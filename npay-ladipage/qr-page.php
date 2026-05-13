<?php
/**
 * Customer-facing QR payment page.
 *
 *   /qr-page.php?ref=NP000123
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/NPayClient.php';

$config = is_file(__DIR__ . '/config.php')
    ? require __DIR__ . '/config.php'
    : require __DIR__ . '/config.example.php';

date_default_timezone_set($config['timezone'] ?? 'Asia/Ho_Chi_Minh');

$ref = isset($_GET['ref']) ? (string) $_GET['ref'] : '';
if ($ref === '' || !preg_match('/^[A-Za-z0-9_-]{3,32}$/', $ref)) {
    http_response_code(400);
    echo 'Invalid reference code.';
    exit;
}

$db    = new Database($config['db_path']);
$order = $db->findByRef($ref);
if (!$order) {
    http_response_code(404);
    echo 'Order not found.';
    exit;
}

$client = new NPayClient($config);
$qrUrl  = $client->buildQrUrl($order['ref_code'], (int) $order['amount']);
$payLink = $client->buildPayLink($order['ref_code'], (int) $order['amount']);

$expirySec   = (int) ($config['expiry_seconds'] ?? 900);
$createdTs   = strtotime($order['created_at'] . ' UTC') ?: time();
$deadlineTs  = $createdTs + $expirySec;
$remaining   = max(0, $deadlineTs - time());
?><!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Thanh toán đơn hàng <?= htmlspecialchars($order['ref_code']) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<main class="container">
    <header class="hero">
        <h1>Quét mã QR để thanh toán</h1>
        <p>Đơn hàng: <strong><?= htmlspecialchars($order['ref_code']) ?></strong></p>
    </header>

    <section class="card qr-card" id="qrCard" data-ref="<?= htmlspecialchars($order['ref_code']) ?>" data-status="<?= htmlspecialchars($order['status']) ?>">
        <div class="qr-wrap">
            <img id="qrImage" src="<?= htmlspecialchars($qrUrl) ?>" alt="QR thanh toán NPay" loading="eager">
        </div>

        <dl class="bank-info">
            <dt>Ngân hàng</dt><dd>BIN <?= htmlspecialchars($config['bank_bin']) ?></dd>
            <dt>Số tài khoản</dt><dd><strong><?= htmlspecialchars($config['account_number']) ?></strong></dd>
            <dt>Chủ tài khoản</dt><dd><?= htmlspecialchars($config['account_holder']) ?></dd>
            <dt>Số tiền</dt><dd><strong><?= number_format((int) $order['amount'], 0, ',', '.') ?> đ</strong></dd>
            <dt>Nội dung</dt><dd><code><?= htmlspecialchars($order['ref_code']) ?></code></dd>
        </dl>

        <p class="hint">Vui lòng giữ nguyên nội dung chuyển khoản <code><?= htmlspecialchars($order['ref_code']) ?></code> để hệ thống tự động đối soát.</p>

        <div class="countdown">
            Còn lại: <span id="countdown" data-remaining="<?= $remaining ?>"><?= gmdate('i:s', $remaining) ?></span>
        </div>

        <div class="actions">
            <a class="btn-primary" href="<?= htmlspecialchars($payLink) ?>" target="_blank" rel="noopener">Mở ứng dụng NPay</a>
        </div>

        <div id="statusBox" class="status status-<?= htmlspecialchars($order['status']) ?>">
            <?php if ($order['status'] === 'paid'): ?>
                ✅ Đã thanh toán. Cảm ơn quý khách!
            <?php elseif ($order['status'] === 'expired'): ?>
                ⌛ Đơn đã hết hạn.
            <?php else: ?>
                ⏳ Đang chờ thanh toán…
            <?php endif; ?>
        </div>
    </section>

    <footer class="muted">
        <small>Powered by <strong>NPay</strong> · qr.npay.vn</small>
    </footer>
</main>

<script>
window.NPAY_CONFIG = {
    statusUrl: 'status.php?ref=<?= rawurlencode($order['ref_code']) ?>',
    pollMs: 3500
};
</script>
<script src="assets/js/poll.js"></script>
</body>
</html>
