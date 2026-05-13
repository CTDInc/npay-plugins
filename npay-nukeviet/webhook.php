<?php

/**
 * NPay Webhook Receiver for NukeViet Shops
 *
 * Standalone endpoint reachable at:
 *   /modules/shops/payment_gateway/npay/webhook.php
 *
 * Bootstraps NukeViet, validates the Authorization header against the
 * configured API token, looks up the shop order by `pay_id` embedded in
 * the bank message content/code, and marks the order as paid.
 *
 * Also exposes a small `?action=status&pay_id=...` polling endpoint used
 * by assets/js/npay-poll.js on the checkout page.
 */

define('NV_SYSTEM', true);

// Locate NukeViet root. This file lives at:
//   modules/shops/payment_gateway/npay/webhook.php
// so NV_ROOTDIR is 4 levels up.
if (!defined('NV_ROOTDIR')) {
    define('NV_ROOTDIR', realpath(dirname(__FILE__) . '/../../../../'));
}

require NV_ROOTDIR . '/includes/mainfile.php';

// Always respond JSON.
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

/**
 * Emit a JSON response and terminate.
 *
 * @param int   $code
 * @param array $payload
 */
function npay_json_response($code, array $payload)
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit(0);
}

/**
 * Read the Authorization header in a server-portable way.
 *
 * @return string
 */
function npay_get_auth_header()
{
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        return trim($_SERVER['HTTP_AUTHORIZATION']);
    }
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }
    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $k => $v) {
            if (strcasecmp($k, 'Authorization') === 0) {
                return trim($v);
            }
        }
    }
    return '';
}

/**
 * Load NPay gateway settings out of nv4_config.
 *
 * @return array
 */
function npay_load_config()
{
    global $db;

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
        // Keep defaults.
    }

    return $defaults;
}

/**
 * Extract the pay_id (e.g. NPAY000123) from a free-form bank message.
 *
 * @param string $haystack
 * @param string $prefix
 * @return string|null
 */
function npay_extract_pay_id($haystack, $prefix)
{
    $prefix = preg_quote(strtoupper((string) $prefix), '/');
    if ($prefix === '') {
        $prefix = 'NPAY';
    }
    if (preg_match('/(' . $prefix . '[A-Z0-9]+)/i', strtoupper((string) $haystack), $m)) {
        return strtoupper($m[1]);
    }
    return null;
}

/**
 * Find an order row by its NPay pay_id transfer code.
 *
 * @param string $pay_id
 * @return array|null
 */
function npay_find_order_by_pay_id($pay_id)
{
    global $db;

    $pay_id = (string) $pay_id;
    if ($pay_id === '') {
        return null;
    }

    try {
        $sth = $db->prepare("SELECT order_id, pay_id, status, total_amount FROM "
            . NV_PREFIXLANG . "_shops_orders WHERE pay_id = :pay_id LIMIT 1");
        $sth->bindParam(':pay_id', $pay_id, PDO::PARAM_STR);
        $sth->execute();
        $row = $sth->fetch();
        return $row ? $row : null;
    } catch (Exception $e) {
        return null;
    }
}

// ---------------------------------------------------------------------------
// Lightweight status endpoint (used by the polling JS).
// ---------------------------------------------------------------------------
$action = isset($_GET['action']) ? (string) $_GET['action'] : '';
if ($action === 'status') {
    $pay_id = isset($_GET['pay_id']) ? (string) $_GET['pay_id'] : '';
    $order  = npay_find_order_by_pay_id($pay_id);
    if (!$order) {
        npay_json_response(404, array('success' => false, 'error' => 'order_not_found'));
    }
    npay_json_response(200, array(
        'success'  => true,
        'pay_id'   => $order['pay_id'],
        'order_id' => (int) $order['order_id'],
        'status'   => $order['status'],
        'paid'     => ($order['status'] === 'paid'),
    ));
}

// ---------------------------------------------------------------------------
// Webhook POST handler.
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    npay_json_response(405, array('success' => false, 'error' => 'method_not_allowed'));
}

$cfg = npay_load_config();

// 1. Validate Authorization: Apikey <token>
$auth = npay_get_auth_header();
$expected = 'Apikey ' . (string) $cfg['npay_api_token'];
if (empty($cfg['npay_api_token']) || !hash_equals($expected, $auth)) {
    npay_json_response(401, array('success' => false, 'error' => 'unauthorized'));
}

// 2. Parse JSON body.
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    npay_json_response(400, array('success' => false, 'error' => 'invalid_json'));
}

// 3. Only react to incoming credit transactions.
$transfer_type = isset($payload['transferType']) ? strtolower((string) $payload['transferType']) : '';
if ($transfer_type !== 'in') {
    npay_json_response(200, array('success' => true, 'skipped' => 'not_incoming'));
}

$amount  = isset($payload['transferAmount']) ? (float) $payload['transferAmount'] : 0;
$content = isset($payload['content']) ? (string) $payload['content'] : '';
$code    = isset($payload['code']) ? (string) $payload['code'] : '';
$ref     = isset($payload['referenceCode']) ? (string) $payload['referenceCode'] : '';
$gateway = isset($payload['gateway']) ? (string) $payload['gateway'] : '';
$tx_date = isset($payload['transactionDate']) ? (string) $payload['transactionDate'] : '';

// 4. Resolve the pay_id — npay sends it in `code`, but some banks bury it in
//    the free-form `content` field.
$prefix  = isset($cfg['npay_prefix']) ? $cfg['npay_prefix'] : 'NPAY';
$pay_id  = npay_extract_pay_id($code, $prefix);
if (!$pay_id) {
    $pay_id = npay_extract_pay_id($content, $prefix);
}
if (!$pay_id) {
    npay_json_response(422, array('success' => false, 'error' => 'pay_id_not_found'));
}

// 5. Match against the orders table.
$order = npay_find_order_by_pay_id($pay_id);
if (!$order) {
    npay_json_response(404, array('success' => false, 'error' => 'order_not_found', 'pay_id' => $pay_id));
}

if ($order['status'] === 'paid') {
    npay_json_response(200, array('success' => true, 'duplicate' => true, 'pay_id' => $pay_id));
}

// 6. Optionally check the amount (informational — partial pays still recorded).
$expected_amount = (float) $order['total_amount'];
if ($expected_amount > 0 && abs($expected_amount - $amount) > 0.01) {
    // Record but don't auto-mark as paid if underpaid.
    if ($amount + 0.01 < $expected_amount) {
        npay_json_response(200, array(
            'success'        => true,
            'pay_id'         => $pay_id,
            'status'         => 'underpaid',
            'paid_amount'    => $amount,
            'expected'       => $expected_amount,
        ));
    }
}

// 7. Mark order as paid.
try {
    $now = NV_CURRENTTIME;
    $sql = "UPDATE " . NV_PREFIXLANG . "_shops_orders "
         . "SET status='paid', payment_time=" . (int) $now . ", "
         . "payment_gateway=" . $db->quote('npay') . ", "
         . "payment_reference=" . $db->quote($ref) . " "
         . "WHERE order_id=" . (int) $order['order_id'];
    $db->query($sql);
} catch (Exception $e) {
    // Fall back to minimal column set if extra columns aren't present.
    try {
        $sql = "UPDATE " . NV_PREFIXLANG . "_shops_orders "
             . "SET status='paid' WHERE order_id=" . (int) $order['order_id'];
        $db->query($sql);
    } catch (Exception $e2) {
        npay_json_response(500, array('success' => false, 'error' => 'db_update_failed'));
    }
}

// 8. Append a log row if a log table is available.
try {
    $log_sql = "INSERT INTO " . NV_PREFIXLANG . "_shops_payment_log "
        . "(gateway, pay_id, order_id, amount, reference, bank, tx_date, raw, created_at) VALUES ("
        . $db->quote('npay') . ", "
        . $db->quote($pay_id) . ", "
        . (int) $order['order_id'] . ", "
        . (float) $amount . ", "
        . $db->quote($ref) . ", "
        . $db->quote($gateway) . ", "
        . $db->quote($tx_date) . ", "
        . $db->quote($raw) . ", "
        . (int) NV_CURRENTTIME . ")";
    $db->query($log_sql);
} catch (Exception $e) {
    // Log table is optional.
}

npay_json_response(200, array(
    'success'  => true,
    'pay_id'   => $pay_id,
    'order_id' => (int) $order['order_id'],
    'amount'   => $amount,
    'status'   => 'paid',
));
