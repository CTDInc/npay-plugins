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

    private const BANK_ALIASES = [
        'vcb' => 'vietcombank', 'tcb' => 'techcombank', 'ctg' => 'vietinbank', 'icb' => 'vietinbank',
        'mb' => 'mbbank', 'vpb' => 'vpbank', 'tpb' => 'tpbank', 'stb' => 'sacombank',
        'hdb' => 'hdbank', 'eib' => 'eximbank', 'vba' => 'agribank', 'agr' => 'agribank',
        'lpb' => 'lienvietpostbank', 'lpbank' => 'lienvietpostbank', 'nab' => 'namabank',
        'abb' => 'abbank', 'bab' => 'bacabank', 'pvcb' => 'pvcombank', 'seab' => 'seabank',
        'klb' => 'kienlongbank', 'vab' => 'vietabank', 'sgicb' => 'saigonbank', 'bvb' => 'banviet',
    ];

    /**
     * Build a qr.npay.vn image URL.
     *
     * Format: https://qr.npay.vn/qrcard?ma_bin=BIN&tai_khoan=ACC&so_tien=AMOUNT&noi_dung=CONTENT&chu_tai_khoan=NAME
     * (`qr_template` = `qr_only` gives the bare QR from /qrpay).
     */
    public function buildQrUrl(string $refCode, int $amount): string
    {
        $base = rtrim($this->config['npay_qr_base'] ?? 'https://qr.npay.vn', '/');
        $base = preg_replace('#/(img|qrpay|qrcard)$#', '', $base);
        $bareQr = in_array($this->config['qr_template'] ?? '', ['qr_only', 'qronly'], true);
        $params = self::bankParam((string) ($this->config['bank_bin'] ?? '')) + [
            'tai_khoan' => (string) ($this->config['account_number'] ?? ''),
            'so_tien'   => (string) $amount,
            'noi_dung'  => $refCode,
        ];
        $holder = (string) ($this->config['account_holder'] ?? '');
        if (!$bareQr && $holder !== '') {
            $params['chu_tai_khoan'] = $holder;
        }
        return $base . ($bareQr ? '/qrpay?' : '/qrcard?') . http_build_query($params);
    }

    /**
     * gen-qr takes either a Napas BIN (`ma_bin`) or a vietnam-qr-pay key (`ngan_hang`).
     *
     * @return array<string, string>
     */
    public static function bankParam(string $bank): array
    {
        $bank = strtolower((string) preg_replace('/[\s_-]+/', '', trim($bank)));
        if (preg_match('/^\d{6}$/', $bank)) {
            return ['ma_bin' => $bank];
        }
        return ['ngan_hang' => self::BANK_ALIASES[$bank] ?? $bank];
    }

    /**
     * Link for the "Mở ứng dụng NPay" button.
     */
    public function buildPayLink(string $refCode, int $amount): string
    {
        return (string) ($this->config['npay_site'] ?? 'https://npay.vn');
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
     *   - Authorization: Apikey <npay_api_key>   (Bearer still accepted for old setups)
     *   - X-Npay-Signature: hex( hmac_sha256(raw_body, npay_webhook_secret) )
     *     with X-Npay-Timestamp (if sent) no older than 5 minutes.
     * `npay_api_key` falls back to `api_token`. Nothing configured = reject.
     */
    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        $apiKey = (string) ($this->config['npay_api_key'] ?? '');
        if ($apiKey === '') {
            $apiKey = (string) ($this->config['api_token'] ?? '');
        }
        if ($apiKey === 'CHANGE_ME_TO_A_LONG_RANDOM_STRING') {
            $apiKey = '';
        }
        $secret = (string) ($this->config['npay_webhook_secret'] ?? '');

        $h = [];
        foreach ($headers as $k => $v) {
            $h[strtolower((string) $k)] = is_array($v) ? implode(',', $v) : (string) $v;
        }

        if ($apiKey !== '') {
            $auth = trim($h['authorization'] ?? '');
            if (preg_match('/^(Apikey|Bearer)\s+(.+)$/i', $auth, $m) && hash_equals($apiKey, trim($m[2]))) {
                return true;
            }
            if (!empty($h['x-api-key']) && hash_equals($apiKey, trim($h['x-api-key']))) {
                return true;
            }
        }

        $sig = strtolower(trim($h['x-npay-signature'] ?? ''));
        if ($secret !== '' && $sig !== '' && hash_equals(hash_hmac('sha256', $rawBody, $secret), $sig)) {
            $ts = trim($h['x-npay-timestamp'] ?? '');
            return $ts === '' || (ctype_digit($ts) && abs(time() - (int) $ts) <= 300);
        }

        return false;
    }

    /**
     * Extract a normalized payment record out of an NPay webhook payload.
     * NPay uses keys similar to SePay; we try several common names.
     *
     * @return array{type:string, content:string, amount:int, transaction_id:string, raw:array}
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

        $code = (string) ($payload['code'] ?? '');

        return [
            'type'           => strtolower((string) ($payload['transferType'] ?? 'in')),
            'content'        => trim($code . ' ' . $content),
            'amount'         => $amount,
            'transaction_id' => $tid,
            'raw'            => $payload,
        ];
    }
}
