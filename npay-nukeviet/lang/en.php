<?php

/**
 * NPay Payment Gateway — English language file
 *
 * @package npay-nukeviet
 */

if (!defined('NV_MAINFILE') && !defined('NV_IS_MOD_SHOPS') && !defined('NV_ADMIN')) {
    die('Stop!!!');
}

$lang_translator['author']     = 'NPay <support@npay.vn>';
$lang_translator['createdate'] = '01/01/2024, 00:00';
$lang_translator['copyright']  = '@Copyright (C) 2024 NPay. All rights reserved';
$lang_translator['info']       = 'NPay payment gateway for NukeViet';
$lang_translator['langtype']   = 'lang_module';

$lang_module['npay_title']                = 'Pay with NPay';
$lang_module['npay_description']          = 'Automatic bank transfer payment';
$lang_module['npay_instruction_heading']  = 'Payment instructions';
$lang_module['npay_instruction_intro']    = 'Please scan the QR code or transfer using the details below. Your order will be confirmed automatically once we receive the payment.';
$lang_module['npay_bank']                 = 'Bank';
$lang_module['npay_account_number']       = 'Account number';
$lang_module['npay_account_name']         = 'Account holder';
$lang_module['npay_amount']               = 'Amount';
$lang_module['npay_currency']             = 'VND';
$lang_module['npay_description_label']    = 'Transfer message';
$lang_module['npay_description_warning']  = 'Please copy the transfer message exactly so we can reconcile your payment automatically.';
$lang_module['npay_scan_qr']              = 'Scan the QR code to pay';
$lang_module['npay_waiting']              = 'Waiting for payment...';
$lang_module['npay_paid']                 = 'Payment confirmed!';
$lang_module['npay_copy']                 = 'Copy';
$lang_module['npay_copied']               = 'Copied';
$lang_module['npay_order_id']             = 'Order ID';
$lang_module['npay_pay_id']               = 'Payment code';
$lang_module['npay_redirecting']          = 'Redirecting to the confirmation page...';

// Admin / settings
$lang_module['npay_config_title']         = 'NPay payment gateway configuration';
$lang_module['npay_api_base']             = 'NPay API endpoint';
$lang_module['npay_api_token']            = 'API Token (Apikey)';
$lang_module['npay_bank_code']            = 'Bank code (e.g. VCB, VTB, TCB)';
$lang_module['npay_account_number_cfg']   = 'Receiving account number';
$lang_module['npay_account_name_cfg']     = 'Account holder name';
$lang_module['npay_prefix']               = 'Payment code prefix (e.g. NPAY)';
$lang_module['npay_qr_template']          = 'QR template (compact/qr_only/print)';
$lang_module['npay_webhook_url']          = 'NPay webhook URL';
$lang_module['npay_save_success']         = 'NPay configuration saved successfully.';
