<?php
declare(strict_types=1);

/**
 * NPay × Sapo - front controller.
 */

require __DIR__ . '/vendor/autoload.php';

use NPay\Sapo\AdminAuth;
use NPay\Sapo\Router;
use NPay\Sapo\Database;
use NPay\Sapo\SapoClient;
use NPay\Sapo\NPayClient;
use NPay\Sapo\Payment\PaymentPage;
use NPay\Sapo\Webhook\SapoWebhook;
use NPay\Sapo\Webhook\NPayWebhook;

$configPath = file_exists(__DIR__ . '/config.php')
    ? __DIR__ . '/config.php'
    : __DIR__ . '/config.example.php';
$config = require $configPath;

$db          = new Database($config);
$sapoClient  = new SapoClient($config, $db);
$npayClient  = new NPayClient($config, $db);
$paymentPage = new PaymentPage($config, $db, $npayClient);
$sapoHook    = new SapoWebhook($config, $db, $sapoClient, $npayClient);
$npayHook    = new NPayWebhook($config, $db, $sapoClient);
$auth        = new AdminAuth($config);

$router = new Router();

// ---- OAuth install / callback ----
$router->get('/install', function () use ($config, $sapoClient): void {
    $shop = $_GET['shop'] ?? '';
    if ($shop === '' || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*$/', $shop)) {
        http_response_code(400);
        echo 'Missing or invalid ?shop parameter (e.g. yourstore.mysapo.net).';
        return;
    }
    $state = bin2hex(random_bytes(8));
    setcookie('npay_sapo_state', $state, time() + 600, '/', '', true, true);
    $url = $sapoClient->buildAuthorizeUrl($shop, $state);
    header('Location: ' . $url);
});

$router->get('/oauth/callback', function () use ($sapoClient, $auth): void {
    $shop  = $_GET['shop']  ?? '';
    $code  = $_GET['code']  ?? '';
    $state = $_GET['state'] ?? '';
    $expected = $_COOKIE['npay_sapo_state'] ?? '';
    if ($shop === '' || $code === '' || $state === '' || !hash_equals($expected, $state)
        || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*$/', $shop)) {
        http_response_code(400);
        echo 'Invalid OAuth callback.';
        return;
    }
    $store = $sapoClient->exchangeCode($shop, $code);
    setcookie('npay_sapo_state', '', time() - 3600, '/', '', true, true);
    $auth->grantStore((int)$store['id']);
    header('Location: /admin?store=' . urlencode((string)$store['id']));
});

// ---- Webhooks ----
$router->post('/webhook/sapo', function () use ($sapoHook): void {
    $sapoHook->handle();
});

$router->post('/webhook/npay', function () use ($npayHook): void {
    $npayHook->handle();
});

// ---- Customer-facing payment page + status polling ----
$router->get('/payment/{orderId}', function (array $params) use ($paymentPage): void {
    $paymentPage->render((string)$params['orderId']);
});

$router->get('/status/{orderId}', function (array $params) use ($paymentPage): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($paymentPage->status((string)$params['orderId']));
});

// ---- Admin ----
$router->get('/admin/login', function () use ($config, $auth): void {
    $passwordConfigured = $auth->passwordConfigured();
    $error   = !empty($_GET['error']);
    $storeId = isset($_GET['store']) ? (int)$_GET['store'] : 0;
    $csrf    = $auth->csrfToken();
    header('Cache-Control: no-store');
    include __DIR__ . '/templates/login.php';
});

$router->post('/admin/login', function () use ($auth): void {
    $storeId = (int)($_POST['store'] ?? 0);
    $back    = '/admin' . ($storeId ? '?store=' . $storeId : '');
    if (!$auth->checkCsrf((string)($_POST['csrf'] ?? '')) || !$auth->login((string)($_POST['password'] ?? ''))) {
        usleep(500000);
        header('Location: /admin/login?error=1' . ($storeId ? '&store=' . $storeId : ''));
        return;
    }
    header('Location: ' . $back);
});

$router->post('/admin/logout', function () use ($auth): void {
    if ($auth->checkCsrf((string)($_POST['csrf'] ?? ''))) {
        $auth->logout();
    }
    header('Location: /admin/login');
});

$router->get('/admin', function () use ($config, $db, $auth): void {
    $storeId = isset($_GET['store']) ? (int)$_GET['store'] : 0;
    if (!$auth->isAuthenticated()) {
        header('Location: /admin/login' . ($storeId ? '?store=' . $storeId : ''));
        return;
    }
    if ($storeId === 0 && !$auth->isOperator()) {
        $storeId = $auth->grantedStores()[0];
    }
    if ($storeId !== 0 && !$auth->canAccessStore($storeId)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Không có quyền với cửa hàng này. Cài lại app từ cửa hàng đó (/install?shop=...) hoặc đăng nhập quản trị.\n";
        return;
    }
    $store      = $storeId ? $db->findStore($storeId) : null;
    $orders     = $store   ? $db->listOrders((int)$store['id'], 50) : [];
    $stores     = $auth->isOperator() && !$store ? $db->listStores() : [];
    $isOperator = $auth->isOperator();
    $csrf       = $auth->csrfToken();
    header('Cache-Control: no-store');
    include __DIR__ . '/templates/admin.php';
});

$router->post('/admin/save', function () use ($db, $auth): void {
    $storeId = (int)($_POST['store_id'] ?? 0);
    if ($storeId === 0) {
        http_response_code(400);
        echo 'Missing store_id';
        return;
    }
    if (!$auth->canAccessStore($storeId) || !$auth->checkCsrf((string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        echo 'Forbidden';
        return;
    }
    $db->updateStoreSettings($storeId, [
        'api_key'        => trim((string)($_POST['api_key'] ?? '')),
        'account_number' => trim((string)($_POST['account_number'] ?? '')),
        'account_name'   => trim((string)($_POST['account_name'] ?? '')),
        'bank_code'      => trim((string)($_POST['bank_code'] ?? '')),
        'webhook_secret' => trim((string)($_POST['webhook_secret'] ?? '')),
        'npay_webhook_secret' => trim((string)($_POST['npay_webhook_secret'] ?? '')),
    ]);
    header('Location: /admin?store=' . $storeId . '&saved=1');
});

// ---- Health ----
$router->get('/', function (): void {
    header('Content-Type: text/plain; charset=utf-8');
    echo "NPay × Sapo bridge is running.\n";
});

$router->get('/healthz', function (): void {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'app' => 'npay-sapo']);
});

$router->dispatch(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'
);
