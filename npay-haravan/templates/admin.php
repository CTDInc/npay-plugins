<?php
/** @var array $orders */
/** @var array $config */
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>NPay × Haravan — Quản trị</title>
<link rel="stylesheet" href="/public/assets/css/style.css">
</head>
<body>
<div class="npay-admin">
    <h1>NPay × Haravan</h1>
    <p>App URL: <code><?= htmlspecialchars((string)$config['app_url']) ?></code></p>
    <p>Cài đặt mới: <code><?= htmlspecialchars(rtrim((string)$config['app_url'], '/')) ?>/install?shop=YOUR_SHOP.myharavan.com</code></p>
    <p>Webhook Haravan: <code><?= htmlspecialchars(rtrim((string)$config['app_url'], '/')) ?>/webhook/haravan</code></p>
    <p>Webhook NPay: <code><?= htmlspecialchars(rtrim((string)$config['app_url'], '/')) ?>/webhook/npay</code></p>

    <h2>Đơn hàng gần đây</h2>
    <table class="npay-table">
        <thead>
        <tr>
            <th>#</th>
            <th>Shop</th>
            <th>Order</th>
            <th>Code</th>
            <th>Amount</th>
            <th>Status</th>
            <th>NPay Txn</th>
            <th>Paid at</th>
            <th>Created</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
            <tr>
                <td><?= (int)$o['id'] ?></td>
                <td><?= htmlspecialchars((string)$o['shop']) ?></td>
                <td><?= htmlspecialchars((string)$o['order_number']) ?></td>
                <td><code><?= htmlspecialchars((string)$o['order_code']) ?></code></td>
                <td><?= number_format((float)$o['amount'], 0, ',', '.') ?></td>
                <td class="status <?= htmlspecialchars((string)$o['status']) ?>"><?= htmlspecialchars((string)$o['status']) ?></td>
                <td><?= htmlspecialchars((string)($o['npay_txn_id'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string)($o['paid_at'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string)($o['created_at'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($orders)): ?>
            <tr><td colspan="9" style="text-align:center;color:#999">Chưa có đơn hàng nào.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
