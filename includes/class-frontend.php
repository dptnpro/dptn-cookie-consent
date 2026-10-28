<?php
/**
 * Вывод на фронте.
 *
 * HTML одинаков для всех посетителей: сервисы печатаются заблокированными
 * (`type="text/plain"`), а решение о запуске принимает consent.js в браузере.
 * Поэтому плагин совместим со страничным кешем.
 */

namespace DPTN\CookieConsent;

defined( 'ABSPATH' ) || exit;

final class Frontend {

	const HANDLE = 'dptn-cc';

	/** @var array[]|null */
	private static $services = null;

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'wp_footer', array( self::class, 'render' ), 100 );
	}

	/**
	 * Без необязательных сервисов спрашивать согласие не о чем — плагин ничего не выводит.
	 */
	private static function services(): array {
		if ( null === self::$services ) {
			self::$services = Services::enabled();
		}

		return self::$services;
	}

	/**
	 * Файловый режим: ассеты кешируются браузером, но это два HTTP-запроса.
	 */
	public static function enqueue(): void {
		if ( Settings::get( 'inline_assets' ) || ! self::services() ) {
			return;
		}

		wp_enqueue_style( self::HANDLE, DPTN_CC_URL . self::asset_path( 'css' ), array(), DPTN_CC_VERSION );
		wp_enqueue_script(
			self::HANDLE,
			DPTN_CC_URL . self::asset_path( 'js' ),
			array(),
			DPTN_CC_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	public static function render(): void {
		$services = self::services();
		if ( ! $services ) {
			return;
		}

		$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
		echo '<script type="application/json" id="dptn-cc-config">' . wp_json_encode( self::config( $services ), $flags ) . "</script>\n";

		foreach ( $services as $service ) {
			printf(
				'<script type="text/plain" data-dptn-cc="%1$s" data-dptn-cc-service="%2$s"%3$s>%4$s</script>' . "\n",
				esc_attr( $service['category'] ),
				esc_attr( $service['id'] ),
				'' !== $service['src'] ? ' src="' . esc_url( $service['src'] ) . '"' : '',
				// Код сервиса — JS из пресета или фильтра; закрывающий тег экранируем, чтобы не порвать разметку.
				str_ireplace( '</script', '<\/script', $service['code'] ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
		}

		if ( Settings::get( 'inline_assets' ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- собственные файлы плагина.
			echo '<style id="dptn-cc-css">' . self::read_asset( 'css' ) . "</style>\n";
			echo '<script id="dptn-cc-js">' . self::read_asset( 'js' ) . "</script>\n";
			// phpcs:enable
		}
	}

	/**
	 * Конфиг для consent.js. Короткие ключи экономят байты в каждой странице.
	 */
	private static function config( array $services ): array {
		$s    = Settings::all();
		$used = array_unique( array_column( $services, 'category' ) );
		$cats = array();

		foreach ( Settings::CATEGORY_BITS as $id => $bit ) {
			// Показываем только категории, в которых есть включённые сервисы.
			if ( 'necessary' !== $id && ! in_array( $id, $used, true ) ) {
				continue;
			}
			$cats[] = array(
				'id'    => $id,
				'bit'   => $bit,
				'label' => (string) ( $s['categories'][ $id ]['label'] ?? $id ),
				'desc'  => (string) ( $s['categories'][ $id ]['desc'] ?? '' ),
				'req'   => 'necessary' === $id,
			);
		}

		$config = array(
			'v'     => max( 1, (int) $s['version'] ),
			'mode'  => 'notice' === $s['mode'] ? 'notice' : 'strict',
			'days'  => max( 1, (int) $s['cookie_days'] ),
			'pos'   => in_array( $s['position'], array( 'bottom', 'bottom-left', 'bottom-right', 'center' ), true ) ? $s['position'] : 'bottom',
			'text'  => array_map( 'strval', (array) $s['texts'] ),
			'links' => array(
				'consent' => esc_url_raw( (string) $s['consent_url'] ),
				'policy'  => esc_url_raw( (string) $s['policy_url'] ),
			),
			'cats'  => $cats,
			'svc'   => array_map(
				static function ( $service ) {
					return array(
						'id'      => $service['id'],
						'name'    => $service['name'],
						'cat'     => $service['category'],
						'cookies' => $service['cookies'],
					);
				},
				$services
			),
		);

		/**
		 * @param array $config Конфиг, который получит consent.js.
		 */
		return apply_filters( 'dptn_cc_frontend_config', $config );
	}

	/**
	 * Минифицированная версия, если собрана, иначе исходник.
	 */
	private static function asset_path( string $type ): string {
		return is_readable( DPTN_CC_DIR . "assets/consent.min.$type" ) ? "assets/consent.min.$type" : "assets/src/consent.$type";
	}

	private static function read_asset( string $type ): string {
		$code = file_get_contents( DPTN_CC_DIR . self::asset_path( $type ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		return false === $code ? '' : $code;
	}
}
