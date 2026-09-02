# Changelog

## 1.0.0 - 2026-09-02

- Added hosted checkout creation through hutko's `checkout/url` JSON API.
- Redirected customers only to validated HTTPS checkout URLs returned by hutko.
- Added an osCommerce-native customer return callback that validates the signed browser response, clears checkout state, and redirects to checkout success without treating the browser return as the authoritative payment notification.
- Kept authoritative payment processing exclusively on the separate signed server callback.
- Set the default paid status independently from the pending/redirected status by using osCommerce's successful online-payment status.
- Added hosted checkout creation using the hutko JSON API.
- Added signed request and callback handling.
- Added Merchant ID, order ID, amount, currency and approved purchase validation.
- Added pending and paid order status configuration.
- Documented the required osCommerce **Available for → Checkout** module setting.
- Added a responsive, theme-neutral hutko logo and label to the checkout payment option.
- Documented the HTTPS requirement for browser returns and local testing.
