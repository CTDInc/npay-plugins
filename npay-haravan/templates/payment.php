<?php
/** @var array $payment */
/** @var string $qrUrl */
/** @var array $bank */
/** @var array $config */
$pollMs = (int)($config['poll_interval_ms'] ?? 4000);
$statusUrl = rtrim($config['app_url'], '/') . '/status/' . rawurlencode((string)$payment['order_id']);
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>NPay - Thanh toán đơn <?= htmlspecialchars((string)$payment['order_number']) ?></title>
<link rel="stylesheet" href="/public/assets/css/style.css">
</head>
<body>
<div class="npay-wrap">
    <header class="npay-header">
        <h1>NPay × Haravan</h1>
        <p>Thanh toán nhanh bằng mã QR ngân hàng</p>
    </header>

    <section class="npay-card">
        <div class="npay-qr">
            <img id="qr-image" src="<?= htmlspecialchars($qrUrl) ?>" alt="QR thanh toán">
        </div>
        <div class="npay-info">
            <h2>Đơn hàng #<?= htmlspecialchars((string)$payment['order_number']) ?></h2>
            <p class="amount"><?= number_format((float)$payment['amount'], 0, ',', '.') ?> <?= htmlspecialchars((string)$payment['currency']) ?></p>
            <ul>
                <li><b>Ngân hàng:</b> <?= htmlspecialchars((string)$bank['bank_id']) ?></li>
                <li><b>Số tài khoản:</b> <?= htmlspecialchars((string)$bank['account_number']) ?></li>
                <li><b>Chủ tài khoản:</b> <?= htmlspecialchars((string)$bank['account_name']) ?></li>
                <li><b>Nội dung CK:</b> <code id="order-code"><?= htmlspecialchars((string)$payment['order_code']) ?></code></li>
            </ul>
            <p class="hint">Vui lòng giữ nguyên nội dung chuyển khoản để hệ thống tự động ghi nhận.</p>
            <div id="npay-status" class="status pending" data-status="<?= htmlspecialchars((string)$payment['status']) ?>">
                Đang chờ thanh toán...
            </div>
        </div>
    </section>

    <footer class="npay-footer">
        <small>Powered by <b>NPay</b></small>
    </footer>
</div>

<script>
window.NPAY_CONFIG = {
    statusUrl: <?= json_encode($statusUrl) ?>,
    pollMs:    <?= (int)$pollMs ?>
};
</script>
<script src="/public/assets/js/poll.js"></script>
</body>
</html>
