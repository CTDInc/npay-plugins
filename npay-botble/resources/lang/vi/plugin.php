<?php

return [
    'name' => 'NPay',
    'description' => 'Nhận thanh toán qua NPay (VietQR) với đối soát tự động.',
    'settings' => [
        'title' => 'Cấu hình NPay',
        'api_token' => 'Mã API Token',
        'api_token_helper' => 'API token của NPay dùng để xác thực webhook (Authorization: Apikey <token>).',
        'account_number' => 'Số tài khoản ngân hàng',
        'bank_bin' => 'Mã BIN ngân hàng',
        'account_holder' => 'Tên chủ tài khoản',
        'qr_template' => 'Mẫu QR',
        'qr_template_options' => [
            'compact' => 'Compact',
            'qr_only' => 'Chỉ QR',
            'print' => 'In',
        ],
        'save' => 'Lưu cấu hình',
    ],
    'payment' => [
        'title' => 'Thanh toán bằng NPay',
        'scan_qr' => 'Quét mã QR bằng ứng dụng ngân hàng',
        'or_transfer' => 'Hoặc chuyển khoản thủ công',
        'bank' => 'Ngân hàng',
        'account_number' => 'Số tài khoản',
        'account_holder' => 'Chủ tài khoản',
        'amount' => 'Số tiền',
        'memo' => 'Nội dung chuyển khoản',
        'memo_warning' => 'Vui lòng nhập đúng nội dung để hệ thống xác nhận thanh toán tự động.',
        'waiting' => 'Đang chờ thanh toán...',
        'completed' => 'Thanh toán thành công! Cảm ơn quý khách.',
    ],
    'webhook' => [
        'invalid_token' => 'Apikey token không hợp lệ hoặc thiếu.',
        'missing_code' => 'Thiếu mã giao dịch trong payload.',
        'not_found' => 'Không tìm thấy giao dịch phù hợp với mã.',
    ],
];
