<?php
/** @var array $order */
/** @var array $store */
/** @var string $qrUrl */
/** @var int    $ttl */
/** @var string $statusUrl */

$amountFmt = number_format((float)$order['amount'], 0, ',', '.') . ' đ';
?><!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>NPay × Sapo · Thanh toán đơn #<?= htmlspecialchars((string)$order['sapo_order_id']) ?></title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
</head>
<body class="npay-page">
<main class="npay-card">
    <header class="npay-card__head">
        <h1>Thanh toán qua <span class="brand">NPay</span></h1>
        <p class="muted">Đơn hàng Sapo #<?= htmlspecialchars((string)$order['sapo_order_id']) ?></p>
    </header>

    <section class="npay-qr">
        <img src="<?= htmlspecialchars($qrUrl) ?>" alt="QR thanh toán NPay" id="npay-qr-img">
    </section>

    <section class="npay-info">
        <div class="row"><span>Ngân hàng</span><b><?= htmlspecialchars((string)($store['bank_code'] ?? '')) ?></b></div>
        <div class="row"><span>Số tài khoản</span><b><?= htmlspecialchars((string)($store['account_number'] ?? '')) ?></b></div>
        <div class="row"><span>Chủ tài khoản</span><b><?= htmlspecialchars((string)($store['account_name'] ?? '')) ?></b></div>
        <div class="row"><span>Số tiền</span><b class="amount"><?= $amountFmt ?></b></div>
        <div class="row"><span>Nội dung CK</span><b class="ref"><?= htmlspecialchars((string)$order['ref_code']) ?></b></div>
    </section>

    <section class="npay-timer">
        Hết hạn sau <b id="npay-countdown">--:--</b>
    </section>

    <section class="npay-status" id="npay-status" data-status="<?= htmlspecialchars((string)$order['status']) ?>">
        <span class="dot"></span>
        <span class="text">Đang chờ thanh toán…</span>
    </section>

    <footer class="npay-foot">
        <p class="muted">Vui lòng giữ nguyên nội dung chuyển khoản để hệ thống tự động xác nhận.</p>
        <p class="muted">Được vận hành bởi <b>NPay</b>.</p>
    </footer>
</main>

<script>
    window.NPAY_SAPO = {
        statusUrl: <?= json_encode($statusUrl) ?>,
        ttl: <?= (int)$ttl ?>
    };
</script>
<script src="/public/assets/js/poll.js"></script>
</body>
</html>
