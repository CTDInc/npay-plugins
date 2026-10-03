<?php

declare(strict_types=1);

namespace NPay\Haravan;

/**
 * Builds VietQR / NPay payment URLs and verifies NPay webhook signatures.
 */
class NPayClient
{
    private array $cfg;

    public function __construct(array $config)
    {
        $this->cfg = $config['npay'] ?? [];
    }

    /**
     * Build a QR image URL using VietQR.io template.
     */
    public function buildQrUrl(string $code, float $amount): string
    {
        $bank  = $this->cfg['bank_id']        ?? '';
        $acc   = $this->cfg['account_number'] ?? '';
        $tpl   = $this->cfg['qr_template']    ?? 'compact2';
        $name  = $this->cfg['account_name']   ?? '';

        $params = http_build_query([
            'amount'      => (int)round($amount),
            'addInfo'     => $code,
            'accountName' => $name,
        ]);
        return sprintf('https://img.vietqr.io/image/%s-%s-%s.png?%s',
            rawurlencode($bank),
            rawurlencode($acc),
            rawurlencode($tpl),
            $params
        );
    }

    public function bankInfo(): array
    {
        return [
            'bank_id'        => $this->cfg['bank_id']        ?? '',
            'account_number' => $this->cfg['account_number'] ?? '',
            'account_name'   => $this->cfg['account_name']   ?? '',
        ];
    }

    /**
     * Verify an NPay webhook. Passes when EITHER:
     *  - `Authorization: Apikey <api_key>` matches `npay.api_key` (falls back to
     *    `npay.webhook_secret` for configs written before 1.1.0), or
     *  - `X-Npay-Signature` = hex HMAC-SHA256(raw body, `npay.webhook_secret`),
     *    with `X-Npay-Timestamp` (if sent) no older than 5 minutes.
     */
    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        $secret = (string)($this->cfg['webhook_secret'] ?? '');
        $apiKey = (string)($this->cfg['api_key'] ?? '');
        if ($apiKey === '') {
            $apiKey = $secret;
        }

        $h = [];
        foreach ($headers as $k => $v) {
            $h[strtolower((string)$k)] = is_array($v) ? ($v[0] ?? '') : (string)$v;
        }

        $auth = trim((string)($h['authorization'] ?? ''));
        if ($apiKey !== '' && preg_match('/^Apikey\s+(.+)$/i', $auth, $m) && hash_equals($apiKey, trim($m[1]))) {
            return true;
        }

        $sig = strtolower(trim((string)($h['x-npay-signature'] ?? '')));
        if ($secret === '' || $sig === '' || !hash_equals(hash_hmac('sha256', $rawBody, $secret), $sig)) {
            return false;
        }
        $ts = trim((string)($h['x-npay-timestamp'] ?? ''));
        return $ts === '' || (ctype_digit($ts) && abs(time() - (int)$ts) <= 300);
    }
}
