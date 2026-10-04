<?php

declare(strict_types=1);

namespace NPay\Haravan;

/**
 * Builds NPay QR (qr.npay.vn) URLs and verifies NPay webhook signatures.
 */
class NPayClient
{
    private array $cfg;

    public function __construct(array $config)
    {
        $this->cfg = $config['npay'] ?? [];
    }

    private const BANK_ALIASES = [
        'vcb' => 'vietcombank', 'tcb' => 'techcombank', 'ctg' => 'vietinbank', 'icb' => 'vietinbank',
        'vtb' => 'vietinbank', 'mb' => 'mbbank', 'vpb' => 'vpbank', 'tpb' => 'tpbank', 'stb' => 'sacombank',
        'hdb' => 'hdbank', 'eib' => 'eximbank', 'vba' => 'agribank', 'agr' => 'agribank',
        'lpb' => 'lienvietpostbank', 'lpbank' => 'lienvietpostbank', 'nab' => 'namabank',
        'abb' => 'abbank', 'bab' => 'bacabank', 'pvcb' => 'pvcombank', 'seab' => 'seabank',
        'klb' => 'kienlongbank', 'vab' => 'vietabank', 'sgicb' => 'saigonbank', 'bvb' => 'banviet',
    ];

    /**
     * Build the NPay QR image URL: `/qrcard` (VietQR card) or `/qrpay` (bare QR,
     * `qr_template` = `qr_only`).
     */
    public function buildQrUrl(string $code, float $amount): string
    {
        $base = rtrim((string)($this->cfg['qr_endpoint'] ?? 'https://qr.npay.vn'), '/');
        $base = (string)preg_replace('#/(img|qrpay|qrcard)$#', '', $base);
        $tpl  = strtolower((string)($this->cfg['qr_template'] ?? ''));
        $bare = in_array($tpl, ['qr_only', 'qronly'], true);

        $params = self::bankParam((string)($this->cfg['bank_id'] ?? '')) + [
            'tai_khoan' => (string)($this->cfg['account_number'] ?? ''),
            'so_tien'   => (string)(int)round($amount),
            'noi_dung'  => $code,
        ];
        $name = (string)($this->cfg['account_name'] ?? '');
        if (!$bare && $name !== '') {
            $params['chu_tai_khoan'] = $name;
        }
        return $base . ($bare ? '/qrpay' : '/qrcard') . '?' . http_build_query($params);
    }

    /**
     * qr.npay.vn takes a Napas BIN (`ma_bin`) or a vietnam-qr-pay slug (`ngan_hang`);
     * short codes like VCB / MB are mapped to their slug.
     *
     * @return array<string, string>
     */
    public static function bankParam(string $bank): array
    {
        $bank = strtolower((string)preg_replace('/[\s_-]+/', '', trim($bank)));
        if (preg_match('/^\d{6}$/', $bank)) {
            return ['ma_bin' => $bank];
        }
        return ['ngan_hang' => self::BANK_ALIASES[$bank] ?? $bank];
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
