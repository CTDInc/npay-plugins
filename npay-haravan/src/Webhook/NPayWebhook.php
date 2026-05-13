<?php

declare(strict_types=1);

namespace NPay\Haravan\Webhook;

use NPay\Haravan\Database;
use NPay\Haravan\HaravanClient;
use NPay\Haravan\NPayClient;

/**
 * Handle incoming NPay transaction webhook.
 *
 * Expected JSON (subset of SePay/NPay common format):
 *   {
 *     "id":           "<npay_txn_id>",
 *     "transferType": "in",
 *     "transferAmount": 150000,
 *     "content":      "NPAY-1001",   // contains order_code
 *     "code":         "NPAY-1001",   // optional explicit code
 *     ...
 *   }
 */
class NPayWebhook
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
        $raw = (string)file_get_contents('php://input');

        $headers = function_exists('getallheaders') ? getallheaders() : [];
        if (!$headers) {
            // fallback: build from $_SERVER
            foreach ($_SERVER as $k => $v) {
                if (str_starts_with((string)$k, 'HTTP_')) {
                    $name = str_replace('_', '-', strtolower(substr((string)$k, 5)));
                    $headers[$name] = $v;
                }
            }
        }

        $npay = new NPayClient($this->config);
        if (!$npay->verifyWebhook($raw, $headers)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'invalid signature']);
            return;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'invalid json']);
            return;
        }

        $type = strtolower((string)($data['transferType'] ?? 'in'));
        if ($type !== 'in') {
            http_response_code(200);
            echo json_encode(['success' => true, 'ignored' => 'not incoming']);
            return;
        }

        $amount  = (float)($data['transferAmount'] ?? $data['amount'] ?? 0);
        $content = (string)($data['content'] ?? '');
        $code    = (string)($data['code'] ?? '');
        $txnId   = (string)($data['id'] ?? $data['referenceCode'] ?? '');

        $orderCode = $this->extractCode($code !== '' ? $code : $content);
        if ($orderCode === '') {
            http_response_code(200);
            echo json_encode(['success' => true, 'ignored' => 'no order code']);
            return;
        }

        $payment = $this->db->findPaymentByCode($orderCode);
        if (!$payment) {
            http_response_code(200);
            echo json_encode(['success' => true, 'ignored' => 'order not found', 'code' => $orderCode]);
            return;
        }

        if ($payment['status'] === 'paid') {
            http_response_code(200);
            echo json_encode(['success' => true, 'ignored' => 'already paid']);
            return;
        }

        if ($amount + 0.5 < (float)$payment['amount']) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'ignored' => 'underpaid',
                'expected' => $payment['amount'],
                'received' => $amount,
            ]);
            return;
        }

        // Mark Haravan order paid via transactions API.
        $shopRow = $this->db->findShop((string)$payment['shop']);
        if (!$shopRow) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'shop not installed']);
            return;
        }

        $client = new HaravanClient($this->config);
        try {
            $client->createTransaction(
                (string)$payment['shop'],
                (string)$shopRow['access_token'],
                (string)$payment['order_id'],
                (float)$payment['amount'],
                (string)($payment['currency'] ?? 'VND'),
                'capture',
                'success',
                'NPay',
                'NPay txn ' . $txnId
            );
        } catch (\Throwable $e) {
            error_log('[npay-haravan] createTransaction error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            return;
        }

        $this->db->markPaid((int)$payment['id'], $txnId);

        http_response_code(200);
        echo json_encode(['success' => true, 'order_code' => $orderCode]);
    }

    private function extractCode(string $text): string
    {
        $prefix = (string)($this->config['order_prefix'] ?? 'NPAY');
        if ($text === '') {
            return '';
        }
        if (preg_match('/' . preg_quote($prefix, '/') . '[\-_ ]?([A-Za-z0-9]+)/i', $text, $m)) {
            return strtoupper($prefix) . '-' . $m[1];
        }
        return '';
    }
}
