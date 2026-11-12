# Test plan

This repository includes a manual staging test plan in the root README. Automated WordPress integration tests are a planned next step because they require a WordPress test library and a WooCommerce test environment.

Recommended next additions:

- PHPUnit bootstrap using the official WordPress test suite.
- WooCommerce test helpers for products, variations, and orders.
- Tests for order payload privacy (no billing/shipping PII).
- Tests for settings sanitization and secret preservation.
- REST API authentication tests (valid, invalid, and missing token).
- Retry scheduling tests for transient and permanent HTTP failures.
