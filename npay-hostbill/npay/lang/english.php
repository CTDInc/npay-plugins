<?php
/**
 * NPay HostBill Gateway – English language file
 */

$_LANG = [
    'name'                 => 'NPay',
    'description'          => 'NPay – Automatic Vietnamese bank transfer payment gateway.',
    'pay_heading'          => 'Pay via NPay',
    'pay_instructions'     => 'Scan the QR code below, or transfer using the bank info:',
    'bank'                 => 'Bank',
    'account_number'       => 'Account number',
    'amount'               => 'Amount',
    'transfer_content'     => 'Transfer note',
    'auto_update_note'     => 'Your invoice will be marked as paid automatically once NPay confirms the transfer.',
    'qr_alt'               => 'NPay VietQR code',
    'cfg_api_token'        => 'API Token (Apikey)',
    'cfg_api_token_help'   => 'Webhook authentication token provided by NPay. Header: Authorization: Apikey <token>',
    'cfg_account_number'   => 'Receiving bank account number',
    'cfg_bank_bin'         => 'Bank BIN code',
    'cfg_bank_bin_help'    => 'NAPAS bank BIN (e.g. 970415 = VietinBank).',
    'cfg_bank_short_name'  => 'Bank short name',
    'cfg_qr_template'      => 'QR template',
    'cfg_prefix_code'      => 'Transaction code prefix',
    'err_unauthorized'     => 'Unauthorized webhook request.',
    'err_invalid_payload'  => 'Invalid JSON payload.',
    'err_no_invoice'       => 'Cannot match invoice from code/content.',
    'msg_payment_recorded' => 'Payment recorded successfully.',
];
