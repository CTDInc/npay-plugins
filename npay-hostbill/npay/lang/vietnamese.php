<?php
/**
 * NPay HostBill Gateway – Vietnamese language file
 */

$_LANG = [
    'name'                 => 'NPay',
    'description'          => 'NPay – Cổng thanh toán chuyển khoản ngân hàng tự động.',
    'pay_heading'          => 'Thanh toán qua NPay',
    'pay_instructions'     => 'Quét mã QR bên dưới hoặc chuyển khoản theo thông tin:',
    'bank'                 => 'Ngân hàng',
    'account_number'       => 'Số tài khoản',
    'amount'               => 'Số tiền',
    'transfer_content'     => 'Nội dung chuyển khoản',
    'auto_update_note'     => 'Hóa đơn sẽ được cập nhật tự động sau khi NPay xác nhận giao dịch.',
    'qr_alt'               => 'Mã VietQR NPay',
    'cfg_api_token'        => 'API Token (Apikey)',
    'cfg_api_token_help'   => 'Token xác thực webhook do NPay cấp. Header: Authorization: Apikey <token>',
    'cfg_account_number'   => 'Số tài khoản nhận tiền',
    'cfg_bank_bin'         => 'Mã BIN ngân hàng',
    'cfg_bank_bin_help'    => 'Mã BIN theo chuẩn NAPAS (VD: 970415 = VietinBank).',
    'cfg_bank_short_name'  => 'Tên viết tắt ngân hàng',
    'cfg_qr_template'      => 'Mẫu QR',
    'cfg_prefix_code'      => 'Tiền tố mã giao dịch',
    'err_unauthorized'     => 'Webhook không được xác thực.',
    'err_invalid_payload'  => 'Payload JSON không hợp lệ.',
    'err_no_invoice'       => 'Không xác định được hóa đơn từ mã/nội dung.',
    'msg_payment_recorded' => 'Đã ghi nhận thanh toán thành công.',
];
