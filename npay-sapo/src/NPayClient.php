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

    private const BANK_ALIASES = [
        'vcb' => 'vietcombank', 'tcb' => 'techcombank', 'ctg' => 'vietinbank', 'icb' => 'vietinbank',
        'mb' => 'mbbank', 'vpb' => 'vpbank', 'tpb' => 'tpbank', 'stb' => 'sacombank',
        'hdb' => 'hdbank', 'eib' => 'eximbank', 'vba' => 'agribank', 'agr' => 'agribank',
        'lpb' => 'lienvietpostbank', 'lpbank' => 'lienvietpostbank', 'nab' => 'namabank',
        'abb' => 'abbank', 'bab' => 'bacabank', 'pvcb' => 'pvcombank', 'seab' => 'seabank',
        'klb' => 'kienlongbank', 'vab' => 'vietabank', 'sgicb' => 'saigonbank', 'bvb' => 'banviet',
    ];

    /**
     * Build the NPay QR image URL (gen-qr `/qrcard`) for a pending order.
     *
     * @param array $store Row from `stores` table.
     */
    public function buildQrUrl(array $store, string $refCode, float $amount): string
    {
        $base = (string)($this->config['npay_qr_endpoint'] ?? 'https://qr.npay.vn');
        $base = preg_replace('#/(img|qrpay|qrcard)/?$#', '', rtrim($base, '/'));
        $params = self::bankParam((string)($store['bank_code'] ?? '')) + [
            'tai_khoan' => (string)($store['account_number'] ?? ''),
            'so_tien'   => (string)(int)round($amount),
            'noi_dung'  => $refCode,
        ];
        $name = (string)($store['account_name'] ?? '');
        if ($name !== '') {
            $params['chu_tai_khoan'] = $name;
        }
        return $base . '/qrcard?' . http_build_query($params);
    }

    /**
     * gen-qr takes either a Napas BIN (`ma_bin`) or a vietnam-qr-pay key (`ngan_hang`).
     *
     * @return array<string, string>
     */
    public static function bankParam(string $bank): array
    {
        $bank = strtolower(preg_replace('/[\s_-]+/', '', trim($bank)));
        if (preg_match('/^\d{6}$/', $bank)) {
            return ['ma_bin' => $bank];
        }
        return ['ngan_hang' => self::BANK_ALIASES[$bank] ?? $bank];
    }

    /**
     * Reference code used in bank memo: NPAY-{order_id}.
     */
    public function refCode(string $sapoOrderId): string
    {
        return 'NPAY-' . preg_replace('/[^A-Za-z0-9]/', '', $sapoOrderId);
    }

    /**
     * Verify an NPay webhook.
     *
     * Accepts either `Authorization: Apikey <api_key>` (legacy `Bearer` still
     * accepted) or `X-Npay-Signature` = hex HMAC-SHA256 of the raw body keyed
     * by the webhook secret shown in the NPay dashboard. Nothing configured
     * means reject.
     */
    public function verifyWebhook(array $headers, string $rawBody, ?string $storeApiKey = null, ?string $webhookSecret = null): bool
    {
        $auth = (string)($headers['authorization'] ?? $headers['Authorization'] ?? '');
        if ($storeApiKey !== null && $storeApiKey !== ''
            && preg_match('/^(Apikey|Bearer)\s+(.+)$/i', trim($auth), $m)
            && hash_equals($storeApiKey, trim($m[2]))) {
            return true;
        }

        $secret = (string)($webhookSecret ?? '');
        if ($secret === '') {
            $secret = (string)($this->config['npay_webhook_secret'] ?? '');
        }
        $sig = strtolower(trim((string)($headers['x-npay-signature'] ?? $headers['X-Npay-Signature'] ?? '')));
        if ($sig === '' || $secret === '') {
            return false;
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        if (!hash_equals($expected, $sig)) {
            return false;
        }
        $ts = (string)($headers['x-npay-timestamp'] ?? $headers['X-Npay-Timestamp'] ?? '');
        return $ts === '' || (ctype_digit($ts) && abs(time() - (int)$ts) <= 300);
    }

    /**
     * Parse the typical NPay transaction webhook payload.
     */
    public function parseTransaction(array $body): array
    {
        return [
            'id'        => (string)($body['id']             ?? $body['transaction_id']  ?? ''),
            'type'      => strtolower((string)($body['transferType'] ?? 'in')),
            'code'      => (string)($body['code']           ?? ''),
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
