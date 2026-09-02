<?php

/** hutko hosted checkout payment module for osCommerce v4. */
namespace common\modules\orderPayment;

require_once __DIR__ . '/hutko/HutkoApi.php';

use common\classes\modules\ModulePayment;
use common\classes\modules\ModuleSortOrder;
use common\classes\modules\ModuleStatus;
use common\modules\orderPayment\hutko\HutkoApi;

class hutko extends ModulePayment
{
    public const VERSION = '1.0.0';
    public $code = 'hutko';
    public $title;
    public $public_title;
    public $description;
    public $enabled = false;
    public $sort_order = 0;
    public $order_status = 0;
    public $paid_status = 0;
    public $dont_send_email = true;
    protected $encrypted_keys = ['MODULE_PAYMENT_HUTKO_SECRET_KEY'];
    protected $defaultTranslationArray = [
        'MODULE_PAYMENT_HUTKO_TEXT_TITLE' => 'hutko',
        'MODULE_PAYMENT_HUTKO_TEXT_PUBLIC_TITLE' => 'Pay securely with hutko',
        'MODULE_PAYMENT_HUTKO_TEXT_DESCRIPTION' => 'Accept payments with hutko hosted checkout.',
        'MODULE_PAYMENT_HUTKO_ERROR_TITLE' => 'Payment could not be processed',
        'MODULE_PAYMENT_HUTKO_ERROR_GENERAL' => 'Please try again or choose another payment method.',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->title = defined('MODULE_PAYMENT_HUTKO_TEXT_TITLE') ? MODULE_PAYMENT_HUTKO_TEXT_TITLE : 'hutko';
        $this->public_title = defined('MODULE_PAYMENT_HUTKO_TEXT_PUBLIC_TITLE') ? MODULE_PAYMENT_HUTKO_TEXT_PUBLIC_TITLE : 'Pay securely with hutko';
        $this->description = defined('MODULE_PAYMENT_HUTKO_TEXT_DESCRIPTION') ? MODULE_PAYMENT_HUTKO_TEXT_DESCRIPTION : 'hutko hosted checkout';
        $this->sort_order = defined('MODULE_PAYMENT_HUTKO_SORT_ORDER') ? (int) MODULE_PAYMENT_HUTKO_SORT_ORDER : 0;
        $this->enabled = defined('MODULE_PAYMENT_HUTKO_STATUS') && MODULE_PAYMENT_HUTKO_STATUS === 'True';
        $this->order_status = $this->statusValue('MODULE_PAYMENT_HUTKO_PENDING_ORDER_STATUS_ID');
        $this->paid_status = $this->statusValue('MODULE_PAYMENT_HUTKO_PAID_ORDER_STATUS_ID');
        if ($this->enabled && !$this->isConfigured()) {
            $this->enabled = false;
        }
        if ($this->enabled) {
            $this->update_status();
        }
    }

    public function selection(): array
    {
        \Yii::$app->getView()->registerCss(
            '.hutko-payment-option{display:inline-flex;align-items:center;gap:.65em;max-width:100%;vertical-align:middle}'
            . '.hutko-payment-option__logo{display:block;width:clamp(84px,18vw,128px);max-width:42%;height:auto;flex:0 1 auto}'
            . '.hutko-payment-option__text{min-width:0;line-height:1.35;white-space:normal}'
            . '@media(max-width:480px){.hutko-payment-option{gap:.5em}.hutko-payment-option__logo{width:96px;max-width:38%}}'
        );

        $title = htmlspecialchars((string) $this->public_title, ENT_QUOTES, 'UTF-8');
        $logo = htmlspecialchars(DIR_WS_IMAGES . 'payment/hutko.png', ENT_QUOTES, 'UTF-8');

        return [
            'id' => $this->code,
            'module' => '<span class="hutko-payment-option">'
                . '<img class="hutko-payment-option__logo" src="' . $logo . '" alt="hutko">'
                . '<span class="hutko-payment-option__text">' . $title . '</span>'
                . '</span>',
        ];
    }
    public function javascript_validation() { return false; }
    public function pre_confirmation_check(): void {}
    public function confirmation() { return false; }
    public function process_button() { return false; }

    public function before_process(): void
    {
        $order = $this->manager->getOrderInstance();
        $order->info['order_status'] = $this->order_status ?: (int) DEFAULT_ORDERS_STATUS_ID;
        $order->isPaidUpdated = true;
    }

    public function after_process(): void
    {
        $order = $this->manager->getOrderInstance();
        $orderId = (int) $order->order_id;
        $amount = $this->minorAmount($order);
        $currency = strtoupper((string) $order->info['currency']);
        if ($orderId < 1 || $amount < 1 || !preg_match('/^[A-Z]{3}$/', $currency)) {
            $this->paymentError();
        }
        try {
            $parameters = [
                'order_id' => $orderId . '#' . time(),
                'order_desc' => $this->orderDescription($orderId),
                'amount' => $amount,
                'currency' => $currency,
                'sender_email' => (string) ($order->customer['email_address'] ?? ''),
                'lang' => substr(strtolower((string) ($_SESSION['language'] ?? 'en')), 0, 2),
                'response_url' => tep_href_link('callback/webhooks.payment.hutko', 'action=return', 'SSL'),
                'server_callback_url' => tep_href_link('callback/webhooks.payment.hutko', '', 'SSL'),
            ];
            $checkoutUrl = $this->api()->getCheckoutUrl($parameters);
        } catch (\Throwable $exception) {
            $this->paymentError();
        }
        tep_redirect($checkoutUrl);
    }

    public function call_webhooks(): void
    {
        $request = \Yii::$app->request;
        $action = (string) $request->get('action', '');
        $isCustomerReturn = $action === 'return';
        if (!$request->isPost) {
            if ($isCustomerReturn) {
                $this->paymentError();
            }
            $this->respond(405, 'Method Not Allowed');
        }
        try {
            $callback = $this->callbackParameters();
            $this->api()->validateCallback($callback);
            if ($isCustomerReturn) {
                if (!$this->isApprovedPurchase($callback)) {
                    $this->paymentError();
                }
                $orderId = $this->orderIdFromCallback($callback);
                $this->manager->clearAfterProcess();
                tep_redirect(tep_href_link(FILENAME_CHECKOUT_SUCCESS, 'orders_id=' . $orderId, 'SSL'));
            }
            $this->processCallback($callback);
            $this->respond(200, 'OK');
        } catch (\InvalidArgumentException $exception) {
            if ($isCustomerReturn) $this->paymentError();
            $this->respond(400, 'Invalid callback');
        } catch (\UnexpectedValueException $exception) {
            if ($isCustomerReturn) $this->paymentError();
            $this->respond(409, 'Payment mismatch');
        } catch (\Throwable $exception) {
            if ($isCustomerReturn) $this->paymentError();
            $this->respond(500, 'Callback processing failed');
        }
    }

    private function processCallback(array $callback): void
    {
        if (!$this->isApprovedPurchase($callback)) {
            return;
        }
        if (strtolower((string) ($callback['response_status'] ?? '')) !== 'success'
            || !isset($callback['payment_id']) || !ctype_digit((string) $callback['payment_id'])) {
            throw new \UnexpectedValueException('Invalid approved payment.');
        }
        $orderId = $this->orderIdFromCallback($callback);
        $order = $this->manager->getOrderInstanceWithId('\common\classes\Order', $orderId);
        if (!$order || (int) $order->order_id !== $orderId) {
            throw new \UnexpectedValueException('Order not found.');
        }
        $amount = (string) ($callback['amount'] ?? '');
        if (!ctype_digit($amount) || (int) $amount !== $this->minorAmount($order)
            || !hash_equals(strtoupper((string) $order->info['currency']), strtoupper((string) ($callback['currency'] ?? '')))) {
            throw new \UnexpectedValueException('Payment amount or currency does not match.');
        }
        if ($this->paymentAlreadyRecorded($orderId, (string) $callback['payment_id'])) {
            return;
        }
        \common\helpers\Order::setStatus($orderId, $this->paid_status, [
            'comments' => 'hutko payment ID: ' . (string) $callback['payment_id'],
            'customer_notified' => 0,
        ]);
        $order->info['order_status'] = $this->paid_status;
        $order->update_piad_information(true);
        $order->save_details();
        $order->notify_customer($order->getProductsHtmlForEmail(), []);
    }

    private function paymentAlreadyRecorded(int $orderId, string $paymentId): bool
    {
        $comment = 'hutko payment ID: ' . preg_replace('/[^0-9]/', '', $paymentId);
        $query = tep_db_query(
            'select orders_id from ' . TABLE_ORDERS_STATUS_HISTORY
            . " where orders_id = '" . $orderId . "'"
            . " and comments = '" . tep_db_input($comment) . "' limit 1"
        );
        return tep_db_num_rows($query) > 0;
    }

    private function callbackParameters(): array
    {
        if (str_contains(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            $decoded = isset($decoded['response']) && is_array($decoded['response']) ? $decoded['response'] : $decoded;
            if (!is_array($decoded) || $decoded === []) throw new \InvalidArgumentException('Invalid callback.');
            return $decoded;
        }
        if (!is_array($_POST) || $_POST === []) throw new \InvalidArgumentException('Empty callback.');
        return $_POST;
    }

    private function orderIdFromCallback(array $callback): int
    {
        if (!preg_match('/^(\d+)#\d+$/', (string) ($callback['order_id'] ?? ''), $matches)) {
            throw new \UnexpectedValueException('Invalid order ID.');
        }
        return (int) $matches[1];
    }

    private function isApprovedPurchase(array $callback): bool
    {
        return strtolower((string) ($callback['order_status'] ?? '')) === 'approved'
            && strtolower((string) ($callback['tran_type'] ?? '')) === 'purchase'
            && strtolower((string) ($callback['response_status'] ?? '')) === 'success';
    }

    private function minorAmount($order): int
    {
        $currencies = \Yii::$container->get('currencies');
        $currency = strtoupper((string) $order->info['currency']);
        $decimals = isset($currencies->currencies[$currency]['decimal_places']) ? (int) $currencies->currencies[$currency]['decimal_places'] : 2;
        $value = (string) $currencies->format_clear($order->info['total_inc_tax'], true, $currency);
        if (!preg_match('/^(\d+)(?:\.(\d+))?$/', $value, $matches)) throw new \UnexpectedValueException('Invalid amount.');
        return (int) ($matches[1] . str_pad(substr($matches[2] ?? '', 0, $decimals), $decimals, '0'));
    }

    private function orderDescription(int $orderId): string
    {
        $text = (defined('STORE_NAME') ? strip_tags((string) STORE_NAME) : 'osCommerce') . ' - order #' . $orderId;
        return function_exists('mb_substr') ? mb_substr($text, 0, 1024, 'UTF-8') : substr($text, 0, 1024);
    }

    private function api(): HutkoApi { return new HutkoApi($this->merchantId(), $this->secret()); }
    private function merchantId(): string { return defined('MODULE_PAYMENT_HUTKO_MERCHANT_ID') ? trim((string) MODULE_PAYMENT_HUTKO_MERCHANT_ID) : ''; }
    private function secret(): string { return trim((string) $this->decryptConst('MODULE_PAYMENT_HUTKO_SECRET_KEY')); }
    private function isConfigured(): bool { return preg_match('/^\d{1,12}$/', $this->merchantId()) === 1 && $this->secret() !== ''; }
    private function statusValue(string $key): int { return defined($key) ? (int) constant($key) : 0; }

    private function paymentError(): void
    {
        tep_redirect($this->getCheckoutUrl(['payment_error' => $this->code], self::PAYMENT_PAGE));
        exit;
    }

    private function respond(int $status, string $body): void
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        echo $body;
        exit;
    }

    public function get_error(): array
    {
        return ['title' => 'Payment could not be processed', 'error' => 'Please try again or choose another payment method.'];
    }

    public function update_status(): void
    {
        if (!$this->enabled || !defined('MODULE_PAYMENT_HUTKO_ZONE') || (int) MODULE_PAYMENT_HUTKO_ZONE < 1) return;
        $countryId = (int) ($this->billing['country']['id'] ?? 0);
        $zoneId = (int) ($this->billing['zone_id'] ?? 0);
        $allowed = false;
        $query = tep_db_query('select zone_id from ' . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . (int) MODULE_PAYMENT_HUTKO_ZONE . "' and zone_country_id = '" . $countryId . "' order by zone_id");
        while ($row = tep_db_fetch_array($query)) {
            if ((int) $row['zone_id'] < 1 || (int) $row['zone_id'] === $zoneId) { $allowed = true; break; }
        }
        $this->enabled = $allowed;
    }

    public function configure_keys(): array
    {
        $default = $this->getDefaultOrderStatusId();
        $paidDefault = (int) ($this->paidOrderStatus() ?: $default);
        return [
            'MODULE_PAYMENT_HUTKO_STATUS' => ['title' => 'Enable hutko', 'description' => 'Accept payments through hutko hosted checkout.', 'value' => 'False', 'set_function' => "tep_cfg_select_option(array('True', 'False'), "],
            'MODULE_PAYMENT_HUTKO_MERCHANT_ID' => ['title' => 'Merchant ID', 'description' => 'Merchant ID from the hutko portal.', 'value' => ''],
            'MODULE_PAYMENT_HUTKO_SECRET_KEY' => ['title' => 'Secret key', 'description' => 'Secret key from the hutko portal.', 'value' => '', 'set_function' => 'setConf(', 'use_function' => '\common\modules\orderPayment\hutko::useConf'],
            'MODULE_PAYMENT_HUTKO_PENDING_ORDER_STATUS_ID' => $this->statusConfig('Pending order status', $default),
            'MODULE_PAYMENT_HUTKO_PAID_ORDER_STATUS_ID' => $this->statusConfig('Paid order status', $paidDefault),
            'MODULE_PAYMENT_HUTKO_ZONE' => ['title' => 'Payment zone', 'description' => 'Choose none to allow all zones.', 'value' => '0', 'use_function' => '\common\helpers\Zones::get_zone_class_title', 'set_function' => 'tep_cfg_pull_down_zone_classes('],
            'MODULE_PAYMENT_HUTKO_SORT_ORDER' => ['title' => 'Display order', 'description' => 'Lower values are displayed first.', 'value' => '0'],
        ];
    }

    private function statusConfig(string $title, int $value): array
    {
        return ['title' => $title, 'description' => $title . ' for hutko payments.', 'value' => $value, 'set_function' => 'tep_cfg_pull_down_order_statuses(', 'use_function' => '\common\helpers\Order::get_order_status_name'];
    }

    public function describe_status_key(): ModuleStatus { return new ModuleStatus('MODULE_PAYMENT_HUTKO_STATUS', 'True', 'False'); }
    public function describe_sort_key(): ModuleSortOrder { return new ModuleSortOrder('MODULE_PAYMENT_HUTKO_SORT_ORDER'); }
    public function isOnline(): bool { return true; }
}
