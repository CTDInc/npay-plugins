<?php
/**
 * NPayClient — helpers around qr.npay.vn QR generation and webhook verification.
 */

class NPayClient
{
    /** @var array */
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Build a qr.npay.vn image URL.
     *
     * Format: https://qr.npay.vn/img?acc=ACCOUNT&bank=BIN&amount=AMOUNT&des=CONTENT&template=TEMPLATE
     */
    public function buildQrUrl(string $refCode, int $amount): string
    {
        $base = rtrim($this->config['npay_qr_base'] ?? 'https://qr.npay.vn', '/');
        $params = [
            'acc'         => $this->config['account_number'] ?? '',
            'bank'        => $this->config['bank_bin'] ?? '',
            'amount'      => $amount,
            'des'         => $refCode,
            'template'    => $this->config['qr_template'] ?? 'compact',
            'accountName' => $this->config['account_holder'] ?? '',
        ];
        return $base . '/img?' . http_build_query($params);
    }

    /**
     * Build a deeplink/link to my.npay.vn for the customer to open NPay app.
     */
    public function buildPayLink(string $refCode, int $amount): string
    {
        $base = rtrim($this->config['npay_my_base'] ?? 'https://my.npay.vn', '/');
        $params = [
            'acc'    => $this->config['account_number'] ?? '',
            'bank'   => $this->config['bank_bin'] ?? '',
            'amount' => $amount,
            'des'    => $refCode,
        ];
        return $base . '/pay?' . http_build_query($params);
    }

    /**
     * Generate a fresh reference code based on a numeric id.
     */
    public function makeRefCode(int $id): string
    {
        $prefix = $this->config['ref_prefix'] ?? 'NP';
        return $prefix . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verify the NPay webhook request.
     *
     * Accepts EITHER:
     *   - Authorization: Bearer <api_token>
     *   - X-NPay-Signature: hex( hmac_sha256(raw_body, api_token) )
     */
    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        $token = $this->config['api_token'] ?? '';
        if ($token === '' || $token === 'CHANGE_ME_TO_A_LONG_RANDOM_STRING') {
            return false;
        }

        // Normalize headers to lowercase keys.
        $h = [];
        foreach ($headers as $k => $v) {
            $h[strtolower($k)] = is_array($v) ? implode(',', $v) : $v;
        }

        // 1. Bearer token
        if (!empty($h['authorization'])) {
            if (preg_match('/^Bearer\s+(.+)$/i', $h['authorization'], $m)) {
                if (hash_equals($token, trim($m[1]))) {
                    return true;
                }
            }
        }

        // 2. HMAC signature
        $sig = $h['x-npay-signature'] ?? $h['x-signature'] ?? '';
        if ($sig !== '') {
            $expected = hash_hmac('sha256', $rawBody, $token);
            if (hash_equals($expected, strtolower(trim($sig)))) {
                return true;
            }
        }

        // 3. Plain api_key query/body param (fallback for simple setups)
        if (!empty($h['x-api-key']) && hash_equals($token, trim($h['x-api-key']))) {
            return true;
        }

        return false;
    }

    /**
     * Extract a normalized payment record out of an NPay webhook payload.
     * NPay uses keys similar to SePay; we try several common names.
     *
     * @return array{content:string, amount:int, transaction_id:string, raw:array}
     */
    public function parseWebhookPayload(array $payload): array
    {
        $content = (string) (
            $payload['content']
            ?? $payload['description']
            ?? $payload['transferContent']
            ?? $payload['transfer_content']
            ?? ''
        );

        $amount = (int) (
            $payload['transferAmount']
            ?? $payload['transfer_amount']
            ?? $payload['amount']
            ?? 0
        );

        $tid = (string) (
            $payload['id']
            ?? $payload['transactionId']
            ?? $payload['transaction_id']
            ?? $payload['referenceCode']
            ?? ''
        );

        return [
            'content'        => $content,
            'amount'         => $amount,
            'transaction_id' => $tid,
            'raw'            => $payload,
        ];
    }
}
