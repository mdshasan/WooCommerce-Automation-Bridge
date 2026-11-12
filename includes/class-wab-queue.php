<?php
/**
 * Lightweight retry queue using Action Scheduler when available, with WP-Cron fallback.
 *
 * @package WooCommerce_Automation_Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WAB_Queue {
	/**
	 * Register worker.
	 */
	public static function init() {
		add_action( 'wab_process_retry', array( __CLASS__, 'process' ), 10, 3 );
	}

	/**
	 * Schedule an integration job.
	 *
	 * @param string $job_type Job type.
	 * @param array  $payload Job payload.
	 * @param int    $attempt Current attempt.
	 */
	public static function schedule( $job_type, $payload, $attempt = 0 ) {
		$args = array( sanitize_key( $job_type ), $payload, absint( $attempt ) );

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'wab_process_retry', $args, 'woo-automation-bridge' );
			return;
		}

		wp_schedule_single_event( time() + 30, 'wab_process_retry', $args );
	}

	/**
	 * Process a job and retry failed requests.
	 *
	 * @param string $job_type Job type.
	 * @param array  $payload Job payload.
	 * @param int    $attempt Current attempt.
	 */
	public static function process( $job_type, $payload, $attempt = 0 ) {
		$result = false;

		if ( 'order_webhook' === $job_type ) {
			$result = WAB_Integrations::send_order_webhook( $payload );
		} elseif ( 'inventory_sync' === $job_type ) {
			$result = WAB_Integrations::send_inventory_update( $payload );
		}

		if ( $result ) {
			return;
		}

		$settings   = WAB_Settings::get();
		$max_retries = absint( $settings['max_retries'] );

		if ( $attempt < $max_retries ) {
			$delay = min( 3600, 60 * ( 2 ** $attempt ) );
			$args  = array( $job_type, $payload, $attempt + 1 );

			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( time() + $delay, 'wab_process_retry', $args, 'woo-automation-bridge' );
			} else {
				wp_schedule_single_event( time() + $delay, 'wab_process_retry', $args );
			}

			WAB_Logger::log(
				'warning',
				'retry_scheduled',
				'Integration request failed; a retry has been scheduled.',
				array(
					'job_type' => $job_type,
					'attempt'  => $attempt + 1,
					'delay'    => $delay,
				)
			);
		} else {
			WAB_Logger::log(
				'error',
				'retries_exhausted',
				'Integration request failed after the maximum number of attempts.',
				array( 'job_type' => $job_type, 'attempts' => $attempt + 1 )
			);
		}
	}
}
