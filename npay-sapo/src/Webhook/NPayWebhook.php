<?php
declare(strict_types=1);

namespace NPay\Sapo\Webhook;

use NPay\Sapo\Database;
use NPay\Sapo\NPayClient;
use NPay\Sapo\SapoClient;

/**
 * NPay bank-transaction webhook handler.
 * Matches transactions to pending orders via `NPAY-{order_id}` reference.
 */
class NPayWebhook
{
    private array $config;
    private Database $db;
    private SapoClient $sapo;
    private NPayClient $npay;

    public function __construct(array $config, Database $db, SapoClient $sapo)
    {
        $this->config = $config;
        $this->db     = $db;
        $this->sapo   = $sapo;
        $this->npay   = new NPayClient($config, $db);
    }

    public function handle(): void
    {
        $raw     = file_get_contents('php://input') ?: '';
        $payload = json_decode($raw, true) ?: [];
        $headers = $this->collectHeaders();

        $this->db->logWebhook('npay', $payload['event'] ?? null, $payload);

        $tx  = $this->npay->parseTransaction($payload);
        $ref = $this->npay->extractRefCode($tx['content']);
        if (!$ref) {
            $this->respond(200, ['ok' => false, 'reason' => 'no ref code in content']);
            return;
        }

        $order = $this->db->findOrderByRef($ref);
        if (!$order) {
            $this->respond(200, ['ok' => false, 'reason' => 'order not found', 'ref' => $ref]);
            return;
        }

        $store = $this->db->findStore((int)$order['sapo_store_id']);
        if (!$store) {
            $this->respond(200, ['ok' => false, 'reason' => 'store missing']);
            return;
        }

        // Per-store NPay token authentication — fail closed: an unset secret
        // must reject, never accept an unverified webhook.
        $apiKey = (string)($store['api_key'] ?? '');
        if ($apiKey === '' || !$this->npay->verifyWebhook($headers, $raw, $apiKey)) {
            $this->respond(401, ['error' => 'invalid signature']);
            return;
        }

        // Already paid? idempotent.
        if ($order['status'] === 'paid') {
            $this->respond(200, ['ok' => true, 'already_paid' => true]);
            return;
        }

        // Amount check.
        if ($tx['amount'] > 0 && (float)$order['amount'] > 0
            && abs($tx['amount'] - (float)$order['amount']) > 0.01) {
            $this->respond(200, [
                'ok'       => false,
                'reason'   => 'amount mismatch',
                'expected' => $order['amount'],
                'got'      => $tx['amount'],
            ]);
            return;
        }

        // Mark paid locally + push to Sapo.
        $this->db->markOrderPaid((int)$order['id']);
        try {
            $this->sapo->createTransaction(
                $store,
                (string)$order['sapo_order_id'],
                (float)$order['amount'],
                'NPay - ' . $ref
            );
        } catch (\Throwable $e) {
            // Fallback: PUT order with financial_status=paid.
            try {
                $this->sapo->markPaid($store, (string)$order['sapo_order_id']);
            } catch (\Throwable $e2) {
                error_log('[npay-sapo] markPaid failed: ' . $e2->getMessage());
            }
        }

        $this->respond(200, ['ok' => true, 'ref' => $ref]);
    }

    /** @return array<string, string> */
    private function collectHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (strpos($k, 'HTTP_') === 0) {
                $name = strtolower(str_replace('_', '-', substr($k, 5)));
                $headers[$name] = (string)$v;
            }
        }
        return $headers;
    }

    private function respond(int $status, array $body): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($body);
    }
}
