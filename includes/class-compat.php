<?php
/**
 * Совместимость с оптимизаторами JS.
 *
 * Скрипт баннера и встроенные скрипты для зависимых библиотек должны выполняться сразу,
 * по ходу разбора страницы: отложенный запуск до первого действия посетителя прячет
 * баннер, а у встроенного скрипта ломает порядок запуска библиотек.
 *
 * Общепринятые атрибуты «не оптимизировать» (ATTRS) ставятся на сами теги — их понимают
 * LiteSpeed Cache, Autoptimize, Cloudflare Rocket Loader и другие. WP Rocket их не читает,
 * поэтому для него исключения регистрируются его фильтрами.
 *
 * Заблокированные скрипты (type="text/plain") оптимизаторы не трогают — исключать их не нужно.
 */

namespace DPTN\CookieConsent;

defined( 'ABSPATH' ) || exit;

final class Compat {

	const ATTRS = ' data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-noptimize="1"';

	/** Узнаваемые части наших тегов: id скрипта баннера, маркер встроенного скрипта, путь к ассетам. */
	const MARKERS = array( 'dptn-cc-js', 'data-dptn-cc-writer', 'dptn-cookie-consent/assets/' );

	public static function init(): void {
		// Файловый режим: тег выводит WordPress, атрибуты добавляем через его фильтр.
		add_filter( 'wp_script_attributes', array( self::class, 'script_attributes' ) );

		if ( defined( 'WP_ROCKET_VERSION' ) ) {
			add_filter( 'rocket_delay_js_exclusions', array( self::class, 'exclusions' ) );
			add_filter( 'rocket_exclude_defer_js', array( self::class, 'exclusions' ) );
			add_filter( 'rocket_exclude_js', array( self::class, 'exclusions' ) );
		}
	}

	/**
	 * @param array $attributes
	 */
	public static function script_attributes( $attributes ): array {
		$attributes = (array) $attributes;
		if ( 'dptn-cc-js' === ( $attributes['id'] ?? '' ) ) {
			$attributes += array(
				'data-cfasync'     => 'false',
				'data-no-optimize' => '1',
				'data-no-defer'    => '1',
				'data-noptimize'   => '1',
			);
		}

		return $attributes;
	}

	/**
	 * @param mixed $patterns Шаблоны исключений WP Rocket (регулярные выражения без разделителей).
	 */
	public static function exclusions( $patterns ): array {
		return array_merge( (array) $patterns, self::MARKERS );
	}
}
