# hutko payment module for osCommerce v4

Hosted checkout integration for [hutko](https://hutko.org/) and osCommerce v4.

## Features

- creates a signed hosted checkout through the hutko API
- validates callback signatures and payment details
- verifies the Merchant ID, order ID, amount, currency and payment status before marking an order as paid
- processes server callbacks independently from the customer's browser return
- handles repeated callbacks without fulfilling an order twice
- provides configurable pending and paid order statuses
- supports payment-zone restrictions
- displays a responsive hutko logo and payment label at checkout

## Requirements

- osCommerce v4
- PHP 8.0 or newer with cURL and JSON
- an HTTPS storefront and HTTPS callback URLs
- a hutko Merchant ID and Secret key

## Installation

1. Copy the contents of `upload/` into the root directory of the osCommerce store, preserving the directory structure.
2. In the osCommerce administrator area, open the payment modules and install **hutko**.
3. Configure the Merchant ID and Secret key.
4. Select different pending and paid order statuses.
5. Configure the payment zone and display order if required.
6. Under **Restrictions**, select **Available for → Checkout**, then click **Update**.

The payment method will not appear at checkout unless **Available for → Checkout** is selected, even when **Enable hutko** is set to `TRUE`.

## Payment processing

The module creates a signed checkout session through:

`https://pay.hutko.org/api/checkout/url`

After the order is saved, the customer is redirected to the HTTPS checkout URL returned by hutko.

The callback URL is generated automatically:

`https://STORE/callback/webhooks.payment.hutko`

The store must accept HTTPS POST requests to this URL. The server callback is authoritative: an order is marked as paid only after the module validates the signature and confirms that the Merchant ID, order ID, amount, currency and payment status match the stored order.

The customer return is validated separately. It clears the osCommerce checkout state and redirects the customer to the order-success page, but it does not independently mark the order as paid.

Use different statuses for pending and paid orders. The pending status applies while the customer is completing payment; the paid status applies only after a valid approved callback.

## Local testing

End-to-end return testing requires HTTPS. A plain `http://localhost` return URL is not supported by the hosted checkout flow. Configure HTTPS in the local web server or use an HTTPS tunnel.

## Official test credentials

- Merchant ID: `1700002`
- Secret key: `test`
- Currency: `UAH`

There is no separate sandbox endpoint. Test payments use the standard hutko API endpoint. Replace the test credentials with the merchant's own Merchant ID and Secret key before production use.

See the [hutko documentation](https://docs.hutko.org/) for test-card details and API information.

## License

GNU General Public License v3.0 or later. See [LICENSE](LICENSE).

---

# Платіжний модуль hutko для osCommerce v4

Інтеграція платіжної сторінки [hutko](https://hutko.org/) з osCommerce v4.

## Можливості

- створення підписаної платіжної сесії через API hutko
- перевірка підписів і параметрів платіжних повідомлень
- перевірка Merchant ID, номера замовлення, суми, валюти та статусу платежу перед позначенням замовлення як оплаченого
- незалежна обробка серверних повідомлень і повернення покупця до магазину
- безпечна обробка повторних повідомлень без повторного виконання замовлення
- окремі налаштування статусів для очікування та успішної оплати
- обмеження способу оплати за платіжною зоною
- адаптивний логотип hutko та назва способу оплати на сторінці оформлення замовлення

## Вимоги

- osCommerce v4
- PHP 8.0 або новіша версія з модулями cURL і JSON
- сайт і адреси зворотних повідомлень із підтримкою HTTPS
- Merchant ID та Secret key hutko

## Встановлення

1. Скопіюйте вміст каталогу `upload/` до кореневого каталогу магазину osCommerce зі збереженням структури каталогів.
2. У панелі адміністратора osCommerce відкрийте платіжні модулі та встановіть **hutko**.
3. Укажіть Merchant ID та Secret key.
4. Виберіть різні статуси для замовлень, що очікують на оплату, та оплачених замовлень.
5. За потреби налаштуйте платіжну зону та порядок відображення.
6. У розділі **Restrictions** виберіть **Available for → Checkout** і натисніть **Update**.

Спосіб оплати не відображатиметься під час оформлення замовлення, доки не вибрано **Available for → Checkout**, навіть якщо параметр **Enable hutko** має значення `TRUE`.

## Обробка платежу

Модуль створює підписану платіжну сесію через:

`https://pay.hutko.org/api/checkout/url`

Після збереження замовлення покупець переходить на захищену платіжну сторінку за HTTPS-адресою, отриманою від hutko.

Адреса для зворотних повідомлень створюється автоматично:

`https://STORE/callback/webhooks.payment.hutko`

Магазин повинен приймати HTTPS POST-запити за цією адресою. Серверне повідомлення є основним джерелом статусу платежу: замовлення позначається як оплачене лише після перевірки підпису та відповідності Merchant ID, номера замовлення, суми, валюти й статусу платежу даним збереженого замовлення.

Повернення покупця перевіряється окремо. Воно очищає стан оформлення замовлення в osCommerce та перенаправляє покупця на сторінку успішного замовлення, але самостійно не позначає замовлення як оплачене.

Використовуйте різні статуси для очікування та успішної оплати. Статус очікування застосовується, поки покупець виконує оплату; статус оплати встановлюється лише після отримання коректного повідомлення про успішний платіж.

## Локальне тестування

Для повної перевірки повернення покупця потрібен HTTPS. Платіжна сторінка не підтримує повернення на звичайну адресу `http://localhost`. Налаштуйте HTTPS на локальному вебсервері або використовуйте HTTPS-тунель.

## Офіційні тестові дані

- Merchant ID: `1700002`
- Secret key: `test`
- Валюта: `UAH`

Окремого тестового API немає. Тестові платежі виконуються через стандартну адресу API hutko. Перед використанням у робочому магазині замініть тестові дані на власні Merchant ID та Secret key продавця.

Дані тестових карток і додаткову інформацію про API наведено в [документації hutko](https://docs.hutko.org/uk/).

## Ліцензія

GNU General Public License версії 3.0 або новішої. Дивіться файл [LICENSE](LICENSE).
