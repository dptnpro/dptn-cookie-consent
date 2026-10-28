<?php
/**
 * Удаление данных плагина при удалении через админку.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'dptn_cc_settings' );
