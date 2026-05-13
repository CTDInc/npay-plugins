<?php

return [
    'api_base' => env('NPAY_API_BASE', 'https://api.npay.vn'),
    'qr_base' => env('NPAY_QR_BASE', 'https://qr.npay.vn/img'),
    'dashboard_url' => env('NPAY_DASHBOARD_URL', 'https://app.npay.vn'),
    'default_template' => env('NPAY_QR_TEMPLATE', 'compact'),
    'webhook_path' => 'api/webhooks/npay',
    'order_prefix' => env('NPAY_ORDER_PREFIX', 'NPAY'),
    'poll_interval' => 5000,
];
