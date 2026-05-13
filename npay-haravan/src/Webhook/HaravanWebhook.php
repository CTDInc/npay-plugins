<?php

declare(strict_types=1);

namespace NPay\Haravan\Webhook;

use NPay\Haravan\Database;

/**
 * Handle Haravan webhook events.
 *
 * Verifies X-Haravan-Hmac-Sha256: base64(hmac_sha256(rawBody, client_secret))
 * Handles topic: orders/create
 */
class HaravanWebhook
{
    private array $config;
    private Database $db;

    public function __construct(array $config, Database $db)
    {
        $this->config = $config;
        $this->db    = $db;
    }

    public function handle(): void
    {
        $raw   = (string)file_get_contents('php://input');
        $hmac  = $this->header('X-Haravan-Hmac-Sha256');
        $topic = strtolower($this->header('X-Haravan-Topic'));
        $shop  = $this->header('X-Haravan-Shop-Domain');

        if (!$this->verify($raw, $hmac)) {
            http_response_code(401);
            echo 'invalid signature';
            return;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            http_response_code(400);
            echo 'invalid json';
            return;
        }

        if ($topic === 'orders/create') {
            $this->onOrderCreate($shop, $data);
        }

        http_response_code(200);
        echo 'ok';
    }

    private function onOrderCreate(string $shop, array $order): void
    {
        $orderId     = (string)($order['id'] ?? '');
        $orderNumber = (string)($order['order_number'] ?? $order['number'] ?? $orderId);
        $amount      = (float)($order['total_price'] ?? $order['total'] ?? 0);
        $currency    = (string)($order['currency'] ?? 'VND');
        $financial   = (string)($order['financial_status'] ?? '');

        if ($orderId === '' || $amount <= 0) {
            return;
        }
        if (in_array($financial, ['paid', 'partially_paid'], true)) {
            return;
        }
        if ($this->db->findPaymentByOrderId($orderId) !== null) {
            return; // already tracked
        }

        $prefix = $this->config['order_prefix'] ?? 'NPAY';
        $code   = $prefix . '-' . $orderNumber;

        $customer = $order['customer'] ?? [];
        $this->db->createPayment([
            ':shop'           => $shop,
            ':order_id'       => $orderId,
            ':order_number'   => $orderNumber,
            ':order_code'     => $code,
            ':amount'         => $amount,
            ':currency'       => $currency,
            ':customer_name'  => trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) ?: null,
            ':customer_email' => $customer['email'] ?? ($order['email'] ?? null),
            ':customer_phone' => $customer['phone'] ?? ($order['phone'] ?? null),
            ':status'         => 'pending',
            ':created_at'     => date('Y-m-d H:i:s'),
            ':updated_at'     => date('Y-m-d H:i:s'),
        ]);
    }

    private function verify(string $body, string $headerHmac): bool
    {
        $secret = (string)($this->config['haravan_client_secret'] ?? '');
        if ($secret === '' || $headerHmac === '') {
            return false;
        }
        $calc = base64_encode(hash_hmac('sha256', $body, $secret, true));
        return hash_equals($calc, $headerHmac);
    }

    private function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return (string)($_SERVER[$key] ?? '');
    }
}
