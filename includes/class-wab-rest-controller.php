<?php
/**
 * Inbound REST API endpoints.
 *
 * @package WooCommerce_Automation_Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WAB_REST_Controller {
	/**
	 * Register REST routes.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 */
	public static function register_routes() {
		register_rest_route(
			'wab/v1',
			'/stock',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'update_stock' ),
				'permission_callback' => array( __CLASS__, 'authenticate' ),
				'args'                => array(
					'sku' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'stock_quantity' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Authenticate using a dedicated shared secret header.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public static function authenticate( $request ) {
		$settings = WAB_Settings::get();
		$provided = $request->get_header( 'x-wab-token' );
		$expected = (string) $settings['inbound_token'];

		if ( '' === $expected || '' === $provided || ! hash_equals( $expected, (string) $provided ) ) {
			return new WP_Error(
				'wab_forbidden',
				__( 'Invalid integration token.', 'woo-automation-bridge' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Update WooCommerce stock by SKU.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_stock( $request ) {
		$sku      = sanitize_text_field( $request->get_param( 'sku' ) );
		$quantity = absint( $request->get_param( 'stock_quantity' ) );
		$product_id = wc_get_product_id_by_sku( $sku );

		if ( ! $product_id ) {
			return new WP_Error(
				'wab_product_not_found',
				__( 'No product was found for the supplied SKU.', 'woo-automation-bridge' ),
				array( 'status' => 404 )
			);
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return new WP_Error(
				'wab_invalid_product',
				__( 'The product could not be loaded.', 'woo-automation-bridge' ),
				array( 'status' => 404 )
			);
		}

		wc_update_product_stock( $product, $quantity, 'set' );

		WAB_Logger::log(
			'info',
			'inbound_stock_update',
			'Product stock updated through the inbound REST API.',
			array( 'product_id' => $product_id, 'sku' => $sku, 'stock_quantity' => $quantity )
		);

		return rest_ensure_response(
			array(
				'success'        => true,
				'product_id'     => $product_id,
				'sku'            => $sku,
				'stock_quantity' => $quantity,
			)
		);
	}
}
