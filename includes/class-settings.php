<?php
/**
 * Настройки плагина: значения по умолчанию и доступ к сохранённым.
 *
 * Все настройки фронта лежат в одной autoload-опции, поэтому чтение
 * не добавляет SQL-запросов.
 */

namespace DPTN\CookieConsent;

defined( 'ABSPATH' ) || exit;

final class Settings {

	const OPTION = 'dptn_cc_settings';

	/**
	 * Битовые маски категорий. Значения хранятся в cookie посетителей,
	 * поэтому менять их нельзя — только добавлять новые.
	 */
	const CATEGORY_BITS = array(
		'necessary'  => 1,
		'analytics'  => 2,
		'marketing'  => 4,
		'functional' => 8,
	);

	/** @var array|null */
	private static $cache = null;

	public static function defaults(): array {
		return array(
			// strict — необязательные сервисы ждут согласия; notice — грузятся сразу, баннер только информирует.
			'mode'          => 'strict',
			// Растёт при изменении текста согласия или состава сервисов: баннер покажется всем заново.
			'version'       => 1,
			'cookie_days'   => 365,
			'position'      => 'bottom', // bottom | bottom-left | bottom-right | center
			// Плавающая кнопка «Настройки cookie» после выбора: none | left | right.
			'floating'      => 'none',
			'inline_assets' => true,
			// ID страниц нужны админке; URL вычисляются при сохранении,
			// потому что get_permalink() на фронте стоил бы SQL-запроса.
			'consent_page'  => 0,
			'consent_url'   => '',
			'policy_page'   => 0,
			'policy_url'    => '',
			// Совпадают со значениями по умолчанию в consent.css; на фронт выводятся только отличия.
			'style'         => array(
				'accent'    => '#1a56db',
				'accent_fg' => '#ffffff',
				'bg'        => '#ffffff',
				'fg'        => '#1f2328',
				'radius'    => 12,
			),
			'texts'         => array(
				'title'     => 'Мы используем cookie',
				'body'      => 'Необходимые cookie обеспечивают работу сайта. Аналитические и маркетинговые включаются только с вашего согласия, выбор можно изменить в любой момент.',
				'accept'    => 'Принять все',
				'necessary' => 'Только необходимые',
				'settings'  => 'Настроить',
				'save'      => 'Сохранить выбор',
				'consent'   => 'Согласие на обработку cookie',
				'policy'    => 'Политика обработки персональных данных',
				'services'  => 'Сервисы',
				'embed'     => '%s — контент стороннего сервиса, который использует cookie.',
				'embed_btn' => 'Разрешить и показать',
				'id'        => 'ID вашего согласия',
				'fab'       => 'Настройки cookie',
				'saved'     => 'Выбор сохранён',
				'new_tab'   => '(откроется в новой вкладке)',
			),
			'categories'    => array(
				'necessary'  => array(
					'label' => 'Необходимые',
					'desc'  => 'Обеспечивают работу сайта: вход в аккаунт, корзину, сохранение этого выбора. Отключить нельзя.',
				),
				'analytics'  => array(
					'label' => 'Аналитика',
					'desc'  => 'Статистика посещений: какие страницы смотрят и откуда приходят посетители.',
				),
				'marketing'  => array(
					'label' => 'Маркетинг',
					'desc'  => 'Реклама, ретаргетинг и оценка эффективности рекламных кампаний.',
				),
				'functional' => array(
					'label' => 'Функциональные',
					'desc'  => 'Встроенные карты, видео и виджеты сторонних сервисов.',
				),
			),
			'services'      => array(
				'yandex_metrika' => array(
					'enabled'    => false,
					'counter_id' => '',
					'webvisor'   => false,
					'clickmap'   => true,
					'ecommerce'  => false,
				),
			),
			// Журнал согласий: по ч. 3 ст. 9 152-ФЗ получение согласия доказывает оператор.
			'log'           => array(
				'enabled'   => true,
				'ip'        => 'none', // none | truncated
				'retention' => 1095,   // дней
				'daily_cap' => 10000,  // записей в сутки на весь сайт
			),
			// Скрипты темы и плагинов, подключённые через wp_enqueue_script:
			// список array( handle, category, name, cookies[] ).
			'scripts'       => array(),
			// Встроенный контент (iframe), блокируемый до согласия на «Функциональные».
			'embeds'        => array(
				'youtube'     => false,
				'rutube'      => false,
				'vk_video'    => false,
				'yandex_maps' => false,
				'google_maps' => false,
			),
		);
	}

	public static function all(): array {
		if ( null === self::$cache ) {
			/**
			 * Позволяет переопределить настройки из кода темы. Значения из фильтра
			 * важнее сохранённых в админке.
			 *
			 * @param array $settings
			 */
			self::$cache = apply_filters( 'dptn_cc_settings', self::stored() );
		}

		return self::$cache;
	}

	/**
	 * Сохранённые значения поверх умолчаний, без фильтра — то, что редактирует админка.
	 */
	public static function stored(): array {
		$stored = get_option( self::OPTION, array() );

		return array_replace_recursive( self::defaults(), is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Объявления CSS-переменных баннера из настроек стиля.
	 *
	 * @param bool $only_changed Пропустить значения, совпадающие с умолчаниями consent.css.
	 */
	public static function css_vars( array $style, bool $only_changed = false ): string {
		$defaults = self::defaults()['style'];
		$colors   = array(
			'accent'    => 'accent',
			'accent_fg' => 'accent-fg',
			'bg'        => 'bg',
			'fg'        => 'fg',
		);
		$vars     = '';

		foreach ( $colors as $key => $var ) {
			$color = sanitize_hex_color( (string) ( $style[ $key ] ?? '' ) );
			if ( $color && ! ( $only_changed && strtolower( $color ) === $defaults[ $key ] ) ) {
				$vars .= "--dptn-cc-$var:$color;";
			}
		}

		$radius = (int) ( $style['radius'] ?? $defaults['radius'] );
		if ( ! ( $only_changed && $radius === $defaults['radius'] ) ) {
			$vars .= "--dptn-cc-radius:{$radius}px;";
		}

		return $vars;
	}

	/**
	 * @param mixed $fallback
	 * @return mixed
	 */
	public static function get( string $key, $fallback = null ) {
		$all = self::all();

		return $all[ $key ] ?? $fallback;
	}

	public static function reset_cache(): void {
		self::$cache = null;
	}
}
