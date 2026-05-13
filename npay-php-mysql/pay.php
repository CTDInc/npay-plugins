<?php
/**
 * pay.php?code=NPAYxx — Trang hiển thị QR cho khách hàng quét.
 */

declare(strict_types=1);

require __DIR__ . '/lib/Database.php';
require __DIR__ . '/lib/NPay.php';

use NPay\Database;
use NPay\NPay;

$cfg = Database::config();
date_default_timezone_set($cfg['app']['timezone'] ?? 'Asia/Ho_Chi_Minh');

$code = strtoupper(trim((string)($_GET['code'] ?? '')));
if ($code === '') { http_response_code(400); exit('Missing code'); }

$pdo = Database::pdo();
$stmt = $pdo->prepare("SELECT * FROM tb_orders WHERE code = :c LIMIT 1");
$stmt->execute([':c' => $code]);
$order = $stmt->fetch();

if (!$order) { http_response_code(404); exit('Không tìm thấy đơn hàng'); }

$amount   = (float)$order['amount'];
$qrUrl    = NPay::qrUrl($code, $amount);
$account  = $cfg['account'] ?? [];
$ttl      = (int)($cfg['app']['order_ttl'] ?? 900);
$createdT = strtotime($order['created_at'] ?? 'now');
$remaining = max(0, ($createdT + $ttl) - time());
$status   = $order['status'];

$base = rtrim($cfg['app']['base_url'] ?? '', '/');
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Thanh toán <?= htmlspecialchars($code) ?> — NPay</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body data-code="<?= htmlspecialchars($code) ?>"
      data-status-url="<?= htmlspecialchars($base . '/status.php?code=' . urlencode($code)) ?>"
      data-remaining="<?= $remaining ?>">
  <main class="npay-pay">
    <header class="npay-header">
      <h1>NPay — Thanh toán đơn <?= htmlspecialchars($code) ?></h1>
      <p>Quét mã VietQR bằng app ngân hàng để thanh toán tự động.</p>
    </header>

    <section class="npay-card npay-status npay-status-<?= htmlspecialchars($status) ?>" id="status-box">
      <?php if ($status === 'paid'): ?>
        ✅ Đơn hàng đã được thanh toán.
      <?php elseif ($status === 'cancelled'): ?>
        ❌ Đơn hàng đã huỷ.
      <?php else: ?>
        ⏳ Đang chờ thanh toán — còn lại <span id="countdown"><?= gmdate('i:s', $remaining) ?></span>
      <?php endif; ?>
    </section>

    <section class="npay-card npay-qr">
      <img src="<?= htmlspecialchars($qrUrl) ?>" alt="VietQR" width="320" height="320">
    </section>

    <section class="npay-card npay-info">
      <table>
        <tr><th>Ngân hàng</th><td><?= htmlspecialchars($account['bank_short'] ?? '') ?></td></tr>
        <tr><th>Số tài khoản</th><td><strong><?= htmlspecialchars($account['account_number'] ?? '') ?></strong></td></tr>
        <tr><th>Chủ tài khoản</th><td><?= htmlspecialchars($account['account_holder'] ?? '') ?></td></tr>
        <tr><th>Số tiền</th><td><strong><?= number_format($amount, 0, ',', '.') ?> đ</strong></td></tr>
        <tr><th>Nội dung CK</th><td><strong><?= htmlspecialchars($code) ?></strong></td></tr>
      </table>
      <p class="muted">⚠️ Vui lòng nhập <strong>chính xác</strong> nội dung chuyển khoản để hệ thống tự động xác nhận.</p>
    </section>
  </main>

  <script src="assets/js/poll.js" defer></script>
</body>
</html>
