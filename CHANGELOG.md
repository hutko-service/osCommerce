# Changelog

## 1.0.0 - 2026-09-02

- Initial stable release for osCommerce v4.
- Added signed hosted-checkout creation through the hutko JSON API.
- Added separate signed handlers for the customer return and server callback.
- Added validation of the Merchant ID, order ID, amount, currency, transaction type and payment status.
- Added idempotent callback processing to prevent duplicate order fulfilment.
- Added configurable pending and paid order statuses, payment-zone filtering and display order.
- Added a responsive hutko logo and payment label for checkout.
- Documented installation, checkout availability, HTTPS and local-testing requirements.
