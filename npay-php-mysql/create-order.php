<?php
/**
 * create-order.php — Tạo đơn pending và trả về QR + payment page.
 *
 * Request:
 *   GET/POST amount=100000
 *
 * Response (JSON):
 *   {
 *     "success": true,
 *     "code":    "NPAY12",
 *     "amount":  100000,
 *     "qr_url":  "https://qr.npay.vn/img?...",
 *     "pay_url": "https://yourdomain.tld/npay/pay.php?code=NPAY12",
 *     "status_url": "https://yourdomain.tld/npay/status.php?code=NPAY12"
 *   }
 */

declare(strict_types=1);

require __DIR__ . '/lib/Database.php';
require __DIR__ . '/lib/NPay.php';

use NPay\Database;
use NPay\NPay;

$cfg = Database::config();
date_default_timezone_set($cfg['app']['timezone'] ?? 'Asia/Ho_Chi_Minh');

$amount = (float)($_REQUEST['amount'] ?? 0);
if ($amount < 1000) {
    NPay::json(['success' => false, 'message' => 'amount tối thiểu 1.000đ'], 422);
}

$pdo = Database::pdo();

try {
    $pdo->beginTransaction();

    // Tạo bản ghi trống để lấy id, rồi update code
    $ins = $pdo->prepare("INSERT INTO tb_orders (code, amount, status) VALUES (:c, :a, 'pending')");
    $placeholder = 'TMP' . bin2hex(random_bytes(6));
    $ins->execute([':c' => $placeholder, ':a' => $amount]);
    $id   = (int)$pdo->lastInsertId();
    $code = NPay::generateCode($id);

    $upd = $pdo->prepare("UPDATE tb_orders SET code = :c WHERE id = :id");
    $upd->execute([':c' => $code, ':id' => $id]);

    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    NPay::json(['success' => false, 'message' => $e->getMessage()], 500);
}

$base = rtrim($cfg['app']['base_url'] ?? '', '/');

NPay::json([
    'success'    => true,
    'order_id'   => $id,
    'code'       => $code,
    'amount'     => $amount,
    'qr_url'     => NPay::qrUrl($code, $amount),
    'pay_url'    => $base . '/pay.php?code=' . urlencode($code),
    'status_url' => $base . '/status.php?code=' . urlencode($code),
    'expires_in' => (int)($cfg['app']['order_ttl'] ?? 900),
]);
