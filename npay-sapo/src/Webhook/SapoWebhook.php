<?php
declare(strict_types=1);

namespace NPay\Sapo\Webhook;

use NPay\Sapo\Database;
use NPay\Sapo\NPayClient;
use NPay\Sapo\SapoClient;

/**
 * Sapo → NPay webhook handler.
 * Topics: order/created, order/updated, order/paid.
 */
class SapoWebhook
{
    private array $config;
    private Database $db;
    private SapoClient $sapo;
    private NPayClient $npay;

    public function __construct(array $config, Database $db, SapoClient $sapo, NPayClient $npay)
    {
        $this->config = $config;
        $this->db     = $db;
        $this->sapo   = $sapo;
        $this->npay   = $npay;
    }

    public function handle(): void
    {
        $raw   = file_get_contents('php://input') ?: '';
        $topic = $_SERVER['HTTP_X_SAPO_TOPIC']        ?? '';
        $shop  = $_SERVER['HTTP_X_SAPO_SHOP_DOMAIN']  ?? '';
        $sig   = $_SERVER['HTTP_X_SAPO_HMAC_SHA256']  ?? '';

        $store = $shop ? $this->db->findStoreByDomain((string)$shop) : null;
        if (!$store) {
            $this->respond(404, ['error' => 'unknown store', 'shop' => $shop]);
            return;
        }

        // Verify HMAC if store has a configured secret.
        $secret = (string)($store['webhook_secret'] ?? '');
        if ($secret !== '' && !$this->sapo->verifyWebhook($raw, (string)$sig, $secret)) {
            $this->respond(401, ['error' => 'invalid signature']);
            return;
        }

        $payload = json_decode($raw, true) ?: [];
        $this->db->logWebhook('sapo', (string)$topic, $payload);

        switch ($topic) {
            case 'order/created':
                $this->onOrderCreated($store, $payload);
                break;
            case 'order/updated':
                $this->onOrderUpdated($store, $payload);
                break;
            case 'order/cancelled':
            case 'order/canceled':
                $this->onOrderCancelled($store, $payload);
                break;
            default:
                // ignore other topics
                break;
        }

        $this->respond(200, ['ok' => true]);
    }

    private function onOrderCreated(array $store, array $order): void
    {
        $sapoOrderId = (string)($order['id'] ?? '');
        if ($sapoOrderId === '') {
            return;
        }

        // Skip if already paid in Sapo.
        if (($order['financial_status'] ?? '') === 'paid') {
            return;
        }

        // Idempotent.
        if ($this->db->findOrderBySapoId((int)$store['id'], $sapoOrderId)) {
            return;
        }

        $amount = (float)($order['total_price'] ?? $order['total'] ?? 0);
        $ref    = $this->npay->refCode($sapoOrderId);
        $this->db->createOrder((int)$store['id'], $sapoOrderId, $ref, $amount, $order);
    }

    private function onOrderUpdated(array $store, array $order): void
    {
        $sapoOrderId = (string)($order['id'] ?? '');
        if ($sapoOrderId === '') {
            return;
        }
        $row = $this->db->findOrderBySapoId((int)$store['id'], $sapoOrderId);
        if (!$row) {
            // Treat as new order.
            $this->onOrderCreated($store, $order);
            return;
        }
        if (($order['financial_status'] ?? '') === 'paid' && $row['status'] !== 'paid') {
            $this->db->markOrderPaid((int)$row['id']);
        }
    }

    private function onOrderCancelled(array $store, array $order): void
    {
        $sapoOrderId = (string)($order['id'] ?? '');
        $row = $this->db->findOrderBySapoId((int)$store['id'], $sapoOrderId);
        if ($row && $row['status'] !== 'paid') {
            $this->db->setOrderStatus((int)$row['id'], 'cancelled');
        }
    }

    private function respond(int $status, array $body): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($body);
    }
}
