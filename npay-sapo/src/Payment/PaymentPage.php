<?php
declare(strict_types=1);

namespace NPay\Sapo\Payment;

use NPay\Sapo\Database;
use NPay\Sapo\NPayClient;

/**
 * Renders the customer-facing QR payment page + JSON status endpoint.
 */
class PaymentPage
{
    private array $config;
    private Database $db;
    private NPayClient $npay;

    public function __construct(array $config, Database $db, NPayClient $npay)
    {
        $this->config = $config;
        $this->db     = $db;
        $this->npay   = $npay;
    }

    public function render(string $sapoOrderId): void
    {
        $order = $this->loadOrder($sapoOrderId);
        if (!$order) {
            http_response_code(404);
            echo 'Không tìm thấy đơn hàng.';
            return;
        }
        $store = $this->db->findStore((int)$order['sapo_store_id']);
        if (!$store) {
            http_response_code(404);
            echo 'Cửa hàng không khả dụng.';
            return;
        }

        $qrUrl    = $this->npay->buildQrUrl($store, (string)$order['ref_code'], (float)$order['amount']);
        $ttl      = (int)($this->config['payment_ttl'] ?? 900);
        $appUrl   = rtrim((string)($this->config['app_url'] ?? ''), '/');
        $statusUrl = $appUrl . '/status/' . urlencode((string)$order['sapo_order_id']);

        include dirname(__DIR__, 2) . '/templates/payment.php';
    }

    public function status(string $sapoOrderId): array
    {
        $order = $this->loadOrder($sapoOrderId);
        if (!$order) {
            return ['ok' => false, 'status' => 'not_found'];
        }
        return [
            'ok'      => true,
            'status'  => $order['status'],
            'paid_at' => $order['paid_at'],
            'amount'  => (float)$order['amount'],
            'ref'     => $order['ref_code'],
        ];
    }

    private function loadOrder(string $sapoOrderId): ?array
    {
        // Allow lookup either by numeric local id or by sapo_order_id.
        if (ctype_digit($sapoOrderId)) {
            $byId = $this->db->findOrderById((int)$sapoOrderId);
            if ($byId) {
                return $byId;
            }
        }
        // Search across stores by sapo_order_id.
        $stmt = $this->db->pdo()->prepare(
            'SELECT * FROM orders WHERE sapo_order_id = :o ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([':o' => $sapoOrderId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
