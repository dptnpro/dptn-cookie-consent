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
			'inline_assets' => true,
			// URL хранятся строками: get_permalink() на фронте стоил бы SQL-запроса.
			'consent_url'   => '',
			'policy_url'    => '',
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
		);
	}

	public static function all(): array {
		if ( null === self::$cache ) {
			$stored = get_option( self::OPTION, array() );
			$merged = array_replace_recursive( self::defaults(), is_array( $stored ) ? $stored : array() );

			/**
			 * Позволяет задать настройки из кода темы, например до появления страницы настроек.
			 *
			 * @param array $settings
			 */
			self::$cache = apply_filters( 'dptn_cc_settings', $merged );
		}

		return self::$cache;
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
