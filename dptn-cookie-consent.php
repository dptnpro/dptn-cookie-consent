<?php
/**
 * Plugin Name:       DPTN Cookie Consent
 * Plugin URI:        https://dptn.pro
 * Description:       Лёгкий баннер согласия на cookie для сайтов РФ (152-ФЗ). Совместим со страничным кешем.
 * Version:           0.1.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            DPTN
 * Author URI:        https://dptn.pro
 * License:           GPL-2.0-or-later
 * Text Domain:       dptn-cookie-consent
 * Update URI:        https://dptn.pro
 */

defined( 'ABSPATH' ) || exit;

define( 'DPTN_CC_VERSION', '0.1.0' );
define( 'DPTN_CC_FILE', __FILE__ );
define( 'DPTN_CC_DIR', plugin_dir_path( __FILE__ ) );
define( 'DPTN_CC_URL', plugin_dir_url( __FILE__ ) );

require_once DPTN_CC_DIR . 'includes/class-settings.php';
require_once DPTN_CC_DIR . 'includes/class-services.php';
require_once DPTN_CC_DIR . 'includes/functions.php';

add_action( 'plugins_loaded', 'dptn_cc_bootstrap' );

/**
 * Подключает только то, что нужно в текущем контексте.
 */
function dptn_cc_bootstrap() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return; // Страница настроек появится на этапе 2.
	}

	require_once DPTN_CC_DIR . 'includes/class-frontend.php';
	DPTN\CookieConsent\Frontend::init();
}
