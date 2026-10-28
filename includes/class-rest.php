<?php
/**
 * POST /wp-json/dptn-cc/v1/consent — запись выбора посетителя в журнал.
 *
 * Без nonce: на закешированной странице он протух бы. Вместо этого строгая
 * валидация, лимиты в Log::add() и всегда 204 — ответ не подсказывает,
 * принята ли запись.
 *
 * Тело — JSON строкой с типом text/plain (так отправляет navigator.sendBeacon,
 * без предварительного CORS-запроса): {"id": uuid, "v": версия, "m": маска, "a": действие}.
 */

namespace DPTN\CookieConsent;

defined( 'ABSPATH' ) || exit;

final class Rest {

	const NAMESPACE = 'dptn-cc/v1';

	public static function register(): void {
		register_rest_route(
			self::NAMESPACE,
			'/consent',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'consent' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function consent( \WP_REST_Request $request ): \WP_REST_Response {
		$done = new \WP_REST_Response( null, 204 );
		$log  = (array) Settings::get( 'log', array() );
		$body = $request->get_body();

		if ( empty( $log['enabled'] ) || strlen( $body ) > 256 ) {
			return $done;
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return $done;
		}

		$id      = (string) ( $data['id'] ?? '' );
		$version = (int) ( $data['v'] ?? 0 );
		$mask    = (int) ( $data['m'] ?? 0 );
		$action  = (int) ( $data['a'] ?? 0 );
		$all     = array_sum( Settings::CATEGORY_BITS );

		// Согласие на устаревшую версию текста не принимаем: баннер её уже не показывает.
		if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id )
			|| (int) Settings::get( 'version' ) !== $version
			|| $mask < 1 || ( $mask & ~$all )
			|| ! isset( Log::ACTIONS[ $action ] ) ) {
			return $done;
		}

		Log::add( $id, $version, $mask | Settings::CATEGORY_BITS['necessary'], $action );

		return $done;
	}
}
