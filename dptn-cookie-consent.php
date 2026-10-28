<?php
/**
 * Plugin Name:       DPTN Cookie Consent
 * Plugin URI:        https://dptn.pro
 * Description:       Лёгкий баннер согласия на cookie для сайтов РФ (152-ФЗ). Совместим со страничным кешем. Разработчик: DPTN (dptn.pro).
 * Version:           0.3.3
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            DPTN
 * Author URI:        https://dptn.pro
 * License:           GPL-2.0-or-later
 * Text Domain:       dptn-cookie-consent
 * Update URI:        https://dptn.pro
 */

defined( 'ABSPATH' ) || exit;

define( 'DPTN_CC_VERSION', '0.3.3' );
define( 'DPTN_CC_FILE', __FILE__ );
define( 'DPTN_CC_DIR', plugin_dir_path( __FILE__ ) );
define( 'DPTN_CC_URL', plugin_dir_url( __FILE__ ) );

require_once DPTN_CC_DIR . 'includes/class-settings.php';
require_once DPTN_CC_DIR . 'includes/class-services.php';
require_once DPTN_CC_DIR . 'includes/functions.php';

register_activation_hook( __FILE__, 'dptn_cc_activate' );
register_deactivation_hook( __FILE__, 'dptn_cc_deactivate' );
add_action( 'plugins_loaded', 'dptn_cc_bootstrap' );

/**
 * Таблица журнала и ежедневная очистка.
 */
function dptn_cc_activate() {
	require_once DPTN_CC_DIR . 'includes/class-log.php';
	DPTN\CookieConsent\Log::install();
}

function dptn_cc_deactivate() {
	wp_clear_scheduled_hook( 'dptn_cc_cleanup' );
}

/**
 * Подключает только то, что нужно в текущем контексте.
 */
function dptn_cc_bootstrap() {
	if ( wp_doing_cron() ) {
		require_once DPTN_CC_DIR . 'includes/class-log.php';
		add_action( 'dptn_cc_cleanup', array( 'DPTN\CookieConsent\Log', 'cleanup' ) );
		return;
	}

	if ( wp_doing_ajax() ) {
		return;
	}

	if ( is_admin() ) {
		require_once DPTN_CC_DIR . 'includes/class-log.php';
		require_once DPTN_CC_DIR . 'includes/class-admin.php';
		DPTN\CookieConsent\Admin::init();
		return;
	}

	require_once DPTN_CC_DIR . 'includes/class-frontend.php';
	require_once DPTN_CC_DIR . 'includes/class-blocker.php';
	require_once DPTN_CC_DIR . 'includes/class-compat.php';
	DPTN\CookieConsent\Frontend::init();
	DPTN\CookieConsent\Compat::init();

	// Срабатывает только на REST-запросах.
	add_action( 'rest_api_init', 'dptn_cc_rest_init' );
}

function dptn_cc_rest_init() {
	require_once DPTN_CC_DIR . 'includes/class-log.php';
	require_once DPTN_CC_DIR . 'includes/class-rest.php';
	DPTN\CookieConsent\Rest::register();
}
