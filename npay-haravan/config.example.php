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
        'bank_id'         => 'BIDV',          // BIN/short code of receiving bank
        'account_number'  => '0123456789',
        'account_name'    => 'CONG TY NPAY',
        'webhook_secret'  => 'CHANGE_ME_NPAY_WEBHOOK_SECRET',
        'qr_template'     => 'compact2',
    ],

    // Order code prefix (becomes NPAY-{order_number})
    'order_prefix' => 'NPAY',

    // Polling interval (ms) on customer payment page
    'poll_interval_ms' => 4000,
];
