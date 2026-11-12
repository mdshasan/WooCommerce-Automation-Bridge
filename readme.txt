=== WooCommerce Automation Bridge ===
Contributors: your-github-username
Tags: woocommerce, integrations, webhooks, inventory, automation, rest-api
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A developer-focused integration toolkit for WooCommerce order webhooks, inventory synchronization, inbound stock updates, retries, and integration logs.

== Description ==

WooCommerce Automation Bridge connects WooCommerce stores to external systems.

Features:
* Send order status webhooks to an external endpoint.
* Synchronize product and variation stock changes to an external inventory API.
* Receive authenticated stock updates through a custom REST API endpoint.
* Retry failed HTTP requests with exponential backoff.
* Use Action Scheduler when available, with WP-Cron fallback.
* Review integration activity in an admin log screen.
* Redact common secret fields from logged context.

== Installation ==

1. Upload the `woo-automation-integration` folder to `/wp-content/plugins/`, or upload the ZIP through Plugins > Add New > Upload Plugin.
2. Activate WooCommerce and this plugin.
3. Open WooCommerce > Automation Bridge.
4. Configure your order webhook and/or inventory API endpoint.
5. Save your settings and review WooCommerce > Integration Logs.

== Outbound order webhook ==

When an order enters a selected status, the plugin sends a JSON POST request.

Example payload:

{
  "event": "order.status_changed",
  "order_id": 123,
  "number": "123",
  "status": "processing",
  "currency": "USD",
  "total": "49.99",
  "created_at": "2025-01-01T12:00:00+00:00",
  "items": [
    {
      "product_id": 456,
      "sku": "SKU-001",
      "name": "Example product",
      "quantity": 1,
      "total": "49.99"
    }
  ]
}

The payload intentionally excludes billing and shipping personal data by default.

== Outbound inventory synchronization ==

When product or variation stock changes, a JSON POST request is sent to the configured Inventory API URL. If a bearer token is configured, it is sent using the Authorization header.

== Inbound stock API ==

Endpoint:

POST /wp-json/wab/v1/stock

Headers:

Content-Type: application/json
X-WAB-Token: YOUR_INBOUND_TOKEN

JSON body:

{
  "sku": "SKU-001",
  "stock_quantity": 25
}

The endpoint updates stock for the WooCommerce product matching the supplied SKU.

== Retry behavior ==

Failed requests are retried up to the configured maximum. Retry delays use exponential backoff. Action Scheduler is used when available; otherwise WordPress single events are used.

== Security notes ==

* Use HTTPS endpoints.
* Keep API tokens private and rotate them if exposed.
* Restrict access to the WordPress administrator account.
* The inbound endpoint requires a dedicated shared token.
* The inbound endpoint currently accepts non-negative stock quantities only.
* Review your external endpoint's authentication, rate limits, and idempotency behavior before production use.
* This plugin is an integration starter and should be tested against your specific API and store configuration before live deployment.

== Developer notes ==

* Main plugin file: `woo-automation-integration.php`
* Integrations: `includes/class-wab-integrations.php`
* Retry queue: `includes/class-wab-queue.php`
* Inbound REST API: `includes/class-wab-rest-controller.php`
* Admin UI: `includes/class-wab-admin.php`

== Changelog ==

= 1.0.0 =
* Initial release.
* Added order status webhooks.
* Added outbound inventory synchronization.
* Added authenticated inbound stock endpoint.
* Added retry queue and integration logs.
