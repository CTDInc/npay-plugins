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
     * Verify NPay webhook signature.
     *
     * NPay sends "Authorization: Apikey <secret>" plus optional HMAC header.
     * We accept either a matching API key or a valid HMAC-SHA256 of the raw body.
     */
    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        $secret = (string)($this->cfg['webhook_secret'] ?? '');
        if ($secret === '') {
            return false;
        }

        // Normalize headers to lower-case keys.
        $h = [];
        foreach ($headers as $k => $v) {
            $h[strtolower($k)] = is_array($v) ? ($v[0] ?? '') : $v;
        }

        // Authorization: Apikey <secret>
        $auth = (string)($h['authorization'] ?? '');
        if (stripos($auth, 'apikey ') === 0) {
            $token = trim(substr($auth, 7));
            if (hash_equals($secret, $token)) {
                return true;
            }
        }

        // X-NPay-Signature: hex(hmac_sha256(body, secret))
        $sig = (string)($h['x-npay-signature'] ?? '');
        if ($sig !== '') {
            $expect = hash_hmac('sha256', $rawBody, $secret);
            if (hash_equals($expect, $sig)) {
                return true;
            }
        }

        return false;
    }
}
