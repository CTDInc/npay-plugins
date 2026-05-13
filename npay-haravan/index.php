<?php
/**
 * NPay Haravan - front controller.
 *
 * Routes:
 *   GET  /install                  - Initiate OAuth install (?shop=xxx.myharavan.com)
 *   GET  /oauth/callback           - Haravan OAuth callback
 *   POST /webhook/haravan          - Haravan webhook (orders/create)
 *   POST /webhook/npay             - NPay transaction webhook
 *   GET  /payment/{order_id}       - Customer-facing payment page (QR)
 *   GET  /status/{order_id}        - JSON status polling endpoint
 *   GET  /admin                    - Simple admin dashboard
 */

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    $configFile = __DIR__ . '/config.example.php';
}
$config = require $configFile;

// Ensure data dir exists for SQLite
if (str_starts_with((string)$config['db_dsn'], 'sqlite:')) {
    $path = substr($config['db_dsn'], 7);
    $dir  = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

$db = new NPay\Haravan\Database($config);
$db->migrate();

$router = new NPay\Haravan\Router($config, $db);
$router->dispatch();
