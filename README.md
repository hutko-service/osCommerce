# hutko payment module for osCommerce v4

Hosted checkout integration for [hutko](https://hutko.org/) and osCommerce v4.

## Features

- creates a signed hosted-checkout session through `https://pay.hutko.org/api/checkout/url`
- signs API requests and validates callback signatures
- checks the Merchant ID, order ID, amount, currency and approved purchase status before marking an order paid
- safely acknowledges duplicate and non-approved callbacks without fulfilling the order
- displays a responsive hutko logo and payment label that adapt to the checkout width
- supports payment-zone filtering

## Requirements

- osCommerce v4
- PHP 8.0 or newer with cURL and JSON
- an HTTPS storefront
- hutko Merchant ID and Secret key

## Installation

Copy the contents of `upload/` into the root directory of the osCommerce store, preserving paths. In the osCommerce administrator area, open payment modules, install **hutko**, and configure:

1. Merchant ID
2. Secret key
3. pending and paid order statuses
4. payment zone and display order, if required

In the module's **Restrictions** section, select **Available for → Checkout** and click **Update**. osCommerce will not display hutko as a payment method during checkout unless this checkbox is selected, even when **Enable hutko** is set to `TRUE`.

The callback URL is generated automatically:

`https://STORE/callback/webhooks.payment.hutko`

The store must accept HTTPS POST requests to this URL. Payment approval is based only on a backend callback with a valid signature and matching order data; the customer's browser return does not mark the order paid.

After osCommerce saves the order, the module creates the hosted checkout through hutko's JSON API and redirects the customer to the HTTPS checkout URL returned by hutko. The module does not include or directly execute the hutko JavaScript SDK.

After payment, hutko returns the customer's browser by signed POST to the module's return callback. The module validates the response, clears the osCommerce checkout state, and redirects the customer to checkout success. Payment approval is processed separately through the signed server callback and never depends on the browser return.

Both the storefront and generated callback URLs must use HTTPS. hutko's hosted checkout uses a legacy compatibility proxy for plain-HTTP `response_url` values; therefore `http://localhost` is not a valid end-to-end redirect test. Configure HTTPS in the local web server or use an HTTPS tunnel.

Use different order statuses for the two payment stages: a pending/redirected status while the customer is at the payment gateway and the store's successful online-payment status after an approved callback.

## Official test credentials

For testing, enter hutko's published credentials directly in the module configuration:

- Merchant ID: `1700002`
- Secret key: `test`
- Currency: `UAH`

There is no separate sandbox endpoint. Test requests use the normal hutko API endpoint. Replace the test credentials with the merchant's own Merchant ID and Secret key before production use.

See the [hutko documentation](https://docs.hutko.org/) for test cards and API details.

## License

GNU General Public License v3.0 or later. See [LICENSE](LICENSE).
