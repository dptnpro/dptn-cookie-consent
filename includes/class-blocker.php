<?php
/**
 * Точечная блокировка чужого кода до согласия, без буферизации всей страницы.
 *
 * - Скрипты из настроек по handle: фильтр script_loader_tag.
 * - Iframe включённых провайдеров: фильтры the_content и embed_oembed_html.
 *
 * Разметка одинакова для всех посетителей, запуск решает consent.js.
 */

namespace DPTN\CookieConsent;

defined( 'ABSPATH' ) || exit;

final class Blocker {

	/** @var string[] handle => category */
	private static $scripts = array();

	/** @var array id => array( name, regex ) */
	private static $embeds = array();

	public static function init(): void {
		self::$scripts = Services::script_map();
		self::$embeds  = Services::embeds();

		if ( self::$scripts ) {
			add_filter( 'script_loader_tag', array( self::class, 'script_tag' ), 10, 2 );
		}
		if ( self::$embeds ) {
			add_filter( 'the_content', array( self::class, 'iframes' ), 99 );
			add_filter( 'embed_oembed_html', array( self::class, 'iframes' ), 99 );
		}
	}

	/**
	 * Переводит все теги скрипта (основной, before/after, переводы) в type="text/plain".
	 * Данные wp_localize_script выводятся отдельно и остаются как есть: это только переменные.
	 */
	public static function script_tag( string $tag, string $handle ): string {
		if ( ! isset( self::$scripts[ $handle ] ) ) {
			return $tag;
		}

		$category = self::$scripts[ $handle ];
		$extra    = ' data-dptn-cc="' . esc_attr( $category ) . '"';
		// Незаблокированный код уже проверил наличие этой библиотеки и не узнает,
		// что она появилась позже, — после согласия нужна перезагрузка.
		$dependents = self::has_unblocked_dependents( $handle );
		if ( $dependents ) {
			$extra .= ' data-dptn-cc-reload';
		}

		$count = 0;
		$tag   = (string) preg_replace_callback(
			'/<script\b([^>]*)>/i',
			static function ( $m ) use ( $extra ) {
				$attrs = $m[1];
				$type  = '';
				if ( preg_match( '/\stype=(["\'])([^"\']*)\1/i', $attrs, $t ) ) {
					$attrs = str_replace( $t[0], '', $attrs );
					$type  = strtolower( $t[2] );
				}
				if ( '' !== $type && 'text/javascript' !== $type ) {
					$extra .= ' data-type="' . esc_attr( $type ) . '"';
				}

				return '<script type="text/plain"' . $extra . $attrs . '>';
			},
			$tag,
			-1,
			$count
		);

		if ( $dependents && $count ) {
			$tag .= self::writer( $count, Settings::CATEGORY_BITS[ $category ] );
		}

		return $tag;
	}

	/**
	 * Зависимые скрипты темы выполняются по ходу разбора страницы и проверяют библиотеку один раз,
	 * а consent.js в подвале запускается позже них. Поэтому сразу за заблокированными тегами стоит
	 * маленький скрипт: если согласие уже дано, он тут же вставляет их через document.write —
	 * только так библиотека выполнится раньше зависимых. Решение принимает браузер, HTML одинаков для всех.
	 */
	private static function writer( int $count, int $bit ): string {
		$js = '(function(d,n,b,v,s){var m=d.cookie.match(/(?:^|;\s*)dptn_cc=v(\d+)\.(\d+)\./),ok=m&&+m[1]===v?m[2]&b:s==="notice";'
			. 'if(!ok)return;var e=d.currentScript,l=[];while(n--&&(e=e.previousElementSibling))l.unshift(e);'
			. 'l.forEach(function(x){var h=x.outerHTML.replace(\' type="text/plain"\',"").replace(/ data-type="([^"]*)"/," type=\"$1\"");'
			. 'x.removeAttribute("data-dptn-cc");x.removeAttribute("id");d.write(h)})})';

		return sprintf(
			'<script data-dptn-cc-writer%s>%s(document,%d,%d,%d,"%s");</script>' . "\n",
			Compat::ATTRS,
			$js,
			$count,
			$bit,
			(int) Settings::get( 'version' ),
			'notice' === Settings::get( 'mode' ) ? 'notice' : 'strict'
		);
	}

	private static function has_unblocked_dependents( string $handle ): bool {
		foreach ( wp_scripts()->registered as $dependent => $script ) {
			if ( in_array( $handle, (array) $script->deps, true )
				&& ! isset( self::$scripts[ $dependent ] )
				&& wp_script_is( $dependent, 'enqueued' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Переносит src iframe включённых провайдеров в data-dptn-cc-src.
	 *
	 * @param mixed $html
	 * @return mixed
	 */
	public static function iframes( $html ) {
		if ( ! is_string( $html ) || false === stripos( $html, '<iframe' ) ) {
			return $html;
		}

		return preg_replace_callback(
			'/<iframe\b[^>]*>/i',
			static function ( $m ) {
				if ( false !== strpos( $m[0], 'data-dptn-cc' ) || ! preg_match( '/\ssrc=(["\'])(.*?)\1/i', $m[0], $src ) ) {
					return $m[0];
				}

				$url = html_entity_decode( $src[2], ENT_QUOTES );
				foreach ( self::$embeds as $embed ) {
					if ( preg_match( $embed[1], $url ) ) {
						$tag = str_replace( $src[0], ' data-dptn-cc-src=' . $src[1] . $src[2] . $src[1], $m[0] );

						return '<iframe data-dptn-cc="functional" data-dptn-cc-name="' . esc_attr( $embed[0] ) . '"' . substr( $tag, 7 );
					}
				}

				return $m[0];
			},
			$html
		);
	}
}
