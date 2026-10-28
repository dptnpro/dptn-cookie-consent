<?php
/**
 * Удаление данных плагина при удалении через админку: настройки, реестр версий, журнал, cron.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

delete_option( 'dptn_cc_settings' );
delete_option( 'dptn_cc_versions' );
delete_option( 'dptn_cc_db_version' );
wp_clear_scheduled_hook( 'dptn_cc_cleanup' );

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dptn_cc_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
