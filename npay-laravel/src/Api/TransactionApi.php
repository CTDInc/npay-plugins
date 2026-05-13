<?php

namespace NPay\Laravel\Api;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Http;

class TransactionApi
{
    public function __construct(
        protected string $apiBase,
        protected string $apiToken
    ) {
        $this->apiBase = rtrim($apiBase, '/');
    }

    /**
     * Lấy danh sách giao dịch.
     *
     * @param  array<string, mixed>  $params  account_number, transaction_date_min, transaction_date_max, since_id, limit, reference_number, amount_in, amount_out
     * @return array<string, mixed>
     */
    public function list(array $params = []): array
    {
        return $this->get('/userapi/transactions/list', $params);
    }

    /**
     * Lấy chi tiết 1 giao dịch theo id.
     *
     * @return array<string, mixed>
     */
    public function get_(int $id): array
    {
        return $this->get('/userapi/transactions/details/' . $id);
    }

    /**
     * Lấy danh sách giao dịch theo số tham chiếu.
     *
     * @return array<string, mixed>
     */
    public function findByReference(string $reference): array
    {
        return $this->list(['reference_number' => $reference]);
    }

    /**
     * Thực hiện request GET.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function get(string $path, array $params = []): array
    {
        $url = $this->apiBase . $path;
        $headers = [
            'Authorization' => 'Apikey ' . $this->apiToken,
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
