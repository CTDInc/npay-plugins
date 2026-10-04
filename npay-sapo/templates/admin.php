<?php
/** @var array $config */
/** @var ?array $store */
/** @var array $orders */
/** @var array $stores */
/** @var bool $isOperator */
/** @var string $csrf */
?><!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>NPay × Sapo · Quản trị</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
</head>
<body class="npay-admin">
<header class="npay-admin__head">
    <h1><span class="brand">NPay</span> · Sapo</h1>
    <?php if ($store): ?>
        <p>Cửa hàng: <b><?= htmlspecialchars((string)$store['sapo_store']) ?></b></p>
    <?php endif; ?>
    <form method="post" action="/admin/logout" class="inline">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <?php if ($isOperator && $store): ?><a href="/admin">Tất cả cửa hàng</a> · <?php endif; ?>
        <button type="submit">Đăng xuất</button>
    </form>
</header>

<?php if (!$store && $stores): ?>
    <section class="npay-card">
        <h2>Cửa hàng đã cài</h2>
        <table class="npay-table">
            <thead><tr><th>#</th><th>Cửa hàng</th><th>Cài lúc</th></tr></thead>
            <tbody>
            <?php foreach ($stores as $s): ?>
                <tr>
                    <td><?= (int)$s['id'] ?></td>
                    <td><a href="/admin?store=<?= (int)$s['id'] ?>"><?= htmlspecialchars((string)$s['sapo_store']) ?></a></td>
                    <td><?= htmlspecialchars((string)$s['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>

<?php if (!$store): ?>
    <section class="npay-card">
        <h2>Cài đặt ứng dụng</h2>
        <p>Để cài đặt NPay vào cửa hàng Sapo của bạn, mở URL:</p>
        <pre><?= htmlspecialchars(rtrim((string)($config['app_url'] ?? ''), '/')) ?>/install?shop=<i>yourstore.mysapo.net</i></pre>
    </section>
<?php else: ?>

    <?php if (!empty($_GET['saved'])): ?>
        <div class="flash ok">Đã lưu cấu hình.</div>
    <?php endif; ?>

    <section class="npay-card">
        <h2>Cấu hình thanh toán</h2>
        <form method="post" action="/admin/save">
            <input type="hidden" name="store_id" value="<?= (int)$store['id'] ?>">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <label>NPay API key (header <code>Authorization: Apikey</code>)<input name="api_key" value="<?= htmlspecialchars((string)($store['api_key'] ?? '')) ?>"></label>
            <label>NPay webhook secret (ký <code>X-Npay-Signature</code>, sao chép từ dashboard npay.vn)<input name="npay_webhook_secret" value="<?= htmlspecialchars((string)($store['npay_webhook_secret'] ?? '')) ?>"></label>
            <label>Ngân hàng (VCB, MB, vietcombank… hoặc mã BIN)<input name="bank_code" value="<?= htmlspecialchars((string)($store['bank_code'] ?? '')) ?>"></label>
            <label>Số tài khoản<input name="account_number" value="<?= htmlspecialchars((string)($store['account_number'] ?? '')) ?>"></label>
            <label>Chủ tài khoản<input name="account_name" value="<?= htmlspecialchars((string)($store['account_name'] ?? '')) ?>"></label>
            <label>Sapo webhook HMAC secret<input name="webhook_secret" value="<?= htmlspecialchars((string)($store['webhook_secret'] ?? '')) ?>"></label>
            <button type="submit">Lưu</button>
        </form>
    </section>

    <section class="npay-card">
        <h2>Đơn hàng gần đây</h2>
        <table class="npay-table">
            <thead>
                <tr>
                    <th>#</th><th>Sapo ID</th><th>Mã CK</th><th>Số tiền</th>
                    <th>Trạng thái</th><th>Tạo lúc</th><th>Thanh toán</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td><?= (int)$o['id'] ?></td>
                    <td><?= htmlspecialchars((string)$o['sapo_order_id']) ?></td>
                    <td><?= htmlspecialchars((string)$o['ref_code']) ?></td>
                    <td><?= number_format((float)$o['amount'], 0, ',', '.') ?> đ</td>
                    <td class="status status--<?= htmlspecialchars((string)$o['status']) ?>"><?= htmlspecialchars((string)$o['status']) ?></td>
                    <td><?= htmlspecialchars((string)$o['created_at']) ?></td>
                    <td><?= htmlspecialchars((string)($o['paid_at'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$orders): ?>
                <tr><td colspan="7" class="muted">Chưa có đơn hàng nào.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>
</body>
</html>
