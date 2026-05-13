<?php
/**
 * Simple token-protected admin UI.
 *
 * Auth: ?token=API_TOKEN (also accepted as POST field 'token' or cookie 'npay_admin').
 * Pages:
 *   /admin.php                -> dashboard (pending + paid orders)
 *   /admin.php?view=order&id= -> order detail
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/NPayClient.php';

$config = is_file(__DIR__ . '/config.php')
    ? require __DIR__ . '/config.php'
    : require __DIR__ . '/config.example.php';

date_default_timezone_set($config['timezone'] ?? 'Asia/Ho_Chi_Minh');

$apiToken = (string) ($config['api_token'] ?? '');

// --- auth ---
$provided = $_GET['token'] ?? $_POST['token'] ?? $_COOKIE['npay_admin'] ?? '';
$authed = $apiToken !== ''
    && $apiToken !== 'CHANGE_ME_TO_A_LONG_RANDOM_STRING'
    && hash_equals($apiToken, (string) $provided);

if (!$authed) {
    if (!empty($_POST['token'])) {
        // sticky cookie for convenience (HttpOnly)
        setcookie('npay_admin', $_POST['token'], [
            'expires'  => time() + 3600 * 8,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        header('Location: admin.php');
        exit;
    }
    render_login();
    exit;
}

$db = new Database($config['db_path']);
$view = $_GET['view'] ?? 'dashboard';

if ($view === 'logout') {
    setcookie('npay_admin', '', time() - 3600, '/');
    header('Location: admin.php');
    exit;
}

if ($view === 'order' && isset($_GET['id'])) {
    $order = $db->findById((int) $_GET['id']);
    render_order_detail($order, $config);
    exit;
}

// Default: dashboard.
$counts  = $db->counts();
$pending = $db->listOrders('pending', 50);
$paid    = $db->listOrders('paid', 50);
render_dashboard($counts, $pending, $paid, $config);

// ---------------------------------------------------------------------------

function render_login(): void
{
    ?><!doctype html>
<html lang="vi"><head><meta charset="utf-8">
<title>NPay Admin — Đăng nhập</title>
<link rel="stylesheet" href="assets/css/style.css">
</head><body><main class="container narrow">
<section class="card">
<h1>NPay × LadiPage Admin</h1>
<form method="post" action="admin.php">
    <label>API Token
        <input type="password" name="token" autofocus required>
    </label>
    <button class="btn-primary" type="submit">Đăng nhập</button>
</form>
<p class="muted"><small>Token được cấu hình trong <code>config.php</code> (<code>api_token</code>).</small></p>
</section></main></body></html><?php
}

function render_dashboard(array $counts, array $pending, array $paid, array $config): void
{
    $base = rtrim($config['base_url'], '/');
    ?><!doctype html>
<html lang="vi"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>NPay Admin</title>
<link rel="stylesheet" href="assets/css/style.css">
</head><body><main class="container wide">

<header class="admin-header">
    <h1>NPay × LadiPage — Bảng điều khiển</h1>
    <a href="admin.php?view=logout" class="muted">Đăng xuất</a>
</header>

<section class="stats">
    <div class="stat"><span>Pending</span><strong><?= (int) ($counts['pending'] ?? 0) ?></strong></div>
    <div class="stat"><span>Paid</span><strong><?= (int) ($counts['paid'] ?? 0) ?></strong></div>
    <div class="stat"><span>Expired</span><strong><?= (int) ($counts['expired'] ?? 0) ?></strong></div>
    <div class="stat"><span>Tổng</span><strong><?= (int) ($counts['total'] ?? 0) ?></strong></div>
</section>

<section class="card">
    <h2>Cấu hình</h2>
    <dl class="bank-info">
        <dt>Số tài khoản</dt><dd><?= htmlspecialchars($config['account_number']) ?></dd>
        <dt>BIN ngân hàng</dt><dd><?= htmlspecialchars($config['bank_bin']) ?></dd>
        <dt>Chủ tài khoản</dt><dd><?= htmlspecialchars($config['account_holder']) ?></dd>
        <dt>LadiPage submit URL</dt><dd><code><?= htmlspecialchars($base . '/webhook.php?source=ladipage') ?></code></dd>
        <dt>NPay webhook URL</dt><dd><code><?= htmlspecialchars($base . '/webhook.php?source=npay') ?></code></dd>
    </dl>
    <p class="muted"><small>Sửa các giá trị này trong file <code>config.php</code>.</small></p>
</section>

<section class="card">
    <h2>Đơn đang chờ (<?= count($pending) ?>)</h2>
    <?= render_table($pending) ?>
</section>

<section class="card">
    <h2>Đơn đã thanh toán (<?= count($paid) ?>)</h2>
    <?= render_table($paid) ?>
</section>

</main></body></html><?php
}

function render_table(array $rows): string
{
    if (!$rows) {
        return '<p class="muted">Chưa có đơn nào.</p>';
    }
    $html = '<table class="orders"><thead><tr>'
          . '<th>#</th><th>Ref</th><th>Số tiền</th><th>Trạng thái</th>'
          . '<th>Tạo lúc</th><th>Thanh toán lúc</th><th></th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $html .= '<tr>'
              . '<td>' . (int) $r['id'] . '</td>'
              . '<td><code>' . htmlspecialchars($r['ref_code']) . '</code></td>'
              . '<td>' . number_format((int) $r['amount'], 0, ',', '.') . '</td>'
              . '<td><span class="badge badge-' . htmlspecialchars($r['status']) . '">' . htmlspecialchars($r['status']) . '</span></td>'
              . '<td>' . htmlspecialchars($r['created_at']) . '</td>'
              . '<td>' . htmlspecialchars($r['paid_at'] ?? '—') . '</td>'
              . '<td><a href="admin.php?view=order&id=' . (int) $r['id'] . '">Xem</a></td>'
              . '</tr>';
    }
    $html .= '</tbody></table>';
    return $html;
}

function render_order_detail(?array $order, array $config): void
{
    if (!$order) {
        http_response_code(404);
        echo 'Order not found.';
        return;
    }
    $ladi = $order['ladipage_data'] ? json_decode($order['ladipage_data'], true) : null;
    $npay = $order['npay_payload']  ? json_decode($order['npay_payload'], true)  : null;
    ?><!doctype html>
<html lang="vi"><head><meta charset="utf-8">
<title>Order <?= htmlspecialchars($order['ref_code']) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head><body><main class="container wide">
<p><a href="admin.php">← Quay lại</a></p>
<h1>Đơn <?= htmlspecialchars($order['ref_code']) ?></h1>
<p>Trạng thái: <span class="badge badge-<?= htmlspecialchars($order['status']) ?>"><?= htmlspecialchars($order['status']) ?></span></p>
<p>Số tiền: <strong><?= number_format((int) $order['amount'], 0, ',', '.') ?> đ</strong></p>
<p>Tạo: <?= htmlspecialchars($order['created_at']) ?> · Thanh toán: <?= htmlspecialchars($order['paid_at'] ?? '—') ?></p>

<section class="card">
<h2>LadiPage form data</h2>
<pre><?= htmlspecialchars(json_encode($ladi, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
</section>

<section class="card">
<h2>NPay webhook payload</h2>
<pre><?= htmlspecialchars(json_encode($npay, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
</section>

</main></body></html><?php
}
