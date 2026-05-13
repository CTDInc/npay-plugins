<?php

declare(strict_types=1);

namespace NPay\Haravan;

use NPay\Haravan\Payment\PaymentPage;
use NPay\Haravan\Webhook\HaravanWebhook;
use NPay\Haravan\Webhook\NPayWebhook;

class Router
{
    private array $config;
    private Database $db;

    public function __construct(array $config, Database $db)
    {
        $this->config = $config;
        $this->db    = $db;
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $uri    = rtrim($uri, '/');
        if ($uri === '') {
            $uri = '/';
        }

        try {
            if ($uri === '/' || $uri === '/admin') {
                $this->renderAdmin();
                return;
            }

            if ($uri === '/install') {
                $this->install();
                return;
            }

            if ($uri === '/oauth/callback') {
                $this->oauthCallback();
                return;
            }

            if ($uri === '/webhook/haravan' && $method === 'POST') {
                (new HaravanWebhook($this->config, $this->db))->handle();
                return;
            }

            if ($uri === '/webhook/npay' && $method === 'POST') {
                (new NPayWebhook($this->config, $this->db))->handle();
                return;
            }

            if (preg_match('#^/payment/([A-Za-z0-9_\-]+)$#', $uri, $m)) {
                (new PaymentPage($this->config, $this->db))->render($m[1]);
                return;
            }

            if (preg_match('#^/status/([A-Za-z0-9_\-]+)$#', $uri, $m)) {
                (new PaymentPage($this->config, $this->db))->status($m[1]);
                return;
            }

            $this->notFound();
        } catch (\Throwable $e) {
            error_log('[npay-haravan] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    private function install(): void
    {
        $shop = trim((string)($_GET['shop'] ?? ''));
        if ($shop === '' || !preg_match('/^[a-zA-Z0-9\-\.]+$/', $shop)) {
            http_response_code(400);
            echo 'Missing or invalid ?shop=xxx.myharavan.com';
            return;
        }

        $state = bin2hex(random_bytes(16));
        setcookie('npay_haravan_state', $state, [
            'expires'  => time() + 600,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        setcookie('npay_haravan_shop', $shop, [
            'expires'  => time() + 600,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $params = [
            'client_id'    => $this->config['haravan_client_id'],
            'scope'        => $this->config['haravan_scopes'],
            'redirect_uri' => rtrim($this->config['app_url'], '/') . '/oauth/callback',
            'state'        => $state,
            'response_type'=> 'code',
        ];
        $url = 'https://' . $shop . '/admin/oauth/authorize?' . http_build_query($params);
        header('Location: ' . $url, true, 302);
    }

    private function oauthCallback(): void
    {
        $code  = $_GET['code']  ?? '';
        $state = $_GET['state'] ?? '';
        $shop  = $_GET['shop']  ?? ($_COOKIE['npay_haravan_shop'] ?? '');
        $cookieState = $_COOKIE['npay_haravan_state'] ?? '';

        if ($code === '' || $shop === '' || $state === '' || !hash_equals((string)$cookieState, (string)$state)) {
            http_response_code(400);
            echo 'Invalid OAuth state.';
            return;
        }

        $client = new HaravanClient($this->config);
        $token  = $client->exchangeCode((string)$shop, (string)$code);

        $this->db->upsertShop([
            'shop'           => $shop,
            'access_token'   => $token['access_token'] ?? '',
            'scope'          => $token['scope'] ?? ($this->config['haravan_scopes'] ?? ''),
            'installed_at'   => date('Y-m-d H:i:s'),
        ]);

        header('Content-Type: text/html; charset=utf-8');
        echo '<h1>NPay × Haravan</h1><p>Cài đặt thành công cho shop <b>' . htmlspecialchars((string)$shop) . '</b>.</p>';
        echo '<p>Webhook URL Haravan cần đăng ký (orders/create): <code>'
            . htmlspecialchars(rtrim($this->config['app_url'], '/') . '/webhook/haravan') . '</code></p>';
        echo '<p>NPay webhook URL: <code>' . htmlspecialchars(rtrim($this->config['app_url'], '/') . '/webhook/npay') . '</code></p>';
    }

    private function renderAdmin(): void
    {
        $orders = $this->db->listRecentOrders(50);
        $config = $this->config;
        include __DIR__ . '/../templates/admin.php';
    }

    private function notFound(): void
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "404 Not Found";
    }
}
