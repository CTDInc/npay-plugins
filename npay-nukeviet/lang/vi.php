<?php

/**
 * NPay Payment Gateway — Vietnamese language file
 *
 * @package npay-nukeviet
 */

if (!defined('NV_MAINFILE') && !defined('NV_IS_MOD_SHOPS') && !defined('NV_ADMIN')) {
    die('Stop!!!');
}

$lang_translator['author']      = 'NPay <support@npay.vn>';
$lang_translator['createdate']  = '01/01/2024, 00:00';
$lang_translator['copyright']   = '@Copyright (C) 2024 NPay. All rights reserved';
$lang_translator['info']        = 'NPay payment gateway for NukeViet';
$lang_translator['langtype']    = 'lang_module';

$lang_module['npay_title']                = 'Thanh toán qua NPay';
$lang_module['npay_description']          = 'Thanh toán tự động qua chuyển khoản ngân hàng';
$lang_module['npay_instruction_heading']  = 'Hướng dẫn thanh toán';
$lang_module['npay_instruction_intro']    = 'Vui lòng quét mã QR hoặc chuyển khoản theo thông tin bên dưới. Đơn hàng sẽ tự động được xác nhận sau khi chúng tôi nhận được tiền.';
$lang_module['npay_bank']                 = 'Ngân hàng';
$lang_module['npay_account_number']       = 'Số tài khoản';
$lang_module['npay_account_name']         = 'Chủ tài khoản';
$lang_module['npay_amount']               = 'Số tiền';
$lang_module['npay_currency']             = 'VNĐ';
$lang_module['npay_description_label']    = 'Nội dung chuyển khoản';
$lang_module['npay_description_warning']  = 'Vui lòng ghi chính xác nội dung chuyển khoản để hệ thống tự động đối soát.';
$lang_module['npay_scan_qr']              = 'Quét mã QR để thanh toán';
$lang_module['npay_waiting']              = 'Đang chờ thanh toán...';
$lang_module['npay_paid']                 = 'Đã thanh toán thành công!';
$lang_module['npay_copy']                 = 'Sao chép';
$lang_module['npay_copied']               = 'Đã sao chép';
$lang_module['npay_order_id']             = 'Mã đơn hàng';
$lang_module['npay_pay_id']               = 'Mã thanh toán';
$lang_module['npay_redirecting']          = 'Đang chuyển hướng đến trang xác nhận...';

// Admin / settings
$lang_module['npay_config_title']         = 'Cấu hình cổng thanh toán NPay';
$lang_module['npay_api_base']             = 'API endpoint của NPay';
$lang_module['npay_api_token']            = 'API Token (Apikey)';
$lang_module['npay_bank_code']            = 'Mã ngân hàng (vd: VCB, VTB, TCB)';
$lang_module['npay_account_number_cfg']   = 'Số tài khoản nhận tiền';
$lang_module['npay_account_name_cfg']     = 'Tên chủ tài khoản';
$lang_module['npay_prefix']               = 'Tiền tố mã thanh toán (vd: NPAY)';
$lang_module['npay_qr_template']          = 'Mẫu mã QR (compact/qr_only/print)';
$lang_module['npay_webhook_url']          = 'URL Webhook NPay';
$lang_module['npay_save_success']         = 'Đã lưu cấu hình NPay thành công.';
