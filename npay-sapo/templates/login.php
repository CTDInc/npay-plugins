<?php
/** @var bool $passwordConfigured */
/** @var bool $error */
/** @var int $storeId */
/** @var string $csrf */
?><!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>NPay × Sapo · Đăng nhập</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
</head>
<body class="npay-admin">
<header class="npay-admin__head">
    <h1><span class="brand">NPay</span> · Sapo</h1>
</header>

<section class="npay-card">
    <h2>Đăng nhập quản trị</h2>
    <p class="muted">Chủ cửa hàng: mở lại app từ trang quản trị Sapo, hoặc truy cập
        <code>/install?shop=yourstore.mysapo.net</code> để xác thực qua Sapo.</p>
    <?php if (!$passwordConfigured): ?>
        <div class="flash err">Chưa cấu hình <code>admin_password</code> (biến môi trường <code>ADMIN_PASSWORD</code>) — đăng nhập bằng mật khẩu đang tắt.</div>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="flash err">Sai mật khẩu.</div>
        <?php endif; ?>
        <form method="post" action="/admin/login">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <?php if ($storeId): ?>
                <input type="hidden" name="store" value="<?= (int)$storeId ?>">
            <?php endif; ?>
            <label>Mật khẩu quản trị<input type="password" name="password" autofocus required autocomplete="current-password"></label>
            <button type="submit">Đăng nhập</button>
        </form>
    <?php endif; ?>
</section>
</body>
</html>
