<?php
/**
 * Plugin settings.
 *
 * @package WooCommerce_Automation_Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WAB_Settings {
	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Register plugin settings.
	 */
	public static function register_settings() {
		register_setting(
			'wab_settings_group',
			WAB_OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Raw settings.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$old   = self::get();

		$statuses = isset( $input['order_statuses'] ) && is_array( $input['order_statuses'] )
			? array_map( 'sanitize_key', $input['order_statuses'] )
			: array();

		$allowed_statuses = array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' );

		return array(
			'enabled'             => ! empty( $input['enabled'] ) ? 'yes' : 'no',
			'order_webhook_url'   => isset( $input['order_webhook_url'] ) ? esc_url_raw( trim( $input['order_webhook_url'] ) ) : '',
			'order_statuses'      => array_values( array_intersect( $statuses, $allowed_statuses ) ),
			'inventory_api_url'   => isset( $input['inventory_api_url'] ) ? esc_url_raw( trim( $input['inventory_api_url'] ) ) : '',
			'inventory_api_token' => isset( $input['inventory_api_token'] ) && '' !== $input['inventory_api_token']
				? sanitize_text_field( $input['inventory_api_token'] )
				: ( isset( $old['inventory_api_token'] ) ? $old['inventory_api_token'] : '' ),
			'inbound_token'       => isset( $input['inbound_token'] ) && '' !== $input['inbound_token']
				? sanitize_text_field( $input['inbound_token'] )
				: ( isset( $old['inbound_token'] ) ? $old['inbound_token'] : wp_generate_password( 48, false, false ) ),
			'timeout'             => isset( $input['timeout'] ) ? min( 30, max( 5, absint( $input['timeout'] ) ) ) : 15,
			'max_retries'         => isset( $input['max_retries'] ) ? min( 10, max( 0, absint( $input['max_retries'] ) ) ) : 3,
		);
	}

	/**
	 * Get merged settings.
	 *
	 * @return array
	 */
	public static function get() {
		$defaults = array(
			'enabled'             => 'yes',
			'order_webhook_url'   => '',
			'order_statuses'      => array( 'processing', 'completed' ),
			'inventory_api_url'   => '',
			'inventory_api_token' => '',
			'inbound_token'       => '',
			'timeout'             => 15,
			'max_retries'         => 3,
		);

		return wp_parse_args( get_option( WAB_OPTION_KEY, array() ), $defaults );
	}
}
