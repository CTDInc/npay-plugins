<?php
/**
 * NPay Sapo App - Configuration template.
 * Copy to config.php and fill in actual values.
 */

return [
    // Public URL of this app (no trailing slash). Used for OAuth callback and asset URLs.
    'app_url' => getenv('APP_URL') ?: 'https://sapo.npay.vn',

    // Sapo Developer Portal credentials (single app, many stores).
    'sapo_client_id'     => getenv('SAPO_CLIENT_ID')     ?: 'your-sapo-client-id',
    'sapo_client_secret' => getenv('SAPO_CLIENT_SECRET') ?: 'your-sapo-client-secret',
    // Scopes requested when installing on a Sapo store.
    'sapo_scopes' => 'read_orders,write_orders,read_products',

    // Admin password for /admin/login (operator view: every store). Compared in constant
    // time; empty = password login disabled. Store owners get in via the Sapo OAuth install
    // (/install?shop=...), which only unlocks their own store.
    'admin_password' => getenv('ADMIN_PASSWORD') ?: '',

    // NPay platform credentials. Per-store API key + webhook secret live in DB;
    // this secret is only a fallback for stores without their own. Empty = off.
    'npay_webhook_secret' => getenv('NPAY_WEBHOOK_SECRET') ?: '',
    'npay_qr_endpoint'    => getenv('NPAY_QR_ENDPOINT')    ?: 'https://qr.npay.vn',
    'npay_api_base'       => getenv('NPAY_API_BASE')       ?: 'https://api.npay.vn',

    // Database DSN (PDO). Default MySQL; swap for sqlite during dev.
    'db_dsn'  => getenv('DB_DSN')  ?: 'mysql:host=127.0.0.1;port=3306;dbname=npay_sapo;charset=utf8mb4',
    'db_user' => getenv('DB_USER') ?: 'npay',
    'db_pass' => getenv('DB_PASS') ?: 'npay',

    // Payment timeout in seconds (used on QR page countdown).
    'payment_ttl' => 900,
];
