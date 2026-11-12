<?php
/**
 * Admin settings and logs screens.
 *
 * @package WooCommerce_Automation_Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WAB_Admin {
	/**
	 * Register admin hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_post_wab_clear_logs', array( __CLASS__, 'clear_logs' ) );
	}

	/**
	 * Add submenu pages under WooCommerce.
	 */
	public static function add_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Automation Bridge', 'woo-automation-bridge' ),
			__( 'Automation Bridge', 'woo-automation-bridge' ),
			'manage_woocommerce',
			'wab-settings',
			array( __CLASS__, 'render_settings' )
		);

		add_submenu_page(
			'woocommerce',
			__( 'Integration Logs', 'woo-automation-bridge' ),
			__( 'Integration Logs', 'woo-automation-bridge' ),
			'manage_woocommerce',
			'wab-logs',
			array( __CLASS__, 'render_logs' )
		);
	}

	/**
	 * Render settings page.
	 */
	public static function render_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$settings = WAB_Settings::get();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WooCommerce Automation Bridge', 'woo-automation-bridge' ); ?></h1>
			<p><?php esc_html_e( 'Connect WooCommerce to external systems using outbound webhooks and REST API inventory updates.', 'woo-automation-bridge' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'wab_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable integrations', 'woo-automation-bridge' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( WAB_OPTION_KEY ); ?>[enabled]" value="1" <?php checked( 'yes', $settings['enabled'] ); ?>> <?php esc_html_e( 'Enable outbound integrations', 'woo-automation-bridge' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="wab-order-url"><?php esc_html_e( 'Order webhook URL', 'woo-automation-bridge' ); ?></label></th>
						<td><input id="wab-order-url" class="regular-text" type="url" name="<?php echo esc_attr( WAB_OPTION_KEY ); ?>[order_webhook_url]" value="<?php echo esc_attr( $settings['order_webhook_url'] ); ?>" placeholder="https://example.com/webhooks/orders"><p class="description"><?php esc_html_e( 'Receives JSON when an order enters one of the selected statuses.', 'woo-automation-bridge' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Order statuses', 'woo-automation-bridge' ); ?></th>
						<td>
							<?php
							$statuses = array(
								'processing' => 'Processing',
								'completed'  => 'Completed',
								'on-hold'    => 'On hold',
								'pending'    => 'Pending payment',
								'cancelled'  => 'Cancelled',
								'refunded'   => 'Refunded',
								'failed'     => 'Failed',
							);
							foreach ( $statuses as $status => $label ) :
								?>
								<label style="display:inline-block;margin:0 14px 8px 0"><input type="checkbox" name="<?php echo esc_attr( WAB_OPTION_KEY ); ?>[order_statuses][]" value="<?php echo esc_attr( $status ); ?>" <?php checked( in_array( $status, (array) $settings['order_statuses'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wab-inventory-url"><?php esc_html_e( 'Inventory API URL', 'woo-automation-bridge' ); ?></label></th>
						<td><input id="wab-inventory-url" class="regular-text" type="url" name="<?php echo esc_attr( WAB_OPTION_KEY ); ?>[inventory_api_url]" value="<?php echo esc_attr( $settings['inventory_api_url'] ); ?>" placeholder="https://api.example.com/inventory"><p class="description"><?php esc_html_e( 'Receives JSON whenever product stock changes.', 'woo-automation-bridge' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="wab-inventory-token"><?php esc_html_e( 'Inventory API bearer token', 'woo-automation-bridge' ); ?></label></th>
						<td><input id="wab-inventory-token" class="regular-text" type="password" autocomplete="new-password" name="<?php echo esc_attr( WAB_OPTION_KEY ); ?>[inventory_api_token]" value="" placeholder="<?php echo esc_attr( empty( $settings['inventory_api_token'] ) ? '' : '•••••••• (saved; leave blank to keep)' ); ?>"><p class="description"><?php esc_html_e( 'Leave blank to keep the existing token.', 'woo-automation-bridge' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="wab-inbound-token"><?php esc_html_e( 'Inbound API token', 'woo-automation-bridge' ); ?></label></th>
						<td><input id="wab-inbound-token" class="regular-text" type="text" name="<?php echo esc_attr( WAB_OPTION_KEY ); ?>[inbound_token]" value="<?php echo esc_attr( $settings['inbound_token'] ); ?>"><p class="description"><?php esc_html_e( 'Send this as the X-WAB-Token header when calling the inbound stock endpoint. Keep it secret.', 'woo-automation-bridge' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="wab-timeout"><?php esc_html_e( 'Request timeout (seconds)', 'woo-automation-bridge' ); ?></label></th>
						<td><input id="wab-timeout" type="number" min="5" max="30" name="<?php echo esc_attr( WAB_OPTION_KEY ); ?>[timeout]" value="<?php echo esc_attr( $settings['timeout'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="wab-retries"><?php esc_html_e( 'Maximum retries', 'woo-automation-bridge' ); ?></label></th>
						<td><input id="wab-retries" type="number" min="0" max="10" name="<?php echo esc_attr( WAB_OPTION_KEY ); ?>[max_retries]" value="<?php echo esc_attr( $settings['max_retries'] ); ?>"></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Inbound stock endpoint', 'woo-automation-bridge' ); ?></h2>
			<p><code><?php echo esc_html( rest_url( 'wab/v1/stock' ) ); ?></code></p>
			<p><?php esc_html_e( 'Send a POST request with JSON containing sku and stock_quantity, and include your X-WAB-Token header.', 'woo-automation-bridge' ); ?></p>
			<pre><code>{
  "sku": "YOUR-PRODUCT-SKU",
  "stock_quantity": 25
}</code></pre>
		</div>
		<?php
	}

	/**
	 * Render logs page.
	 */
	public static function render_logs() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$logs = WAB_Logger::recent( 100 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Integration Logs', 'woo-automation-bridge' ); ?></h1>
			<p><?php esc_html_e( 'Showing the latest 100 integration events. Sensitive tokens are not stored in log context.', 'woo-automation-bridge' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Delete all integration logs?', 'woo-automation-bridge' ) ); ?>');">
				<?php wp_nonce_field( 'wab_clear_logs' ); ?>
				<input type="hidden" name="action" value="wab_clear_logs">
				<?php submit_button( __( 'Clear logs', 'woo-automation-bridge' ), 'delete', 'submit', false ); ?>
			</form>
			<table class="widefat striped" style="margin-top:16px">
				<thead><tr><th><?php esc_html_e( 'Date (UTC)', 'woo-automation-bridge' ); ?></th><th><?php esc_html_e( 'Level', 'woo-automation-bridge' ); ?></th><th><?php esc_html_e( 'Event', 'woo-automation-bridge' ); ?></th><th><?php esc_html_e( 'Message', 'woo-automation-bridge' ); ?></th><th><?php esc_html_e( 'Context', 'woo-automation-bridge' ); ?></th></tr></thead>
				<tbody>
				<?php if ( empty( $logs ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No integration events recorded yet.', 'woo-automation-bridge' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $logs as $log ) : ?>
						<tr>
							<td><?php echo esc_html( $log['created_at'] ); ?></td>
							<td><?php echo esc_html( ucfirst( $log['level'] ) ); ?></td>
							<td><code><?php echo esc_html( $log['event'] ); ?></code></td>
							<td><?php echo esc_html( $log['message'] ); ?></td>
							<td><code><?php echo esc_html( $log['context'] ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Clear logs action.
	 */
	public static function clear_logs() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to clear logs.', 'woo-automation-bridge' ) );
		}

		check_admin_referer( 'wab_clear_logs' );
		WAB_Logger::clear();
		wp_safe_redirect( admin_url( 'admin.php?page=wab-logs&cleared=1' ) );
		exit;
	}
}
