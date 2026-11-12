<?php
/**
 * Integration event logger.
 *
 * @package WooCommerce_Automation_Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WAB_Logger {
	/**
	 * Register hooks.
	 */
	public static function init() {
		// Reserved for future log maintenance hooks.
	}

	/**
	 * Store a log entry.
	 *
	 * @param string $level   Log level.
	 * @param string $event   Event identifier.
	 * @param string $message Human-readable message.
	 * @param array  $context Context data.
	 */
	public static function log( $level, $event, $message, $context = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wab_logs';

		$wpdb->insert(
			$table,
			array(
				'level'      => sanitize_key( $level ),
				'event'      => sanitize_key( $event ),
				'message'    => sanitize_text_field( $message ),
				'context'    => wp_json_encode( self::redact_context( $context ) ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Remove known secret fields before storing context.
	 *
	 * @param array $context Context data.
	 * @return array
	 */
	private static function redact_context( $context ) {
		$secret_keys = array( 'token', 'authorization', 'password', 'secret', 'api_key' );

		foreach ( $context as $key => $value ) {
			if ( in_array( strtolower( (string) $key ), $secret_keys, true ) ) {
				$context[ $key ] = '[redacted]';
			} elseif ( is_array( $value ) ) {
				$context[ $key ] = self::redact_context( $value );
			}
		}

		return $context;
	}

	/**
	 * Retrieve recent logs.
	 *
	 * @param int $limit Number of rows.
	 * @return array
	 */
	public static function recent( $limit = 100 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wab_logs';
		$limit = absint( $limit );

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ),
			ARRAY_A
		);
	}

	/**
	 * Delete all logs.
	 */
	public static function clear() {
		global $wpdb;
		$table = $wpdb->prefix . 'wab_logs';
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}
}
