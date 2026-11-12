<?php
/**
 * Plugin Name: WooCommerce Automation Bridge
 * Plugin URI: https://github.com/your-github-username/woo-automation-integration
 * Description: A developer-focused integration toolkit for WooCommerce order webhooks, inventory synchronization, inbound stock updates, retries, and integration logs.
 * Version: 1.0.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: Your Name
 * Author URI: https://github.com/your-github-username
 * Text Domain: woo-automation-bridge
 * Domain Path: /languages
 *
 * @package WooCommerce_Automation_Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WAB_VERSION', '1.0.0' );
define( 'WAB_FILE', __FILE__ );
define( 'WAB_PATH', plugin_dir_path( __FILE__ ) );
define( 'WAB_URL', plugin_dir_url( __FILE__ ) );
define( 'WAB_OPTION_KEY', 'wab_settings' );

require_once WAB_PATH . 'includes/class-wab-logger.php';
require_once WAB_PATH . 'includes/class-wab-settings.php';
require_once WAB_PATH . 'includes/class-wab-queue.php';
require_once WAB_PATH . 'includes/class-wab-integrations.php';
require_once WAB_PATH . 'includes/class-wab-rest-controller.php';
require_once WAB_PATH . 'includes/class-wab-admin.php';

/**
 * Bootstrap plugin services after plugins load.
 */
function wab_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wab_woocommerce_missing_notice' );
		return;
	}

	WAB_Settings::init();
	WAB_Logger::init();
	WAB_Queue::init();
	WAB_Integrations::init();
	WAB_REST_Controller::init();

	if ( is_admin() ) {
		WAB_Admin::init();
	}
}
add_action( 'plugins_loaded', 'wab_init', 20 );

/**
 * Show an admin notice when WooCommerce is unavailable.
 */
function wab_woocommerce_missing_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>' .
		esc_html__( 'WooCommerce Automation Bridge requires WooCommerce to be installed and active.', 'woo-automation-bridge' ) .
		'</p></div>';
}

/**
 * Create the log table on activation.
 */
function wab_activate() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table_name      = $wpdb->prefix . 'wab_logs';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table_name} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		level varchar(20) NOT NULL DEFAULT 'info',
		event varchar(100) NOT NULL,
		message text NOT NULL,
		context longtext NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY level (level),
		KEY event (event),
		KEY created_at (created_at)
	) {$charset_collate};";

	dbDelta( $sql );

	if ( false === get_option( WAB_OPTION_KEY ) ) {
		add_option(
			WAB_OPTION_KEY,
			array(
				'enabled'             => 'yes',
				'order_webhook_url'   => '',
				'order_statuses'      => array( 'processing', 'completed' ),
				'inventory_api_url'   => '',
				'inventory_api_token' => '',
				'inbound_token'       => wp_generate_password( 48, false, false ),
				'timeout'             => 15,
				'max_retries'         => 3,
			)
		);
	}
}
register_activation_hook( __FILE__, 'wab_activate' );

/**
 * Clear scheduled fallback events on deactivation.
 */
function wab_deactivate() {
	wp_clear_scheduled_hook( 'wab_process_retry' );
}
register_deactivation_hook( __FILE__, 'wab_deactivate' );
