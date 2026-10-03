<?php
/**
 * NPay × LadiPage Webhook Receiver — Configuration Template
 *
 * Copy this file to config.php and fill in your real values.
 * config.php is gitignored and must NOT be committed.
 */

return [
    // Admin token protecting admin.php login. Also accepted as the NPay
    // `Authorization: Apikey` value when npay_api_key is empty.
    'api_token' => 'CHANGE_ME_TO_A_LONG_RANDOM_STRING',

    // NPay webhook auth — paste from the webhook you created on https://npay.vn.
    // A request passes if EITHER matches; leave both empty = every webhook is rejected.
    //   npay_api_key:        sent as `Authorization: Apikey <key>` (auth type "API Key")
    //   npay_webhook_secret: signs the raw body, `X-Npay-Signature` = hex HMAC-SHA256
    'npay_api_key'        => '',
    'npay_webhook_secret' => '',

    // Bank account that customers will transfer into.
    'account_number' => '0123456789',
    'bank_bin'       => '970422', // BIN (MBBank = 970422, Vietcombank = 970436) or bank key e.g. 'mbbank'
    'account_holder' => 'NGUYEN VAN A',

    // QR style: 'qr_only' = bare QR (/qrpay), anything else = VietQR card (/qrcard).
    'qr_template' => 'compact',

    // Default order amount (VND). Used as fallback if LadiPage form doesn't post 'amount'.
    'default_amount' => 100000,

    // Reference-code prefix. Final code = PREFIX + zero-padded id, e.g. NP000123.
    'ref_prefix' => 'NP',

    // QR / payment expiry in seconds (countdown shown on qr-page.php).
    'expiry_seconds' => 900, // 15 minutes

    // Where SQLite DB will be stored (must be writable, OUTSIDE webroot ideally).
    'db_path' => __DIR__ . '/data/npay.sqlite',

    // Public base URL of this plugin (no trailing slash). Used for redirects.
    // Example: https://yourdomain.com/npay
    'base_url' => 'https://yourdomain.com/npay',

    // NPay endpoints (rarely needs changing).
    'npay_api_base' => 'https://api.npay.vn',
    'npay_qr_base'  => 'https://qr.npay.vn',
    'npay_site'     => 'https://npay.vn',

    // Optional: whitelist of IPs allowed to POST to webhook.php?source=npay.
    // Leave empty to disable IP check (signature is still verified).
    'npay_webhook_ips' => [],

    // Timezone for created_at / paid_at display.
    'timezone' => 'Asia/Ho_Chi_Minh',
];
