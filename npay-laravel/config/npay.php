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
    'dashboard_url' => env('NPAY_DASHBOARD_URL', 'https://npay.vn'),

    // API token (zna_…) gọi Public API v1, gửi dạng `Authorization: Bearer <token>`
    'api_token' => env('NPAY_API_TOKEN'),

    // Thông tin tài khoản ngân hàng nhận tiền
    'account_number' => env('NPAY_ACCOUNT_NUMBER'),
    'bank_bin' => env('NPAY_BANK_BIN'),
    'account_holder' => env('NPAY_ACCOUNT_HOLDER'),

    // Xác thực webhook — hợp lệ khi khớp một trong hai (cấu hình ít nhất một):
    //  - webhook_token: API key của webhook, NPay gửi `Authorization: Apikey <token>`
    //  - webhook_secret: webhook secret trên dashboard (bật "Ký request"), kiểm
    //    `X-Npay-Signature` = hex HMAC-SHA256 của raw body
    'webhook_token' => env('NPAY_WEBHOOK_TOKEN'),
    'webhook_secret' => env('NPAY_WEBHOOK_SECRET'),

    // Độ lệch tối đa (giây) của `X-Npay-Timestamp` khi kiểm chữ ký
    'webhook_tolerance' => (int) env('NPAY_WEBHOOK_TOLERANCE', 300),

    // Template QR mặc định: `qr_only` = chỉ mã QR (/qrpay), còn lại = thẻ VietQR (/qrcard)
    'default_template' => env('NPAY_DEFAULT_TEMPLATE', 'compact'),

    // Đường dẫn route webhook
    'webhook_route' => env('NPAY_WEBHOOK_ROUTE', 'npay/webhook'),

    // Prefix sinh mã thanh toán
    'code_prefix' => env('NPAY_CODE_PREFIX', 'NPAY'),

    // Có tự động lưu giao dịch vào DB khi nhận webhook không
    'store_transactions' => env('NPAY_STORE_TRANSACTIONS', true),
];
