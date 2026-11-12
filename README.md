# WooCommerce Automation Bridge

A developer-focused WooCommerce integration plugin demonstrating practical WordPress engineering: order webhooks, inventory synchronization, authenticated inbound REST endpoints, retry handling, and integration logging.

## Features

- **Order webhooks:** Send selected order status changes to an external JSON endpoint.
- **Inventory sync:** Send product and variation stock updates to an external inventory API.
- **Inbound stock API:** Update WooCommerce stock by SKU through an authenticated REST endpoint.
- **Retry handling:** Exponential-backoff retries using Action Scheduler when available, otherwise WP-Cron.
- **Integration logs:** Admin log screen for request outcomes and retry events.
- **Security-conscious defaults:** Capability checks, nonce-protected admin actions, sanitized settings, escaped output, token redaction, and minimal order payloads.

## Requirements

- WordPress 6.2+
- PHP 7.4+
- WooCommerce 8.0+ recommended
- HTTPS strongly recommended
- Action Scheduler is optional (bundled with modern WooCommerce)

## Installation

1. Download or clone this repository.
2. Upload the `woo-automation-integration` directory to `wp-content/plugins/`.
3. Activate WooCommerce, then activate **WooCommerce Automation Bridge**.
4. Go to **WooCommerce → Automation Bridge**.
5. Configure the endpoints and save.

## Configuration

### Order webhook

Set an HTTPS endpoint and select the order statuses that should trigger a webhook. The plugin sends a JSON payload containing order ID, order number, status, currency, total, timestamp, and line items.

Billing and shipping personal data are deliberately excluded from the default payload.

### Outbound inventory API

Set the inventory endpoint. Optionally add a bearer token. The plugin sends stock updates when WooCommerce fires product or variation stock hooks.

### Inbound stock endpoint

`POST /wp-json/wab/v1/stock`

Headers:

```http
Content-Type: application/json
X-WAB-Token: YOUR_INBOUND_TOKEN
```

Body:

```json
{
  "sku": "SKU-001",
  "stock_quantity": 25
}
```

The token is available in **WooCommerce → Automation Bridge**. Treat it like a password.

## Example order webhook payload

```json
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
```

## Architecture

```text
woo-automation-integration.php
├── includes/
│   ├── class-wab-settings.php          # Settings registration and sanitization
│   ├── class-wab-integrations.php      # WooCommerce hooks and outbound requests
│   ├── class-wab-queue.php             # Async processing and retries
│   ├── class-wab-rest-controller.php  # Authenticated inbound REST API
│   ├── class-wab-logger.php            # Database-backed event logs
│   └── class-wab-admin.php             # Settings and logs screens
├── tests/
├── .github/workflows/
├── README.md
└── readme.txt
```

## Reliability notes

- HTTP requests use the WordPress HTTP API.
- Non-2xx responses and transport errors are logged and retried.
- Retry delay increases exponentially and is capped at one hour.
- Action Scheduler is preferred when available; WP-Cron is the fallback.
- External systems should make webhook processing idempotent because retries can deliver the same event more than once.

## Security notes

- Use HTTPS for all endpoints.
- Do not commit real API tokens, credentials, customer data, or production logs.
- The inbound endpoint uses a dedicated shared token in `X-WAB-Token`.
- Rotate the inbound token if it is exposed.
- Review endpoint access, rate limiting, and API authentication before exposing this to a live store.
- The inbound stock route currently supports non-negative integer quantities.
- The plugin intentionally avoids sending customer billing/shipping details by default.

## Testing checklist

Before production use, test on a staging store:

1. Activate with WooCommerce enabled.
2. Confirm settings save and secrets remain masked.
3. Configure a request-inspection endpoint for order webhooks.
4. Move a test order into each selected status.
5. Change stock on a simple product and a variation.
6. Test inbound stock update with a valid token.
7. Test invalid token and unknown SKU responses.
8. Simulate a failing endpoint and confirm retries/logging.
9. Confirm no secrets or personal customer information appear in logs.
10. Test with the store's actual caching, security, and Action Scheduler setup.

## Roadmap

- HMAC-signed outbound webhook payloads.
- Per-endpoint configuration and delivery history.
- Admin action to replay failed events.
- Idempotency keys and persistent delivery records.
- Bulk inventory reconciliation.
- HPOS compatibility test suite and automated integration tests.
- More flexible mapping between WooCommerce fields and external API schemas.

## License

GPL-2.0-or-later.
