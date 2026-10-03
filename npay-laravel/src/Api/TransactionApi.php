<?php

namespace NPay\Laravel\Api;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Http;

/**
 * Public API v1 của NPay (`/api/v1/...`), xác thực `Authorization: Bearer <api token>`
 * (token `zna_…` tạo trong dashboard npay.vn → Cài đặt → API token).
 *
 * Giao dịch trả về theo đúng shape của NPay, `id` là chuỗi `tx_…`:
 *   {"id": "tx_…", "account_id": "…", "bank": "MBBank", "account_number": "…",
 *    "virtual_account": "", "datetime": "…", "type": "in"|"out", "amount": 100000,
 *    "balance": 1000000, "description": "…", "payment_code": "", "reference_code": "", …}
 */
class TransactionApi
{
    public function __construct(
        protected string $apiBase,
        protected string $apiToken
    ) {
        $this->apiBase = rtrim($apiBase, '/');
    }

    /**
     * Danh sách giao dịch, phân trang: {"items": [...], "count": n, "page": 1, "page_size": 20}.
     *
     * @param  array<string, mixed>  $params  page, page_size (tối đa 100), q (tìm theo nội dung,
     *                                        mã thanh toán, mã tham chiếu…), date_from, date_to
     *                                        (Y-m-d), account_id, virtual_account, ordering
     * @return array<string, mixed>
     */
    public function list(array $params = []): array
    {
        return $this->get('/api/v1/transactions/', $params);
    }

    /**
     * Chi tiết 1 giao dịch theo id công khai (`tx_…`).
     *
     * @return array<string, mixed>
     */
    public function get_(string $id): array
    {
        return $this->get('/api/v1/transactions/' . rawurlencode($id) . '/');
    }

    /**
     * Giao dịch có `reference_code` đúng bằng $reference: {"items": [...], "count": n}.
     *
     * @return array<string, mixed>
     */
    public function findByReference(string $reference): array
    {
        $result = $this->list(['q' => $reference, 'page_size' => 100]);
        $items = array_values(array_filter(
            (array) ($result['items'] ?? []),
            static fn ($tx) => is_array($tx) && (string) ($tx['reference_code'] ?? '') === $reference
        ));

        return ['items' => $items, 'count' => count($items)];
    }

    /**
     * Danh sách tài khoản ngân hàng của chủ token.
     *
     * @return array<int|string, mixed>
     */
    public function accounts(): array
    {
        return $this->get('/api/v1/accounts/');
    }

    /**
     * Thực hiện request GET.
     *
     * @param  array<string, mixed>  $params
     * @return array<int|string, mixed>
     */
    protected function get(string $path, array $params = []): array
    {
        $url = $this->apiBase . $path;
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiToken,
            'Accept' => 'application/json',
        ];

        // Dùng Http facade nếu có (Laravel runtime), fallback Guzzle
        if (class_exists(Http::class) && function_exists('app') && app()->bound('config')) {
            try {
                $response = Http::withHeaders($headers)->get($url, $params);
                return is_array($response->json()) ? $response->json() : ['raw' => $response->body()];
            } catch (\Throwable $e) {
                // fall through to Guzzle
            }
        }

        $client = new GuzzleClient(['timeout' => 15]);
        $response = $client->request('GET', $url, [
            'headers' => $headers,
            'query' => $params,
            'http_errors' => false,
        ]);

        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : ['raw' => $body];
    }
}
