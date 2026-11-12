<?php
/**
 * WooCommerce event listeners and outbound API integrations.
 *
 * @package WooCommerce_Automation_Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WAB_Integrations {
	/**
	 * Register WooCommerce hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'maybe_queue_order' ), 10, 4 );
		add_action( 'woocommerce_product_set_stock', array( __CLASS__, 'queue_product_stock' ), 10, 1 );
		add_action( 'woocommerce_variation_set_stock', array( __CLASS__, 'queue_product_stock' ), 10, 1 );
	}

	/**
	 * Queue order webhook when order enters a configured status.
	 *
	 * @param int      $order_id Order ID.
	 * @param string   $from Old status.
	 * @param string   $to New status.
	 * @param WC_Order $order Order object.
	 */
	public static function maybe_queue_order( $order_id, $from, $to, $order ) {
		$settings = WAB_Settings::get();

		if ( 'yes' !== $settings['enabled'] || empty( $settings['order_webhook_url'] ) ) {
			return;
		}

		if ( ! in_array( $to, (array) $settings['order_statuses'], true ) ) {
			return;
		}

		$payload = self::build_order_payload( $order );

		WAB_Queue::schedule( 'order_webhook', $payload );
		WAB_Logger::log(
			'info',
			'order_queued',
			'Order webhook queued.',
			array( 'order_id' => absint( $order_id ), 'status' => $to )
		);
	}

	/**
	 * Build a safe order payload.
	 *
	 * @param WC_Order $order Order object.
	 * @return array
	 */
	public static function build_order_payload( $order ) {
		$items = array();

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();

			$items[] = array(
				'product_id' => $product ? $product->get_id() : 0,
				'sku'        => $product ? $product->get_sku() : '',
				'name'       => $item->get_name(),
				'quantity'   => $item->get_quantity(),
				'total'      => (string) $item->get_total(),
			);
		}

		return array(
			'event'      => 'order.status_changed',
			'order_id'   => $order->get_id(),
			'number'     => $order->get_order_number(),
			'status'     => $order->get_status(),
			'currency'   => $order->get_currency(),
			'total'      => (string) $order->get_total(),
			'created_at' => $order->get_date_created() ? $order->get_date_created()->date( DATE_ATOM ) : '',
			'items'      => $items,
		);
	}

	/**
	 * Queue a product stock update.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function queue_product_stock( $product ) {
		$settings = WAB_Settings::get();

		if ( 'yes' !== $settings['enabled'] || empty( $settings['inventory_api_url'] ) || ! $product instanceof WC_Product ) {
			return;
		}

		$payload = array(
			'event'          => 'inventory.updated',
			'product_id'     => $product->get_id(),
			'parent_id'      => $product->is_type( 'variation' ) ? $product->get_parent_id() : 0,
			'sku'            => $product->get_sku(),
			'stock_quantity' => $product->get_stock_quantity(),
			'stock_status'   => $product->get_stock_status(),
			'manage_stock'   => $product->get_manage_stock(),
			'updated_at'     => gmdate( DATE_ATOM ),
		);

		WAB_Queue::schedule( 'inventory_sync', $payload );
	}

	/**
	 * Send order webhook.
	 *
	 * @param array $payload Payload.
	 * @return bool
	 */
	public static function send_order_webhook( $payload ) {
		$settings = WAB_Settings::get();

		if ( empty( $settings['order_webhook_url'] ) ) {
			return true;
		}

		return self::post_json(
			$settings['order_webhook_url'],
			$payload,
			array(),
			'order_webhook'
		);
	}

	/**
	 * Send inventory update to configured external API.
	 *
	 * @param array $payload Payload.
	 * @return bool
	 */
	public static function send_inventory_update( $payload ) {
		$settings = WAB_Settings::get();

		if ( empty( $settings['inventory_api_url'] ) ) {
			return true;
		}

		$headers = array();
		if ( ! empty( $settings['inventory_api_token'] ) ) {
			$headers['Authorization'] = 'Bearer ' . $settings['inventory_api_token'];
		}

		return self::post_json(
			$settings['inventory_api_url'],
			$payload,
			$headers,
			'inventory_sync'
		);
	}

	/**
	 * Perform an HTTP POST request with safe response handling.
	 *
	 * @param string $url URL.
	 * @param array  $payload JSON payload.
	 * @param array  $headers Additional headers.
	 * @param string $event Event name.
	 * @return bool
	 */
	private static function post_json( $url, $payload, $headers, $event ) {
		$settings = WAB_Settings::get();

		if ( ! wp_http_validate_url( $url ) ) {
			WAB_Logger::log( 'error', $event, 'The configured endpoint URL is invalid.' );
			return false;
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout'     => absint( $settings['timeout'] ),
				'redirection' => 0,
				'headers'     => array_merge(
					array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' ),
					$headers
				),
				'body'        => wp_json_encode( $payload ),
				'data_format' => 'body',
			)
		);

		if ( is_wp_error( $response ) ) {
			WAB_Logger::log(
				'error',
				$event,
				'HTTP request failed: ' . $response->get_error_message()
			);
			return false;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );

		if ( $status >= 200 && $status < 300 ) {
			WAB_Logger::log( 'success', $event, 'Integration request completed successfully.', array( 'http_status' => $status ) );
			return true;
		}

		WAB_Logger::log(
			'error',
			$event,
			'Integration endpoint returned an unsuccessful HTTP status.',
			array( 'http_status' => $status, 'response' => substr( wp_remote_retrieve_body( $response ), 0, 1000 ) )
		);

		return false;
	}
}
