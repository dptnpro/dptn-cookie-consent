<?php
/**
 * Каталог сервисов, которые плагин умеет блокировать до согласия.
 *
 * Каталог живёт в коде, а не в БД. Сторонний сервис добавляется фильтром:
 *
 *     add_filter( 'dptn_cc_services', function ( $services ) {
 *         $services['my_pixel'] = array(
 *             'name'     => 'Мой пиксель',
 *             'category' => 'marketing',
 *             'cookies'  => array( '_mp_' ),         // префиксы cookie для удаления при отзыве
 *             'code'     => 'console.log("pixel");', // JS-код без тега <script>
 *             // или 'src' => 'https://…/pixel.js',
 *         );
 *         return $services;
 *     } );
 *
 * Пресеты из каталога включаются в настройках (`services.{id}.enabled`),
 * сервисы с готовым `code`/`src` из фильтра считаются включёнными.
 */

namespace DPTN\CookieConsent;

defined( 'ABSPATH' ) || exit;

final class Services {

	public static function catalog(): array {
		$catalog = array(
			'yandex_metrika' => array(
				'name'     => 'Яндекс.Метрика',
				'category' => 'analytics',
				// Префикс без подчёркивания: в localStorage есть ключи вида _ym12345678_lsid.
				'cookies'  => array( '_ym' ),
				'render'   => array( self::class, 'render_yandex_metrika' ),
			),
		);

		/**
		 * @param array $catalog Сервисы: id => name, category, cookies, render|code|src.
		 */
		return apply_filters( 'dptn_cc_services', $catalog );
	}

	/**
	 * Включённые сервисы с готовым к выводу кодом.
	 *
	 * @return array[] id, name, category, cookies, code, src.
	 */
	public static function enabled(): array {
		$options = Settings::get( 'services', array() );
		$result  = array();

		foreach ( self::catalog() as $id => $service ) {
			if ( empty( $service['category'] ) || ! isset( Settings::CATEGORY_BITS[ $service['category'] ] ) ) {
				continue;
			}

			$code = '';
			$src  = '';

			if ( isset( $service['render'] ) ) {
				if ( empty( $options[ $id ]['enabled'] ) || ! is_callable( $service['render'] ) ) {
					continue;
				}
				$code = (string) call_user_func( $service['render'], (array) $options[ $id ] );
			} else {
				$code = (string) ( $service['code'] ?? '' );
				$src  = (string) ( $service['src'] ?? '' );
			}

			if ( '' === $code && '' === $src ) {
				continue; // Например, не указан номер счётчика.
			}

			$result[] = array(
				'id'       => (string) $id,
				'name'     => (string) ( $service['name'] ?? $id ),
				'category' => $service['category'],
				'cookies'  => array_values( array_map( 'strval', (array) ( $service['cookies'] ?? array() ) ) ),
				'code'     => $code,
				'src'      => $src,
			);
		}

		return $result;
	}

	/**
	 * Стандартный код счётчика без <noscript>: пиксель в noscript
	 * сработал бы без согласия у посетителей с отключённым JS.
	 */
	public static function render_yandex_metrika( array $options ): string {
		$counter = preg_replace( '/\D/', '', (string) ( $options['counter_id'] ?? '' ) );
		if ( '' === $counter ) {
			return '';
		}

		$params = array(
			'clickmap'            => ! empty( $options['clickmap'] ),
			'trackLinks'          => true,
			'accurateTrackBounce' => true,
			'webvisor'            => ! empty( $options['webvisor'] ),
		);
		if ( ! empty( $options['ecommerce'] ) ) {
			$params['ecommerce'] = 'dataLayer';
		}

		return '(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};'
			. 'm[i].l=1*new Date();for(var j=0;j<document.scripts.length;j++){if(document.scripts[j].src===r){return;}}'
			. 'k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})'
			. '(window,document,"script","https://mc.yandex.ru/metrika/tag.js","ym");'
			. 'ym(' . $counter . ',"init",' . wp_json_encode( $params ) . ');';
	}
}
