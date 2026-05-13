<?php
/**
 * NPay PHP+MySQL Example — Configuration
 *
 * Copy this file to config.php and edit values for your environment.
 *   cp config.example.php config.php
 *
 * DO NOT commit config.php to git.
 */

return [
    // ---------------------------------------------------------------------
    // Database (MySQL / MariaDB)
    // ---------------------------------------------------------------------
    'db' => [
        'dsn'      => 'mysql:host=127.0.0.1;port=3306;dbname=npay_demo;charset=utf8mb4',
        'username' => 'npay_user',
        'password' => 'change_me',
        'options'  => [
            // PDO options — sane defaults are applied in lib/Database.php
        ],
    ],

    // ---------------------------------------------------------------------
    // NPay Webhook authentication
    //
    //  - api_token: token cấu hình trong dashboard https://my.npay.vn.
    //               Webhook gửi `Authorization: Apikey <token>`.
    //  - hmac_secret: (tùy chọn) secret để verify `X-NPay-Signature`
    //               (HMAC-SHA256 của raw body).
    // ---------------------------------------------------------------------
    'webhook' => [
        'api_token'   => 'NPAY_API_TOKEN_HERE',
        'hmac_secret' => '',           // bỏ trống để tắt verify HMAC
        'allow_ip'    => [],           // ['113.161.0.0/16'] để chặn IP nếu cần
    ],

    // ---------------------------------------------------------------------
    // Tài khoản nhận tiền — dùng để dựng QR động
    // ---------------------------------------------------------------------
    'account' => [
        'bank_bin'       => '970422',                 // ví dụ MB Bank
        'bank_short'     => 'MB',
        'account_number' => '0123456789',
        'account_holder' => 'NGUYEN VAN A',
        'qr_template'    => 'compact2',               // compact | compact2 | qr_only | print
    ],

    // ---------------------------------------------------------------------
    // Endpoint NPay
    // ---------------------------------------------------------------------
    'endpoints' => [
        'api'       => 'https://api.npay.vn',
        'qr'        => 'https://qr.npay.vn',
        'dashboard' => 'https://my.npay.vn',
    ],

    // ---------------------------------------------------------------------
    // Trang admin (HTTP Basic)
    // ---------------------------------------------------------------------
    'admin' => [
        'username' => 'admin',
        'password' => 'change_me_too',
    ],

    // ---------------------------------------------------------------------
    // App
    // ---------------------------------------------------------------------
    'app' => [
        'name'        => 'NPay Demo Shop',
        'base_url'    => 'https://yourdomain.tld/npay',
        'order_ttl'   => 15 * 60,    // 15 phút
        'code_prefix' => 'NPAY',
        'timezone'    => 'Asia/Ho_Chi_Minh',
    ],
];
