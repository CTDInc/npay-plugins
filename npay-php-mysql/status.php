<?php
/**
 * status.php?code=NPAYxx — Trả JSON trạng thái cho JS poll.
 */

declare(strict_types=1);

require __DIR__ . '/lib/Database.php';
require __DIR__ . '/lib/NPay.php';

use NPay\Database;
use NPay\NPay;

$code = strtoupper(trim((string)($_GET['code'] ?? '')));
if ($code === '') {
    NPay::json(['success' => false, 'message' => 'missing code'], 400);
}

$pdo = Database::pdo();
$stmt = $pdo->prepare("SELECT status, paid_at, amount FROM tb_orders WHERE code = :c LIMIT 1");
$stmt->execute([':c' => $code]);
$row = $stmt->fetch();

if (!$row) {
    NPay::json(['success' => false, 'message' => 'not found'], 404);
}

NPay::json([
    'success' => true,
    'code'    => $code,
    'status'  => $row['status'],
    'amount'  => (float)$row['amount'],
    'paid_at' => $row['paid_at'],
]);
