<?php

namespace NPay\Laravel;

use Illuminate\Http\Request;
use NPay\Laravel\Api\TransactionApi;

class NPay
{
    /**
     * Cấu hình của NPay.
     *
     * @var array<string, mixed>
     */
    protected array $config;

    protected ?TransactionApi $transactionApi = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Lấy giá trị config theo key.
     */
    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
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
     * Tạo URL ảnh QR thanh toán từ dịch vụ gen-qr của NPay (qr.npay.vn).
     *
     * `/qrcard` = thẻ VietQR đầy đủ, `/qrpay` = chỉ mã QR (template `qr_only`).
     * `bank` nhận BIN Napas (`970422`) hoặc mã ngân hàng (`mbbank`, `VCB`…).
     *
     * Các tham số hỗ trợ: bank, account, amount, description, template, account_name.
     *
     * @param  array<string, mixed>  $params
     */
    public function generateQrUrl(array $params): string
    {
        $bank = $params['bank'] ?? $this->config('bank_bin');
        $account = $params['account'] ?? $this->config('account_number');
        $template = (string) ($params['template'] ?? $this->config('default_template', 'compact'));
        $accountName = $params['account_name'] ?? $this->config('account_holder');

        if (empty($bank) || empty($account)) {
            throw new \InvalidArgumentException('NPay: thiếu thông tin bank hoặc account để tạo QR.');
        }

        $base = rtrim((string) $this->config('qr_base', 'https://qr.npay.vn'), '/');
        $base = (string) preg_replace('#/(img|qrpay|qrcard)$#', '', $base);
        $bareQr = in_array($template, ['qr_only', 'qronly'], true);

        $query = self::bankParam((string) $bank) + ['tai_khoan' => (string) $account];
        if (isset($params['amount']) && $params['amount'] !== '') {
            $query['so_tien'] = (string) (int) round((float) $params['amount']);
        }
        if (isset($params['description']) && $params['description'] !== '') {
            $query['noi_dung'] = (string) $params['description'];
        }
        if (!$bareQr && !empty($accountName)) {
            $query['chu_tai_khoan'] = (string) $accountName;
        }

        return $base . ($bareQr ? '/qrpay' : '/qrcard') . '?' . http_build_query($query);
    }

    /**
     * gen-qr nhận BIN Napas (`ma_bin`) hoặc khoá vietnam-qr-pay (`ngan_hang`).
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
     * Sinh mã thanh toán duy nhất theo ID đơn hàng.
     */
    public function generatePaymentCode(int $orderId, ?string $prefix = null): string
    {
        $prefix = $prefix ?? (string) $this->config('code_prefix', 'NPAY');

        return sprintf('%s%d', $prefix, $orderId);
    }

    /**
     * Tách orderId từ payment code theo prefix cấu hình.
     */
    public function parsePaymentCode(string $content, ?string $prefix = null): ?int
    {
        $prefix = $prefix ?? (string) $this->config('code_prefix', 'NPAY');

        if (preg_match('/' . preg_quote($prefix, '/') . '(\d+)/i', $content, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Lấy wrapper API giao dịch.
     */
    public function transactions(): TransactionApi
    {
        if ($this->transactionApi === null) {
            $this->transactionApi = new TransactionApi(
                (string) $this->config('api_base', 'https://api.npay.vn'),
                (string) $this->config('api_token', '')
            );
        }

        return $this->transactionApi;
    }

    /**
     * Xác thực webhook NPay. Hợp lệ khi khớp MỘT trong hai cách đã cấu hình:
     *  - `webhook_token`: header `Authorization: Apikey <token>` (nhận cả `Bearer`);
     *  - `webhook_secret`: `X-Npay-Signature` = hex HMAC-SHA256 của raw body; có
     *    `X-Npay-Timestamp` thì lệch tối đa `webhook_tolerance` giây (mặc định 300).
     * Không cấu hình gì → từ chối.
     */
    public function verifyWebhook(Request $request): bool
    {
        return $this->verifyWebhookToken($request) || $this->verifyWebhookSignature($request);
    }

    public function hasWebhookCredentials(): bool
    {
        return (string) $this->config('webhook_token', '') !== ''
            || (string) $this->config('webhook_secret', '') !== '';
    }

    public function verifyWebhookToken(Request $request): bool
    {
        $expected = (string) $this->config('webhook_token', '');
        $auth = trim((string) $request->header('Authorization', ''));
        if ($expected === '' || $auth === '') {
            return false;
        }

        if (preg_match('/^(Apikey|Bearer)\s+(.+)$/i', $auth, $m)) {
            return hash_equals($expected, trim($m[2]));
        }

        return hash_equals($expected, $auth);
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = (string) $this->config('webhook_secret', '');
        $signature = strtolower(trim((string) $request->header('X-Npay-Signature', '')));
        if ($secret === '' || $signature === '') {
            return false;
        }

        if (!hash_equals(hash_hmac('sha256', (string) $request->getContent(), $secret), $signature)) {
            return false;
        }

        $timestamp = trim((string) $request->header('X-Npay-Timestamp', ''));
        if ($timestamp === '') {
            return true;
        }

        return ctype_digit($timestamp)
            && abs(time() - (int) $timestamp) <= (int) $this->config('webhook_tolerance', 300);
    }
}
