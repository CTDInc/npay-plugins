<?php
/**
 * webhook.php — Endpoint nhận webhook từ NPay.
 *
 * Bảo mật:
 *   1. Authorization: Apikey <api_token>      (bắt buộc)
 *   2. X-NPay-Signature: <hmac-sha256-hex>    (tùy chọn, nếu hmac_secret được set)
 *
 * Idempotent:
 *   reference_number có UNIQUE index → INSERT IGNORE → trả 200 OK nếu trùng.
 *
 * Đối soát đơn hàng:
 *   - Trích NPAY{xxx} từ `content`
 *   - Tìm đơn pending có code đó, amount khớp ⇒ paid.
 *
 * Response:
 *   200 {success:true, ...}
 *   401 {success:false, message:"unauthorized"}
 *   422 {success:false, message:"invalid payload"}
 */

declare(strict_types=1);

require __DIR__ . '/lib/Database.php';
require __DIR__ . '/lib/NPay.php';

use NPay\Database;
use NPay\NPay;

$cfg = Database::config();
date_default_timezone_set($cfg['app']['timezone'] ?? 'Asia/Ho_Chi_Minh');

// --- 1. Đọc raw body ---------------------------------------------------------
$raw = file_get_contents('php://input') ?: '';

// --- 2. Auth: Apikey ---------------------------------------------------------
$authHeader = NPay::header('Authorization');
$expected   = $cfg['webhook']['api_token'] ?? '';
if (!NPay::verifyApiKey($authHeader, $expected)) {
    NPay::json(['success' => false, 'message' => 'unauthorized'], 401);
}

// --- 3. (Optional) HMAC ------------------------------------------------------
$secret = (string)($cfg['webhook']['hmac_secret'] ?? '');
if ($secret !== '') {
    $sig = NPay::header('X-NPay-Signature');
    if (!NPay::verifySignature($raw, $sig, $secret)) {
        NPay::json(['success' => false, 'message' => 'bad signature'], 401);
    }
}

// --- 4. (Optional) IP allowlist ---------------------------------------------
$allowIps = $cfg['webhook']['allow_ip'] ?? [];
if (!empty($allowIps)) {
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $ok = false;
    foreach ($allowIps as $cidr) {
        if (ip_in_cidr($clientIp, $cidr)) { $ok = true; break; }
    }
    if (!$ok) {
        NPay::json(['success' => false, 'message' => 'ip not allowed'], 403);
    }
}

// --- 5. Parse JSON -----------------------------------------------------------
$data = json_decode($raw, true);
if (!is_array($data)) {
    NPay::json(['success' => false, 'message' => 'invalid json'], 422);
}

// Chuẩn hoá trường theo doc SePay/NPay (compat).
$gateway          = (string)($data['gateway']            ?? '');
$transactionDate  = (string)($data['transactionDate']    ?? $data['transaction_date'] ?? date('Y-m-d H:i:s'));
$accountNumber    = (string)($data['accountNumber']      ?? $data['account_number']   ?? '');
$subAccount       = $data['subAccount']                   ?? $data['sub_account']      ?? null;
$amountIn         = (float)($data['transferAmount']      ?? $data['amount_in']        ?? 0);
$amountOut        = (float)($data['amount_out']          ?? 0);
if (($data['transferType'] ?? '') === 'out') {
    $amountOut = (float)($data['transferAmount'] ?? $amountIn);
    $amountIn  = 0.0;
}
$accumulated      = (float)($data['accumulated']         ?? 0);
$code             = $data['code']                         ?? null;
$content          = (string)($data['content']            ?? $data['transaction_content'] ?? '');
$referenceNumber  = (string)($data['referenceCode']      ?? $data['reference_number']    ?? '');
$bodyText         = isset($data['description']) ? (string)$data['description'] : $raw;

if ($referenceNumber === '') {
    NPay::json(['success' => false, 'message' => 'missing referenceCode'], 422);
}

// Auto-detect mã đơn nếu webhook không gửi code
if (!$code) {
    $code = NPay::extractOrderCode($content, $cfg['app']['code_prefix'] ?? 'NPAY');
}

$pdo = Database::pdo();

// --- 6. Insert idempotent ----------------------------------------------------
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO tb_transactions
           (gateway, transaction_date, account_number, sub_account,
            amount_in, amount_out, accumulated, code,
            transaction_content, reference_number, body)
         VALUES
           (:gateway, :tdate, :acc, :sub, :ain, :aout, :accum,
            :code, :content, :ref, :body)"
    );
    $stmt->execute([
        ':gateway' => $gateway,
        ':tdate'   => $transactionDate,
        ':acc'     => $accountNumber,
        ':sub'     => $subAccount,
        ':ain'     => $amountIn,
        ':aout'    => $amountOut,
        ':accum'   => $accumulated,
        ':code'    => $code,
        ':content' => $content,
        ':ref'     => $referenceNumber,
        ':body'    => $bodyText,
    ]);
    $isNew = $stmt->rowCount() > 0;
    $txId  = $isNew ? (int)$pdo->lastInsertId() : 0;

    if (!$isNew) {
        // Lấy id giao dịch đã tồn tại
        $q = $pdo->prepare("SELECT id FROM tb_transactions WHERE reference_number = :ref LIMIT 1");
        $q->execute([':ref' => $referenceNumber]);
        $txId = (int)($q->fetchColumn() ?: 0);
    }

    // --- 7. Đối soát đơn hàng (chỉ với tiền vào & lần đầu) ------------------
    $matched = null;
    if ($isNew && $amountIn > 0 && $code) {
        $find = $pdo->prepare(
            "SELECT id, amount FROM tb_orders
              WHERE code = :code AND status = 'pending'
              LIMIT 1 FOR UPDATE"
        );
        $find->execute([':code' => $code]);
        $order = $find->fetch();
        if ($order && abs((float)$order['amount'] - $amountIn) < 0.01) {
            $upd = $pdo->prepare(
                "UPDATE tb_orders
                    SET status = 'paid',
                        paid_transaction_id = :tx,
                        paid_at = NOW()
                  WHERE id = :id AND status = 'pending'"
            );
            $upd->execute([':tx' => $txId, ':id' => $order['id']]);
            $matched = ['id' => (int)$order['id'], 'code' => $code];
        }
    }

    $pdo->commit();

    NPay::json([
        'success'         => true,
        'duplicated'      => !$isNew,
        'transaction_id'  => $txId,
        'matched_order'   => $matched,
    ]);
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[npay-webhook] ' . $e->getMessage());
    NPay::json(['success' => false, 'message' => 'server error'], 500);
}

// ---------------------------------------------------------------------------
function ip_in_cidr(string $ip, string $cidr): bool
{
    if (strpos($cidr, '/') === false) {
        return $ip === $cidr;
    }
    [$subnet, $bits] = explode('/', $cidr, 2);
    $ipL  = ip2long($ip);
    $snL  = ip2long($subnet);
    if ($ipL === false || $snL === false) return false;
    $mask = -1 << (32 - (int)$bits);
    return ($ipL & $mask) === ($snL & $mask);
}
