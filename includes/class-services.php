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

	/**
	 * Провайдеры встроенного контента: id => name, регулярка по адресу iframe.
	 */
	const EMBEDS = array(
		'youtube'     => array( 'YouTube', '#^(https?:)?//([a-z0-9-]+\.)?(youtube\.com|youtube-nocookie\.com|youtu\.be)/#i' ),
		'rutube'      => array( 'RUTUBE', '#^(https?:)?//([a-z0-9-]+\.)?rutube\.ru/#i' ),
		'vk_video'    => array( 'VK Видео', '#^(https?:)?//([a-z0-9-]+\.)?(vk\.com|vk\.ru|vkvideo\.ru)/#i' ),
		'yandex_maps' => array( 'Яндекс.Карты', '#^(https?:)?//([a-z0-9-]+\.)?yandex\.(ru|com|by|kz)/(map-widget|maps)/#i' ),
		'google_maps' => array( 'Google Карты', '#^(https?:)?//([a-z0-9-]+\.)?google\.[a-z.]+/maps#i' ),
	);

	/**
	 * Подсказки для найденных на сайте скриптов: регулярка по handle или адресу => name, category, cookies.
	 * Только подсказки — блокируется лишь то, что админ добавил в настройках.
	 */
	const SCRIPT_HINTS = array(
		'#^sourcebuster-js$#'               => array( 'WooCommerce: источники трафика', 'marketing', array( 'sbjs_' ) ),
		'#^wc-order-attribution$#'          => array( 'WooCommerce: атрибуция заказов', 'marketing', array() ),
		'#//api-maps\.yandex\.ru/#'         => array( 'Яндекс.Карты', 'functional', array( 'mdd' ) ),
		'#//(top-fwz1\.mail\.ru|top\.mail\.ru)/#' => array( 'Top.Mail.ru / VK Реклама', 'marketing', array( 'tmr_' ) ),
		'#//vk\.(com|ru)/js/api/openapi#'   => array( 'Пиксель VK', 'marketing', array() ),
		'#//www\.googletagmanager\.com/#'   => array( 'Google Analytics / Tag Manager', 'analytics', array( '_ga', '_gid' ) ),
		'#//www\.youtube\.com/iframe_api#'  => array( 'YouTube API', 'functional', array() ),
		'#//cloud\.roistat\.com/#'          => array( 'Roistat', 'marketing', array( 'roistat' ) ),
		'#//mod\.calltouch\.ru/#'           => array( 'Calltouch', 'marketing', array( '_ct' ) ),
		'#//cdn\.callibri\.ru/#'            => array( 'Callibri', 'marketing', array( 'callibri' ) ),
		'#//app\.(comagic|uiscom)\.ru/#'    => array( 'CoMagic / UIS', 'marketing', array( '_comagic', 'comagic' ) ),
		'#//code\.jivo(site)?\.(ru|com)/#'  => array( 'Jivo (чат)', 'functional', array( 'jv_' ) ),
		'#//mc\.yandex\.ru/#'               => array( 'Яндекс.Метрика — подключите на вкладке выше', '', array() ),
	);

	/**
	 * Подсказка для скрипта по handle и адресу или null.
	 */
	public static function hint( string $handle, string $src ): ?array {
		foreach ( self::SCRIPT_HINTS as $pattern => $hint ) {
			if ( preg_match( $pattern, $handle ) || preg_match( $pattern, $src ) ) {
				return $hint;
			}
		}

		return null;
	}

	/**
	 * Скрипты по handle из настроек: handle => category.
	 *
	 * @return string[]
	 */
	public static function script_map(): array {
		$map = array();
		foreach ( (array) Settings::get( 'scripts', array() ) as $row ) {
			if ( ! empty( $row['handle'] ) && isset( Settings::CATEGORY_BITS[ $row['category'] ?? '' ] ) ) {
				$map[ (string) $row['handle'] ] = (string) $row['category'];
			}
		}

		return $map;
	}

	/**
	 * Включённые провайдеры встроенного контента: id => array( name, regex ).
	 */
	public static function embeds(): array {
		return array_intersect_key( self::EMBEDS, array_filter( (array) Settings::get( 'embeds', array() ) ) );
	}

	/**
	 * Всё, о чём спрашиваем согласие: сервисы с кодом, скрипты по handle и встроенный контент.
	 * Нужен баннеру для списка категорий и сервисов.
	 *
	 * @return array[] id, name, category, cookies.
	 */
	public static function listed(): array {
		$list = array();
		foreach ( self::enabled() as $service ) {
			$list[] = array_intersect_key( $service, array_flip( array( 'id', 'name', 'category', 'cookies' ) ) );
		}
		foreach ( (array) Settings::get( 'scripts', array() ) as $row ) {
			if ( ! empty( $row['handle'] ) && isset( Settings::CATEGORY_BITS[ $row['category'] ?? '' ] ) ) {
				$list[] = array(
					'id'       => 'script:' . $row['handle'],
					'name'     => (string) ( $row['name'] ?? $row['handle'] ),
					'category' => (string) $row['category'],
					'cookies'  => array_values( array_map( 'strval', (array) ( $row['cookies'] ?? array() ) ) ),
				);
			}
		}
		foreach ( self::embeds() as $id => $embed ) {
			$list[] = array(
				'id'       => 'embed:' . $id,
				'name'     => $embed[0],
				'category' => 'functional',
				'cookies'  => array(),
			);
		}

		return $list;
	}

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
