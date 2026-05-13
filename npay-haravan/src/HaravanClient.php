<?php

declare(strict_types=1);

namespace NPay\Haravan;

use GuzzleHttp\Client;

/**
 * Haravan REST API client.
 *
 * Docs: https://docs.haravan.com/
 *
 * - OAuth token exchange: POST https://{shop}/admin/oauth/access_token
 * - Get order:            GET  https://{shop}/admin/orders/{id}.json
 * - Create transaction:   POST https://{shop}/admin/orders/{id}/transactions.json
 */
class HaravanClient
{
    private array $config;
    private Client $http;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->http   = new Client([
            'timeout'         => 20,
            'connect_timeout' => 5,
            'http_errors'     => false,
        ]);
    }

    /**
     * Exchange an authorization code for an access token.
     *
     * @return array{access_token?:string,scope?:string}
     */
    public function exchangeCode(string $shop, string $code): array
    {
        $url = 'https://' . $shop . '/admin/oauth/access_token';
        $res = $this->http->post($url, [
            'form_params' => [
                'client_id'     => $this->config['haravan_client_id'],
                'client_secret' => $this->config['haravan_client_secret'],
                'code'          => $code,
            ],
        ]);
        $body = (string)$res->getBody();
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid OAuth response: ' . $body);
        }
        if (($res->getStatusCode() >= 400) || empty($data['access_token'])) {
            throw new \RuntimeException('OAuth exchange failed: ' . $body);
        }
        return $data;
    }

    /**
     * Fetch order details.
     */
    public function getOrder(string $shop, string $accessToken, string $orderId): array
    {
        $url = 'https://' . $shop . '/admin/orders/' . $orderId . '.json';
        $res = $this->http->get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept'        => 'application/json',
            ],
        ]);
        $body = (string)$res->getBody();
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid getOrder response: ' . $body);
        }
        return $data['order'] ?? $data;
    }

    /**
     * Create a transaction (mark order paid).
     */
    public function createTransaction(
        string $shop,
        string $accessToken,
        string $orderId,
        float $amount,
        string $currency = 'VND',
        string $kind = 'capture',
        string $status = 'success',
        ?string $gateway = 'NPay',
        ?string $note = null
    ): array {
        $url = 'https://' . $shop . '/admin/orders/' . $orderId . '/transactions.json';
        $payload = [
            'transaction' => [
                'kind'     => $kind,
                'status'   => $status,
                'amount'   => $amount,
                'currency' => $currency,
                'gateway'  => $gateway,
                'source'   => 'external',
            ],
        ];
        if ($note !== null) {
            $payload['transaction']['message'] = $note;
        }

        $res = $this->http->post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        $body = (string)$res->getBody();
        $data = json_decode($body, true) ?: ['raw' => $body];
        if ($res->getStatusCode() >= 400) {
            throw new \RuntimeException('createTransaction failed: ' . $body);
        }
        return $data;
    }
}
