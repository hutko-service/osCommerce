<?php

namespace common\modules\orderPayment\hutko;

final class HutkoApi
{
    private const API_URL = 'https://pay.hutko.org/api/';
    private string $merchantId;
    private string $secretKey;

    public function __construct(string $merchantId, string $secretKey)
    {
        if (!preg_match('/^\d{1,12}$/', $merchantId) || $secretKey === '') {
            throw new \InvalidArgumentException('Invalid hutko credentials.');
        }
        $this->merchantId = $merchantId;
        $this->secretKey = $secretKey;
    }

    public function validateCallback(array $parameters): void
    {
        if (!isset($parameters['merchant_id']) || (int) $parameters['merchant_id'] !== (int) $this->merchantId) {
            throw new \InvalidArgumentException('Merchant data is incorrect.');
        }
        $signature = $parameters['signature'] ?? null;
        if (!is_string($signature) || !preg_match('/^[a-f0-9]{40}$/i', $signature)) {
            throw new \InvalidArgumentException('Signature is not valid.');
        }
        unset($parameters['signature'], $parameters['response_signature_string']);
        if (!hash_equals(self::getSignature($parameters, $this->secretKey), strtolower($signature))) {
            throw new \InvalidArgumentException('Signature is not valid.');
        }
    }

    public function getCheckoutUrl(array $parameters): string
    {
        $parameters['merchant_id'] = $this->merchantId;
        $parameters['signature'] = self::getSignature($parameters, $this->secretKey);

        $payload = json_encode(['request' => $parameters], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($payload)) {
            throw new \UnexpectedValueException('Could not encode hutko request.');
        }

        $handle = curl_init(self::API_URL . 'checkout/url');
        if ($handle === false) {
            throw new \RuntimeException('Could not initialize hutko request.');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=UTF-8'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 70,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if (!is_string($body) || $body === '' || $error !== '') {
            throw new \RuntimeException('hutko API connection failed.');
        }
        if ($status !== 200) {
            throw new \RuntimeException('hutko API returned HTTP ' . $status . '.');
        }

        $decoded = json_decode($body, true);
        $response = is_array($decoded) && isset($decoded['response']) && is_array($decoded['response'])
            ? $decoded['response']
            : null;
        if ($response === null || ($response['response_status'] ?? '') !== 'success') {
            throw new \UnexpectedValueException('hutko rejected the checkout request.');
        }

        $checkoutUrl = $response['checkout_url'] ?? '';
        if (!is_string($checkoutUrl)
            || !preg_match('~^https://(?:[a-z0-9-]+\.)*hutko\.org(?:/|$)~i', $checkoutUrl)) {
            throw new \UnexpectedValueException('hutko returned an invalid checkout URL.');
        }

        return $checkoutUrl;
    }

    public static function getSignature(array $parameters, string $secretKey): string
    {
        unset($parameters['signature'], $parameters['response_signature_string']);
        $parameters = array_filter($parameters, static fn ($value): bool => $value !== '' && $value !== null);
        ksort($parameters);
        return sha1($secretKey . '|' . implode('|', array_values($parameters)));
    }
}
