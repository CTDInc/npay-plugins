<?php
declare(strict_types=1);

namespace NPay\Sapo;

/**
 * Builds NPay QR URLs and verifies incoming NPay webhooks.
 */
class NPayClient
{
    private array $config;
    private Database $db;

    public function __construct(array $config, Database $db)
    {
        $this->config = $config;
        $this->db     = $db;
    }

    /**
     * Build the QR image URL (VietQR-style) for a pending order.
     *
     * @param array $store Row from `stores` table.
     */
    public function buildQrUrl(array $store, string $refCode, float $amount): string
    {
        $base = rtrim((string)($this->config['npay_qr_endpoint'] ?? 'https://qr.npay.vn/img'), '/');
        $params = [
            'bank'    => (string)($store['bank_code']      ?? ''),
            'account' => (string)($store['account_number'] ?? ''),
            'name'    => (string)($store['account_name']   ?? ''),
            'amount'  => (string)(int)round($amount),
            'memo'    => $refCode,
        ];
        return $base . '?' . http_build_query($params);
    }

    /**
     * Reference code used in bank memo: NPAY-{order_id}.
     */
    public function refCode(string $sapoOrderId): string
    {
        return 'NPAY-' . preg_replace('/[^A-Za-z0-9]/', '', $sapoOrderId);
    }

    /**
     * Verify NPay webhook signature.
     * Either: `Authorization: Bearer <api_key>` (per-store), or HMAC header.
     */
    public function verifyWebhook(array $headers, string $rawBody, ?string $storeApiKey = null): bool
    {
        $auth = $headers['authorization'] ?? $headers['Authorization'] ?? '';
        if ($storeApiKey && is_string($auth) && stripos($auth, 'Bearer ') === 0) {
            $token = trim(substr($auth, 7));
            if (hash_equals($storeApiKey, $token)) {
                return true;
            }
        }
        $sig = $headers['x-npay-signature'] ?? $headers['X-NPay-Signature'] ?? '';
        $secret = (string)($this->config['npay_webhook_secret'] ?? '');
        if ($sig === '' || $secret === '') {
            return false;
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, is_string($sig) ? $sig : '');
    }

    /**
     * Parse the typical NPay transaction webhook payload.
     */
    public function parseTransaction(array $body): array
    {
        return [
            'id'        => (string)($body['id']             ?? $body['transaction_id']  ?? ''),
            'amount'    => (float) ($body['amount']         ?? $body['transferAmount']  ?? 0),
            'content'   => (string)($body['content']        ?? $body['description']     ?? $body['memo'] ?? ''),
            'gateway'   => (string)($body['gateway']        ?? ''),
            'account'   => (string)($body['accountNumber']  ?? $body['account_number']  ?? ''),
            'paid_at'   => (string)($body['transactionDate']?? $body['paid_at']         ?? ''),
        ];
    }

    /**
     * Extract `NPAY-XXXX` reference from arbitrary memo text.
     */
    public function extractRefCode(string $content): ?string
    {
        if (preg_match('/NPAY[-_]?([A-Za-z0-9]+)/i', $content, $m)) {
            return 'NPAY-' . strtoupper($m[1]);
        }
        return null;
    }
}
