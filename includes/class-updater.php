<?php
/**
 * Обновления из GitHub Releases.
 *
 * WordPress (5.8+) сам спрашивает о новой версии через фильтр update_plugins_{хост Update URI},
 * поэтому достаточно отдать ему данные из манифеста info.json последнего релиза.
 * Манифест берётся по ссылке releases/latest/download — это обычная загрузка файла,
 * а не GitHub API, и лимит 60 запросов в час на IP её не касается.
 *
 * Релиз (zip и info.json) собирает .github/workflows/release.yml по тегу vX.Y.Z.
 */

namespace DPTN\CookieConsent;

defined( 'ABSPATH' ) || exit;

final class Updater {

	const REPO     = 'dptnpro/dptn-cookie-consent';
	const SLUG     = 'dptn-cookie-consent';
	const MANIFEST = 'https://github.com/' . self::REPO . '/releases/latest/download/info.json';
	const CACHE    = 'dptn_cc_update';

	public static function init(): void {
		add_filter( 'update_plugins_github.com', array( self::class, 'check' ), 10, 3 );
		add_filter( 'plugins_api', array( self::class, 'details' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( self::class, 'flush' ) );
	}

	/**
	 * @param mixed  $update      Данные от других обработчиков этого хоста.
	 * @param array  $plugin_data Заголовок плагина.
	 * @param string $plugin_file Путь плагина относительно папки plugins.
	 * @return mixed
	 */
	public static function check( $update, $plugin_data, $plugin_file ) {
		if ( plugin_basename( DPTN_CC_FILE ) !== $plugin_file ) {
			return $update;
		}

		$info = self::manifest();
		if ( ! $info ) {
			return $update;
		}

		return array(
			'slug'         => self::SLUG,
			'plugin'       => $plugin_file,
			'version'      => $info['version'],
			'url'          => 'https://github.com/' . self::REPO,
			'package'      => $info['download_url'],
			'requires'     => $info['requires'] ?? '',
			'requires_php' => $info['requires_php'] ?? '',
			'tested'       => $info['tested'] ?? '',
		);
	}

	/**
	 * Окно «Подробности» у обновления.
	 *
	 * @param mixed  $result
	 * @param string $action
	 * @param object $args
	 * @return mixed
	 */
	public static function details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || self::SLUG !== ( $args->slug ?? '' ) ) {
			return $result;
		}

		$info = self::manifest();
		if ( ! $info ) {
			return $result;
		}

		return (object) array(
			'name'          => 'DPTN Cookie Consent',
			'slug'          => self::SLUG,
			'version'       => $info['version'],
			'author'        => '<a href="https://dptn.pro">DPTN</a>',
			'homepage'      => 'https://github.com/' . self::REPO,
			'requires'      => $info['requires'] ?? '',
			'requires_php'  => $info['requires_php'] ?? '',
			'tested'        => $info['tested'] ?? '',
			'last_updated'  => $info['last_updated'] ?? '',
			'download_link' => $info['download_url'],
			'sections'      => array(
				'changelog' => wpautop( esc_html( $info['changelog'] ?? '' ) ),
			),
		);
	}

	public static function flush(): void {
		delete_site_transient( self::CACHE );
	}

	/**
	 * Манифест последнего релиза или null. Кешируется на 6 часов, ошибка — на час;
	 * «Проверить снова» на странице обновлений кеш сбрасывает.
	 *
	 * @return array|null
	 */
	private static function manifest() {
		$force = is_admin() && ! empty( $_GET['force-check'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$info  = $force ? false : get_site_transient( self::CACHE );

		if ( false === $info ) {
			$info     = array();
			$response = wp_remote_get( self::MANIFEST, array( 'timeout' => 10 ) );

			if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
				$data = json_decode( wp_remote_retrieve_body( $response ), true );
				// Скачивать разрешено только из релизов своего репозитория.
				if ( is_array( $data ) && ! empty( $data['version'] ) && is_string( $data['download_url'] ?? null )
					&& 0 === strpos( $data['download_url'], 'https://github.com/' . self::REPO . '/releases/download/' ) ) {
					$info = $data;
				}
			}

			set_site_transient( self::CACHE, $info, $info ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
		}

		return $info ? $info : null;
	}
}
