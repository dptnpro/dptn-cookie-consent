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
	private static $listed = null;

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'wp_footer', array( self::class, 'render' ), 100 );
		// После загрузки темы: её фильтр dptn_cc_settings должен успеть подключиться.
		add_action( 'wp', array( Blocker::class, 'init' ) );
		add_shortcode( 'dptn_cc_settings', array( self::class, 'shortcode' ) );
	}

	/**
	 * [dptn_cc_settings text="Настройки cookie"] — ссылка, открывающая панель выбора.
	 *
	 * @param array|string $atts
	 */
	public static function shortcode( $atts ): string {
		$atts = shortcode_atts( array( 'text' => 'Настройки cookie' ), $atts, 'dptn_cc_settings' );

		return '<a href="#dptn-cc-settings" class="dptn-cc-settings-link">' . esc_html( $atts['text'] ) . '</a>';
	}

	/**
	 * Без необязательных сервисов спрашивать согласие не о чем — плагин ничего не выводит.
	 */
	private static function listed(): array {
		if ( null === self::$listed ) {
			self::$listed = Services::listed();
		}

		return self::$listed;
	}

	/**
	 * Файловый режим: ассеты кешируются браузером, но это два HTTP-запроса.
	 */
	public static function enqueue(): void {
		if ( Settings::get( 'inline_assets' ) || ! self::listed() ) {
			return;
		}

		wp_enqueue_style( self::HANDLE, DPTN_CC_URL . dptn_cc_asset_path( 'css' ), array(), DPTN_CC_VERSION );
		$vars = self::style_vars();
		if ( '' !== $vars ) {
			wp_add_inline_style( self::HANDLE, $vars );
		}
		wp_enqueue_script(
			self::HANDLE,
			DPTN_CC_URL . dptn_cc_asset_path( 'js' ),
			array(),
			DPTN_CC_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	public static function render(): void {
		$listed = self::listed();
		if ( ! $listed ) {
			return;
		}

		$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
		echo '<script type="application/json" id="dptn-cc-config">' . wp_json_encode( self::config( $listed ), $flags ) . "</script>\n";

		foreach ( Services::enabled() as $service ) {
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
			echo '<style id="dptn-cc-css">' . self::read_asset( 'css' ) . self::style_vars() . "</style>\n";
			echo '<script id="dptn-cc-js"' . Compat::ATTRS . '>' . self::read_asset( 'js' ) . "</script>\n";
			// phpcs:enable
		}
	}

	/**
	 * Конфиг для consent.js. Короткие ключи экономят байты в каждой странице.
	 */
	private static function config( array $services ): array {
		// Фильтр темы dptn_cc_settings мог убрать ключи — подкладываем умолчания.
		$s    = array_replace_recursive( Settings::defaults(), Settings::all() );
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
			'fab'   => in_array( $s['floating'], array( 'left', 'right' ), true ) ? $s['floating'] : '',
			'pos'   => in_array( $s['position'], array( 'bottom', 'bottom-left', 'bottom-right', 'center' ), true ) ? $s['position'] : 'bottom',
			'text'  => array_map( 'strval', (array) $s['texts'] ),
			'links' => array(
				'consent' => esc_url_raw( (string) $s['consent_url'] ),
				'policy'  => esc_url_raw( (string) $s['policy_url'] ),
			),
			// Маршрут Rest::NAMESPACE; класс REST на фронте не загружается.
			'log'   => empty( $s['log']['enabled'] ) ? '' : rest_url( 'dptn-cc/v1/consent' ),
			'cats'  => $cats,
			// Блоки, которые наполняют заблокированные скрипты: до согласия в них заглушка.
			'ph'    => array_values(
				array_filter(
					array_map(
						static function ( $row ) {
							return empty( $row['placeholder'] ) || empty( $row['category'] ) ? null : array(
								'sel'  => (string) $row['placeholder'],
								'cat'  => (string) $row['category'],
								'name' => (string) ( $row['name'] ?? '' ),
							);
						},
						(array) $s['scripts']
					)
				)
			),
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
	 * Цвета и скругление из «Внешнего вида» поверх consent.css.
	 */
	private static function style_vars(): string {
		$vars = Settings::css_vars( (array) Settings::get( 'style', array() ), true );

		return '' === $vars ? '' : '.dptn-cc,.dptn-cc-ph,.dptn-cc-fab{' . $vars . '}';
	}

	private static function read_asset( string $type ): string {
		$code = file_get_contents( DPTN_CC_DIR . dptn_cc_asset_path( $type ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		return false === $code ? '' : $code;
	}
}
