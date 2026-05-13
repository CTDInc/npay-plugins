<?php

/**
 * NPay Payment Gateway for NukeViet Shops Module
 *
 * @package    npay-nukeviet
 * @author     NPay <support@npay.vn>
 * @copyright  Copyright (C) 2024 NPay. All rights reserved.
 * @license    GNU/GPL v2 or later
 * @version    1.0.0
 *
 * File: modules/shops/payment_gateway/npay.php
 */

if (!defined('NV_IS_MOD_SHOPS')) {
    die('Stop!!!');
}

/**
 * NPay payment method configuration.
 *
 * Loaded by the shops module when listing/registering payment gateways.
 * Keys follow the NukeViet 4.x shops payment_gateway convention.
 */
$payment_npay = array(
    'name'        => 'NPay',
    'description' => 'Thanh toán tự động qua chuyển khoản ngân hàng (NPay)',
    'logo'        => NV_BASE_SITEURL . 'modules/shops/payment_gateway/npay_logo.png',
    'version'     => '1.0.0',
    'author'      => 'NPay <support@npay.vn>',
    'website'     => 'https://npay.vn',
    'config_keys' => array(
        'npay_api_base'       => 'https://api.npay.vn',
        'npay_api_token'      => '',
        'npay_bank_code'      => '',
        'npay_account_number' => '',
        'npay_account_name'   => '',
        'npay_prefix'         => 'NPAY',
        'npay_qr_template'    => 'compact',
    ),
    // Callable used by the shops module to render the payment instruction
    // page after the customer chooses NPay as their payment method.
    'callable'    => 'npay_payment_render',
);

if (!function_exists('npay_payment_render')) {

    /**
     * Render the payment instruction screen for a shop order.
     *
     * NukeViet's shops module invokes this callable with the order array
     * once the customer selects NPay. The function returns the HTML for
     * the checkout instruction block (QR code, bank info, polling JS).
     *
     * @param array $order Shop order row from nv4_vi_shops_orders
     * @return string Rendered HTML fragment
     */
    function npay_payment_render($order = array())
    {
        global $module_info, $module_name, $module_file, $global_config, $lang_module, $db;

        // Load language strings for this gateway.
        if (file_exists(NV_ROOTDIR . '/modules/shops/payment_gateway/lang/' . NV_LANG_INTERFACE . '.php')) {
            include NV_ROOTDIR . '/modules/shops/payment_gateway/lang/' . NV_LANG_INTERFACE . '.php';
        } elseif (file_exists(NV_ROOTDIR . '/modules/shops/payment_gateway/lang/vi.php')) {
            include NV_ROOTDIR . '/modules/shops/payment_gateway/lang/vi.php';
        }

        // Pull module config values written by install.php / admin.
        $cfg = npay_get_config();

        $order_id = isset($order['order_id']) ? (int) $order['order_id'] : 0;
        $pay_id   = isset($order['pay_id']) ? (string) $order['pay_id'] : '';
        $amount   = isset($order['total_amount']) ? (float) $order['total_amount'] : 0;

        // Build a deterministic transfer code so the webhook can match the
        // incoming bank message to the originating order.
        if (empty($pay_id)) {
            $pay_id = npay_generate_pay_id($cfg['npay_prefix'], $order_id);
        }

        $des = $cfg['npay_prefix'] . $pay_id;

        // VietQR (npay) image URL.
        $qr_url = 'https://qr.npay.vn/img?'
            . 'acc=' . rawurlencode($cfg['npay_account_number'])
            . '&bank=' . rawurlencode($cfg['npay_bank_code'])
            . '&amount=' . rawurlencode((string) $amount)
            . '&des=' . rawurlencode($des)
            . '&template=' . rawurlencode($cfg['npay_qr_template']);

        // Polling endpoint the JS will use to detect successful payment.
        $poll_url = NV_BASE_SITEURL . 'modules/shops/payment_gateway/npay/webhook.php?action=status&pay_id=' . rawurlencode($pay_id);

        // Render via Xtemplate.
        $tpl_dir = NV_ROOTDIR . '/modules/shops/payment_gateway/npay/templates';
        if (!file_exists($tpl_dir . '/payment_instruction.tpl')) {
            $tpl_dir = NV_ROOTDIR . '/themes/' . $global_config['module_theme'] . '/modules/shops/payment_gateway';
        }

        $xtpl = new XTemplate('payment_instruction.tpl', $tpl_dir);
        $xtpl->assign('LANG', isset($lang_module) ? $lang_module : array());
        $xtpl->assign('DATA', array(
            'qr_url'         => nv_htmlspecialchars($qr_url),
            'poll_url'       => nv_htmlspecialchars($poll_url),
            'bank_code'      => nv_htmlspecialchars($cfg['npay_bank_code']),
            'account_number' => nv_htmlspecialchars($cfg['npay_account_number']),
            'account_name'   => nv_htmlspecialchars($cfg['npay_account_name']),
            'amount'         => number_format($amount, 0, ',', '.'),
            'amount_raw'     => $amount,
            'description'    => nv_htmlspecialchars($des),
            'pay_id'         => nv_htmlspecialchars($pay_id),
            'order_id'       => $order_id,
            'css_url'        => NV_BASE_SITEURL . 'modules/shops/payment_gateway/npay/assets/css/npay.css',
            'js_url'         => NV_BASE_SITEURL . 'modules/shops/payment_gateway/npay/assets/js/npay-poll.js',
        ));
        $xtpl->parse('main');
        return $xtpl->text('main');
    }
}

if (!function_exists('npay_get_config')) {

    /**
     * Load NPay gateway settings from nv4_config.
     *
     * @return array
     */
    function npay_get_config()
    {
        global $db;

        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $defaults = array(
            'npay_api_base'       => 'https://api.npay.vn',
            'npay_api_token'      => '',
            'npay_bank_code'      => '',
            'npay_account_number' => '',
            'npay_account_name'   => '',
            'npay_prefix'         => 'NPAY',
            'npay_qr_template'    => 'compact',
        );

        try {
            $sql = "SELECT config_name, config_value FROM " . NV_CONFIG_GLOBALTABLE
                 . " WHERE lang='sys' AND module='shops' AND config_name LIKE 'npay_%'";
            $result = $db->query($sql);
            while ($row = $result->fetch()) {
                $defaults[$row['config_name']] = $row['config_value'];
            }
        } catch (Exception $e) {
            // Silently fall back to defaults if config table is unavailable.
        }

        $cache = $defaults;
        return $cache;
    }
}

if (!function_exists('npay_generate_pay_id')) {

    /**
     * Deterministic transfer code based on order id + prefix.
     *
     * @param string $prefix
     * @param int    $order_id
     * @return string
     */
    function npay_generate_pay_id($prefix, $order_id)
    {
        $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $prefix));
        if ($prefix === '') {
            $prefix = 'NPAY';
        }
        return $prefix . str_pad((string) (int) $order_id, 6, '0', STR_PAD_LEFT);
    }
}
