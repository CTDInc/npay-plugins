<?php
/**
 * Landing-page generator / demo entry point.
 *
 * In a real deployment the actual landing page is built in LadiPage and
 * configured to POST its form to: /webhook.php?source=ladipage
 *
 * This file provides:
 *   1. A documented snippet showing the form action you should paste in LadiPage.
 *   2. A self-contained demo form (so you can test the flow without LadiPage).
 *
 * Flow:
 *   form submit -> webhook.php?source=ladipage -> 302 -> qr-page.php?ref=...
 */

declare(strict_types=1);

$config = is_file(__DIR__ . '/config.php')
    ? require __DIR__ . '/config.php'
    : require __DIR__ . '/config.example.php';

$webhookUrl = rtrim($config['base_url'], '/') . '/webhook.php?source=ladipage';
$defaultAmount = (int) ($config['default_amount'] ?? 100000);
?><!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>NPay × LadiPage — Demo Landing</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<main class="container">
    <header class="hero">
        <h1>NPay × LadiPage</h1>
        <p>Webhook receiver dùng cho landing page LadiPage có thanh toán bằng QR NPay.</p>
    </header>

    <section class="card">
        <h2>Tích hợp LadiPage</h2>
        <p>Trong LadiPage, mở <em>Cài đặt form → Hành động → Gửi đến URL</em> và dán:</p>
<pre><code><?= htmlspecialchars($webhookUrl, ENT_QUOTES) ?></code></pre>
        <p>Sau khi submit, khách hàng sẽ được redirect tự động sang trang QR thanh toán.</p>
    </section>

    <section class="card">
        <h2>Demo form (giả lập LadiPage)</h2>
        <form method="post" action="webhook.php?source=ladipage">
            <label>
                Họ tên
                <input type="text" name="name" required placeholder="Nguyễn Văn A">
            </label>
            <label>
                Số điện thoại
                <input type="tel" name="phone" required placeholder="0901234567">
            </label>
            <label>
                Email
                <input type="email" name="email" placeholder="you@example.com">
            </label>
            <label>
                Sản phẩm
                <input type="text" name="product" value="Gói khởi đầu">
            </label>
            <label>
                Số tiền (VND)
                <input type="number" name="amount" value="<?= $defaultAmount ?>" min="1000" step="1000">
            </label>
            <button type="submit" class="btn-primary">Đặt hàng & lấy QR</button>
        </form>
    </section>

    <footer class="muted">
        <small>Powered by <strong>NPay</strong> · qr.npay.vn</small>
    </footer>
</main>
</body>
</html>
