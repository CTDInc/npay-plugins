<?php
/**
 * lib/NPay.php — Helpers cho NPay: QR URL builder, HMAC verify, code generator.
 */

namespace NPay;

final class NPay
{
    /**
     * Build QR image URL theo chuẩn NPay (qr.npay.vn).
     *
     *   https://qr.npay.vn/img?bank=MB&acc=0123456789&template=compact2&amount=100000&des=NPAY12
     *
     * @param array<string,mixed> $opt Override account/qr_template.
     */
    public static function qrUrl(string $code, float $amount, array $opt = []): string
    {
        $cfg = Database::config();
        $acc = $opt + ($cfg['account']  ?? []);
        $ep  = $cfg['endpoints'] ?? [];

        $base = rtrim($ep['qr'] ?? 'https://qr.npay.vn', '/');
        $qs = http_build_query([
            'bank'     => $acc['bank_short']     ?? '',
            'acc'      => $acc['account_number'] ?? '',
            'template' => $acc['qr_template']    ?? 'compact2',
            'amount'   => number_format($amount, 0, '', ''),
            'des'      => $code,
        ]);
        return $base . '/img?' . $qs;
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
        if (stripos($token, 'apikey ') === 0) {
            $token = trim(substr($token, 7));
        }
        return hash_equals($expected, $token);
    }

    /**
     * Verify HMAC-SHA256 chữ ký của raw body.
     * Header: X-NPay-Signature: <hex hmac>
     */
    public static function verifySignature(string $rawBody, ?string $signature, string $secret): bool
    {
        if ($secret === '') {
            return false; // fail closed: an unset secret must never accept
        }
        if ($signature === null || $signature === '') {
            return false;
        }
        $sig = trim($signature);
        if (stripos($sig, 'sha256=') === 0) {
            $sig = substr($sig, 7);
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $sig);
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
