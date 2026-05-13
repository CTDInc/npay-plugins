<?php
declare(strict_types=1);

namespace NPay\Sapo;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Sapo (Bizweb) REST + OAuth client.
 *
 * Docs: https://developers.sapo.vn/
 */
class SapoClient
{
    private array $config;
    private Database $db;
    private Client $http;

    public function __construct(array $config, Database $db, ?Client $http = null)
    {
        $this->config = $config;
        $this->db     = $db;
        $this->http   = $http ?? new Client(['timeout' => 20]);
    }

    public function buildAuthorizeUrl(string $shop, string $state): string
    {
        $params = [
            'client_id'    => $this->config['sapo_client_id'] ?? '',
            'scope'        => $this->config['sapo_scopes'] ?? 'read_orders,write_orders',
            'redirect_uri' => rtrim((string)($this->config['app_url'] ?? ''), '/') . '/oauth/callback',
            'state'        => $state,
        ];
        return 'https://' . $shop . '/admin/oauth/authorize?' . http_build_query($params);
    }

    /**
     * Exchange authorization code → access token, then persist store.
     */
    public function exchangeCode(string $shop, string $code): array
    {
        $url = 'https://' . $shop . '/admin/oauth/access_token';
        $resp = $this->http->post($url, [
            'form_params' => [
                'client_id'     => $this->config['sapo_client_id'] ?? '',
                'client_secret' => $this->config['sapo_client_secret'] ?? '',
                'code'          => $code,
            ],
        ]);
        $body = (string)$resp->getBody();
        $data = json_decode($body, true) ?: [];
        $token = (string)($data['access_token'] ?? '');
        $scope = $data['scope'] ?? null;
        if ($token === '') {
            throw new \RuntimeException('Sapo OAuth exchange failed: ' . $body);
        }
        return $this->db->upsertStore($shop, $token, is_string($scope) ? $scope : null);
    }

    /**
     * Fetch a single order via Sapo Admin API.
     */
    public function getOrder(array $store, string $orderId): array
    {
        $resp = $this->http->get($this->endpoint($store, "/admin/orders/{$orderId}.json"), [
            'headers' => $this->authHeaders($store),
        ]);
        $data = json_decode((string)$resp->getBody(), true) ?: [];
        return $data['order'] ?? $data;
    }

    /**
     * Mark order as paid in Sapo (financial_status = paid).
     */
    public function markPaid(array $store, string $orderId, array $extra = []): array
    {
        $payload = [
            'order' => array_merge([
                'id'               => $orderId,
                'financial_status' => 'paid',
            ], $extra),
        ];
        try {
            $resp = $this->http->put($this->endpoint($store, "/admin/orders/{$orderId}.json"), [
                'headers' => $this->authHeaders($store) + ['Content-Type' => 'application/json'],
                'body'    => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);
            return json_decode((string)$resp->getBody(), true) ?: [];
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Sapo updateOrder failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Create a transaction record on the order (preferred way to mark paid).
     */
    public function createTransaction(array $store, string $orderId, float $amount, string $note = ''): array
    {
        $payload = [
            'transaction' => [
                'kind'    => 'capture',
                'status'  => 'success',
                'amount'  => $amount,
                'gateway' => 'NPay',
                'source'  => 'external',
                'note'    => $note,
            ],
        ];
        try {
            $resp = $this->http->post(
                $this->endpoint($store, "/admin/orders/{$orderId}/transactions.json"),
                [
                    'headers' => $this->authHeaders($store) + ['Content-Type' => 'application/json'],
                    'body'    => json_encode($payload, JSON_UNESCAPED_UNICODE),
                ]
            );
            return json_decode((string)$resp->getBody(), true) ?: [];
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Sapo createTransaction failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Verify Sapo webhook HMAC-SHA256 signature.
     * Sapo sends header `X-Sapo-Hmac-SHA256` = base64(HMAC_SHA256(rawBody, sharedSecret)).
     */
    public function verifyWebhook(string $rawBody, string $headerSignature, string $secret): bool
    {
        if ($secret === '' || $headerSignature === '') {
            return false;
        }
        $expected = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));
        return hash_equals($expected, $headerSignature);
    }

    private function endpoint(array $store, string $path): string
    {
        return 'https://' . $store['sapo_store'] . $path;
    }

    /** @return array<string, string> */
    private function authHeaders(array $store): array
    {
        return [
            'X-Sapo-Access-Token' => (string)($store['access_token'] ?? ''),
            'Accept'              => 'application/json',
        ];
    }
}
