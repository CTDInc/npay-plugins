<?php
/** @var bool $passwordConfigured */
/** @var bool $error */
/** @var string $csrf */
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>NPay × Haravan — Đăng nhập</title>
<link rel="stylesheet" href="/public/assets/css/style.css">
</head>
<body>
<div class="npay-admin npay-login">
    <h1>NPay × Haravan</h1>
    <h2>Đăng nhập quản trị</h2>
    <p>Chủ shop: truy cập <code>/install?shop=YOUR_SHOP.myharavan.com</code> để xác thực qua Haravan.</p>
    <?php if (!$passwordConfigured): ?>
        <p class="npay-flash-err">Chưa cấu hình <code>admin_password</code> (biến môi trường <code>ADMIN_PASSWORD</code>) — đăng nhập bằng mật khẩu đang tắt.</p>
    <?php else: ?>
        <?php if ($error): ?>
            <p class="npay-flash-err">Sai mật khẩu.</p>
        <?php endif; ?>
        <form method="post" action="/admin/login">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <label>Mật khẩu quản trị
                <input type="password" name="password" autofocus required autocomplete="current-password">
            </label>
            <button type="submit">Đăng nhập</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
