<?php
/**
 * Uninstall cleanup.
 *
 * @package WooCommerce_Automation_Bridge
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wab_settings' );

global $wpdb;
$table = $wpdb->prefix . 'wab_logs';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
