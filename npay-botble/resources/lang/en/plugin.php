<?php

return [
    'name' => 'NPay',
    'description' => 'Accept payments via NPay (VietQR) with automatic reconciliation.',
    'settings' => [
        'title' => 'NPay Settings',
        'api_token' => 'API Token',
        'api_token_helper' => 'NPay API token used for webhook authentication (Authorization: Apikey <token>).',
        'account_number' => 'Bank account number',
        'bank_bin' => 'Bank BIN code',
        'account_holder' => 'Account holder name',
        'qr_template' => 'QR template',
        'qr_template_options' => [
            'compact' => 'Compact',
            'qr_only' => 'QR only',
            'print' => 'Print',
        ],
        'save' => 'Save settings',
    ],
    'payment' => [
        'title' => 'Pay with NPay',
        'scan_qr' => 'Scan QR code with your banking app',
        'or_transfer' => 'Or transfer manually',
        'bank' => 'Bank',
        'account_number' => 'Account number',
        'account_holder' => 'Account holder',
        'amount' => 'Amount',
        'memo' => 'Transfer memo',
        'memo_warning' => 'Please include the exact memo so we can confirm your payment automatically.',
        'waiting' => 'Waiting for payment...',
        'completed' => 'Payment completed! Thank you.',
    ],
    'webhook' => [
        'invalid_token' => 'Invalid or missing Apikey token.',
        'missing_code' => 'Missing transfer code/memo in payload.',
        'not_found' => 'No matching payment for code.',
    ],
];
