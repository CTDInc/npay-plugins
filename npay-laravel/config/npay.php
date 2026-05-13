<?php

/*
|--------------------------------------------------------------------------
| NPay Configuration
|--------------------------------------------------------------------------
|
| File cấu hình mặc định cho gói NPay Laravel. Mọi giá trị có thể được
| ghi đè bằng các biến môi trường tương ứng trong file `.env`.
|
*/

return [
    // Base URL API NPay
    'api_base' => env('NPAY_API_BASE', 'https://api.npay.vn'),

    // Base URL dịch vụ tạo QR (VietQR-compatible)
    'qr_base' => env('NPAY_QR_BASE', 'https://qr.npay.vn'),

    // URL trang quản trị NPay
    'dashboard_url' => env('NPAY_DASHBOARD_URL', 'https://my.npay.vn'),

    // API token (Apikey) để gọi các endpoint của NPay
    'api_token' => env('NPAY_API_TOKEN'),

    // Thông tin tài khoản ngân hàng nhận tiền
    'account_number' => env('NPAY_ACCOUNT_NUMBER'),
    'bank_bin' => env('NPAY_BANK_BIN'),
    'account_holder' => env('NPAY_ACCOUNT_HOLDER'),

    // Token webhook để xác thực header `Authorization: Apikey <token>`
    'webhook_token' => env('NPAY_WEBHOOK_TOKEN'),

    // Template QR mặc định (compact|compact2|qr_only|print)
    'default_template' => env('NPAY_DEFAULT_TEMPLATE', 'compact'),

    // Đường dẫn route webhook
    'webhook_route' => env('NPAY_WEBHOOK_ROUTE', 'npay/webhook'),

    // Prefix sinh mã thanh toán
    'code_prefix' => env('NPAY_CODE_PREFIX', 'NPAY'),

    // Có tự động lưu giao dịch vào DB khi nhận webhook không
    'store_transactions' => env('NPAY_STORE_TRANSACTIONS', true),
];
