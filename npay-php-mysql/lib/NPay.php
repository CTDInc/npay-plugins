<?php
/**
 * lib/NPay.php — Helpers cho NPay: QR URL builder, HMAC verify, code generator.
 */

namespace NPay;

final class NPay
{
    private const BANK_ALIASES = [
        'vcb' => 'vietcombank', 'tcb' => 'techcombank', 'ctg' => 'vietinbank', 'icb' => 'vietinbank',
        'mb' => 'mbbank', 'vpb' => 'vpbank', 'tpb' => 'tpbank', 'stb' => 'sacombank',
        'hdb' => 'hdbank', 'eib' => 'eximbank', 'vba' => 'agribank', 'agr' => 'agribank',
        'lpb' => 'lienvietpostbank', 'lpbank' => 'lienvietpostbank', 'nab' => 'namabank',
        'abb' => 'abbank', 'bab' => 'bacabank', 'pvcb' => 'pvcombank', 'seab' => 'seabank',
        'klb' => 'kienlongbank', 'vab' => 'vietabank', 'sgicb' => 'saigonbank', 'bvb' => 'banviet',
    ];

    /**
     * Build QR image URL từ dịch vụ gen-qr của NPay (qr.npay.vn).
     *
     *   https://qr.npay.vn/qrcard?ma_bin=970422&tai_khoan=0123456789&so_tien=100000&noi_dung=NPAY12&chu_tai_khoan=...
     *   (`qr_template` = `qr_only` → `/qrpay`, chỉ có mã QR)
     *
     * @param array<string,mixed> $opt Override account/qr_template.
     */
    public static function qrUrl(string $code, float $amount, array $opt = []): string
    {
        $cfg = Database::config();
        $acc = $opt + ($cfg['account']  ?? []);
        $ep  = $cfg['endpoints'] ?? [];

        $base = rtrim($ep['qr'] ?? 'https://qr.npay.vn', '/');
        $base = (string)preg_replace('#/(img|qrpay|qrcard)$#', '', $base);
        $bareQr = in_array($acc['qr_template'] ?? '', ['qr_only', 'qronly'], true);

        $params = self::bankParam((string)($acc['bank_bin'] ?? '') ?: (string)($acc['bank_short'] ?? '')) + [
            'tai_khoan' => (string)($acc['account_number'] ?? ''),
            'so_tien'   => number_format($amount, 0, '', ''),
            'noi_dung'  => $code,
        ];
        $holder = (string)($acc['account_holder'] ?? '');
        if (!$bareQr && $holder !== '') {
            $params['chu_tai_khoan'] = $holder;
        }
        return $base . ($bareQr ? '/qrpay?' : '/qrcard?') . http_build_query($params);
    }

    /**
     * gen-qr nhận BIN Napas (`ma_bin`) hoặc mã ngân hàng vietnam-qr-pay (`ngan_hang`).
     *
     * @return array<string,string>
     */
    public static function bankParam(string $bank): array
    {
        $bank = strtolower((string)preg_replace('/[\s_-]+/', '', trim($bank)));
        if (preg_match('/^\d{6}$/', $bank)) {
            return ['ma_bin' => $bank];
        }
        return ['ngan_hang' => self::BANK_ALIASES[$bank] ?? $bank];
    }

    /**
     * Tạo mã đơn duy nhất dạng NPAY{id} hoặc random.
     */
    public static function generateCode(?int $orderId = null): string
    {
        $cfg = Database::config();
        $prefix = $cfg['app']['code_prefix'] ?? 'NPAY';
        if ($orderId !== null && $orderId > 0) {
            return $prefix . $orderId;
        }
        return $prefix . strtoupper(bin2hex(random_bytes(4)));
    }

    /**
     * Constant-time compare API token (Authorization: Apikey <token>).
     */
    public static function verifyApiKey(?string $headerValue, string $expected): bool
    {
        if ($headerValue === null || $expected === '') {
            return false;
        }
        $token = trim($headerValue);
        if (preg_match('/^(Apikey|Bearer)\s+(.+)$/i', $token, $m)) {
            $token = trim($m[2]);
        }
        return hash_equals($expected, $token);
    }

    /**
     * Verify HMAC-SHA256 chữ ký của raw body.
     * Header: X-Npay-Signature: <hex hmac> (khoá = webhook secret trên dashboard NPay).
     * Có X-Npay-Timestamp thì từ chối request lệch quá $maxSkew giây.
     */
    public static function verifySignature(string $rawBody, ?string $signature, string $secret, ?string $timestamp = null, int $maxSkew = 300): bool
    {
        if ($secret === '') {
            return false; // fail closed: an unset secret must never accept
        }
        if ($signature === null || $signature === '') {
            return false;
        }
        $sig = strtolower(trim($signature));
        if (strpos($sig, 'sha256=') === 0) {
            $sig = substr($sig, 7);
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        if (!hash_equals($expected, $sig)) {
            return false;
        }
        $ts = trim((string)$timestamp);
        return $ts === '' || (ctype_digit($ts) && abs(time() - (int)$ts) <= $maxSkew);
    }

    /**
     * Trích mã đơn (NPAYxx) từ nội dung chuyển khoản.
     */
    public static function extractOrderCode(string $content, string $prefix = 'NPAY'): ?string
    {
        $prefix = preg_quote($prefix, '/');
        if (preg_match('/(' . $prefix . '[A-Z0-9]+)/i', $content, $m)) {
            return strtoupper($m[1]);
        }
        return null;
    }

    /**
     * Trả JSON và thoát.
     *
     * @param array<string,mixed> $payload
     */
    public static function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Đọc header request không phụ thuộc apache_request_headers.
     */
    public static function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        if (function_exists('apache_request_headers')) {
            foreach (apache_request_headers() as $k => $v) {
                if (strcasecmp($k, $name) === 0) {
                    return $v;
                }
            }
        }
        return null;
    }
}
