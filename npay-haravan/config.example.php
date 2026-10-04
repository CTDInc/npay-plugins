<?php
/**
 * NPay Haravan - Example configuration.
 * Copy to config.php and fill in your credentials.
 */

return [
    // Haravan OAuth app credentials (from https://partners.haravan.com)
    'haravan_client_id'     => 'YOUR_HARAVAN_CLIENT_ID',
    'haravan_client_secret' => 'YOUR_HARAVAN_CLIENT_SECRET',
    'haravan_scopes'        => 'read_orders,write_orders,read_products',

    // Admin password for /admin/login (operator view: every shop). Compared in constant
    // time; empty = password login disabled. Shop owners get in via the Haravan OAuth
    // install (/install?shop=...), which only shows their own shop's orders.
    'admin_password' => getenv('ADMIN_PASSWORD') ?: '',

    // Public URL where this app is served (no trailing slash)
    'app_url' => 'https://npay-haravan.example.com',

    // Database (MySQL recommended in production, SQLite for dev)
    // Examples:
    //   'mysql:host=127.0.0.1;dbname=npay_haravan;charset=utf8mb4'
    //   'sqlite:' . __DIR__ . '/data/npay.sqlite'
    'db_dsn'  => 'sqlite:' . __DIR__ . '/data/npay.sqlite',
    'db_user' => null,
    'db_pass' => null,

    // NPay merchant configuration
    'npay' => [
        'bank_id'         => 'BIDV',          // Napas BIN (970418) or bank code/slug (BIDV, VCB, mbbank)
        'account_number'  => '0123456789',
        'account_name'    => 'CONG TY NPAY',
        // Webhook auth — paste from the webhook on https://npay.vn. Either one is enough:
        //   api_key:        sent as `Authorization: Apikey <key>` (auth type "API Key")
        //   webhook_secret: signs the raw body, `X-Npay-Signature` = hex HMAC-SHA256
        'api_key'         => '',
        'webhook_secret'  => '',
        'qr_template'     => 'compact',       // 'qr_only' = bare QR (/qrpay), anything else = VietQR card (/qrcard)
        'qr_endpoint'     => 'https://qr.npay.vn',
    ],

    // Order code prefix (becomes NPAY-{order_number})
    'order_prefix' => 'NPAY',

    // Polling interval (ms) on customer payment page
    'poll_interval_ms' => 4000,
];
