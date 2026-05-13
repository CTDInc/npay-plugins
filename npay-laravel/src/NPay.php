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

    /**
     * Tạo URL ảnh QR thanh toán (định dạng VietQR-compatible qua qr.npay.vn).
     *
     * Các tham số hỗ trợ: bank, account, amount, description, template, account_name.
     *
     * @param  array<string, mixed>  $params
     */
    public function generateQrUrl(array $params): string
    {
        $bank = $params['bank'] ?? $this->config('bank_bin');
        $account = $params['account'] ?? $this->config('account_number');
        $template = $params['template'] ?? $this->config('default_template', 'compact');
        $accountName = $params['account_name'] ?? $this->config('account_holder');

        if (empty($bank) || empty($account)) {
            throw new \InvalidArgumentException('NPay: thiếu thông tin bank hoặc account để tạo QR.');
        }

        $base = rtrim((string) $this->config('qr_base', 'https://qr.npay.vn'), '/');
        $path = sprintf('/img/%s/%s/%s.png', rawurlencode((string) $bank), rawurlencode((string) $account), rawurlencode((string) $template));

        $query = [];
        if (isset($params['amount']) && $params['amount'] !== '') {
            $query['amount'] = (int) $params['amount'];
        }
        if (isset($params['description']) && $params['description'] !== '') {
            $query['des'] = (string) $params['description'];
        }
        if (!empty($accountName)) {
            $query['accountName'] = (string) $accountName;
        }

        $url = $base . $path;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
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
     * Xác thực webhook (header Authorization: Apikey <token>).
     */
    public function verifyWebhook(Request $request): bool
    {
        $expected = (string) $this->config('webhook_token', '');
        if ($expected === '') {
            return false;
        }

        $auth = (string) $request->header('Authorization', '');
        if ($auth === '') {
            return false;
        }

        // Hỗ trợ "Apikey <token>" hoặc "Bearer <token>"
        if (preg_match('/^(Apikey|Bearer)\s+(.+)$/i', $auth, $m)) {
            return hash_equals($expected, trim($m[2]));
        }

        return hash_equals($expected, $auth);
    }
}
