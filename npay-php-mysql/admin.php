<?php
/**
 * admin.php — Trang quản trị tối giản (HTTP Basic auth).
 *
 * Tab:
 *   ?tab=orders        — danh sách đơn
 *   ?tab=transactions  — danh sách giao dịch nhận về
 *   ?tab=settings      — config readonly
 *
 * Action:
 *   POST action=cancel&id=N    — huỷ đơn pending
 *   POST action=mark_paid&id=N — đánh dấu đã thanh toán thủ công
 */

declare(strict_types=1);

require __DIR__ . '/lib/Database.php';
require __DIR__ . '/lib/NPay.php';

use NPay\Database;
use NPay\NPay;

$cfg = Database::config();
date_default_timezone_set($cfg['app']['timezone'] ?? 'Asia/Ho_Chi_Minh');

// --- HTTP Basic --------------------------------------------------------------
$adminU = $cfg['admin']['username'] ?? '';
$adminP = $cfg['admin']['password'] ?? '';
$u = $_SERVER['PHP_AUTH_USER'] ?? '';
$p = $_SERVER['PHP_AUTH_PW']   ?? '';
if (!hash_equals($adminU, (string)$u) || !hash_equals($adminP, (string)$p)) {
    header('WWW-Authenticate: Basic realm="NPay Admin"');
    http_response_code(401);
    exit('Unauthorized');
}

$pdo = Database::pdo();

// --- Actions -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    if ($action === 'cancel' && $id > 0) {
        $pdo->prepare("UPDATE tb_orders SET status='cancelled' WHERE id=:id AND status='pending'")
            ->execute([':id' => $id]);
    } elseif ($action === 'mark_paid' && $id > 0) {
        $pdo->prepare("UPDATE tb_orders SET status='paid', paid_at=NOW() WHERE id=:id AND status='pending'")
            ->execute([':id' => $id]);
    }
    header('Location: ?tab=' . urlencode($_POST['tab'] ?? 'orders'));
    exit;
}

$tab = $_GET['tab'] ?? 'orders';

// --- Stats -------------------------------------------------------------------
$stats = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM tb_orders)                              AS orders_total,
        (SELECT COUNT(*) FROM tb_orders WHERE status='pending')       AS orders_pending,
        (SELECT COUNT(*) FROM tb_orders WHERE status='paid')          AS orders_paid,
        (SELECT COUNT(*) FROM tb_transactions)                        AS tx_total,
        (SELECT COALESCE(SUM(amount_in),0) FROM tb_transactions)      AS tx_in_sum"
)->fetch();
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>NPay Admin</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="npay-header">
  <h1>NPay Admin</h1>
  <nav class="npay-tabs">
    <a href="?tab=orders"        class="<?= $tab === 'orders' ? 'active' : '' ?>">Đơn hàng</a>
    <a href="?tab=transactions"  class="<?= $tab === 'transactions' ? 'active' : '' ?>">Giao dịch</a>
    <a href="?tab=settings"      class="<?= $tab === 'settings' ? 'active' : '' ?>">Cấu hình</a>
  </nav>
</header>

<section class="npay-stats">
  <div class="stat"><span class="num"><?= (int)$stats['orders_total'] ?></span><span>Đơn</span></div>
  <div class="stat"><span class="num"><?= (int)$stats['orders_pending'] ?></span><span>Đang chờ</span></div>
  <div class="stat"><span class="num"><?= (int)$stats['orders_paid'] ?></span><span>Đã thu</span></div>
  <div class="stat"><span class="num"><?= (int)$stats['tx_total'] ?></span><span>Giao dịch</span></div>
  <div class="stat"><span class="num"><?= number_format((float)$stats['tx_in_sum'], 0, ',', '.') ?>đ</span><span>Tổng tiền vào</span></div>
</section>

<main class="npay-admin">
<?php if ($tab === 'orders'): ?>
  <h2>Đơn hàng (50 gần nhất)</h2>
  <table class="npay-table">
    <thead><tr><th>ID</th><th>Code</th><th>Số tiền</th><th>Trạng thái</th><th>Tạo</th><th>Trả lúc</th><th>TxID</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($pdo->query("SELECT * FROM tb_orders ORDER BY id DESC LIMIT 50") as $o): ?>
      <tr>
        <td><?= (int)$o['id'] ?></td>
        <td><a href="pay.php?code=<?= htmlspecialchars($o['code']) ?>"><?= htmlspecialchars($o['code']) ?></a></td>
        <td><?= number_format((float)$o['amount'], 0, ',', '.') ?>đ</td>
        <td><span class="badge badge-<?= htmlspecialchars($o['status']) ?>"><?= htmlspecialchars($o['status']) ?></span></td>
        <td><?= htmlspecialchars($o['created_at']) ?></td>
        <td><?= htmlspecialchars($o['paid_at'] ?? '') ?></td>
        <td><?= htmlspecialchars((string)($o['paid_transaction_id'] ?? '')) ?></td>
        <td>
          <?php if ($o['status'] === 'pending'): ?>
            <form method="post" style="display:inline" onsubmit="return confirm('Đánh dấu đã thanh toán?')">
              <input type="hidden" name="action" value="mark_paid">
              <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
              <input type="hidden" name="tab" value="orders">
              <button>✓ Paid</button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('Huỷ đơn này?')">
              <input type="hidden" name="action" value="cancel">
              <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
              <input type="hidden" name="tab" value="orders">
              <button>✗ Huỷ</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

<?php elseif ($tab === 'transactions'): ?>
  <h2>Giao dịch nhận từ webhook (50 gần nhất)</h2>
  <table class="npay-table">
    <thead><tr><th>ID</th><th>Gateway</th><th>Ngày</th><th>STK</th><th>Vào</th><th>Ra</th><th>Code</th><th>Nội dung</th><th>Ref</th></tr></thead>
    <tbody>
    <?php foreach ($pdo->query("SELECT * FROM tb_transactions ORDER BY id DESC LIMIT 50") as $t): ?>
      <tr>
        <td><?= (int)$t['id'] ?></td>
        <td><?= htmlspecialchars($t['gateway']) ?></td>
        <td><?= htmlspecialchars($t['transaction_date']) ?></td>
        <td><?= htmlspecialchars((string)$t['account_number']) ?></td>
        <td><?= number_format((float)$t['amount_in'], 0, ',', '.') ?></td>
        <td><?= number_format((float)$t['amount_out'], 0, ',', '.') ?></td>
        <td><?= htmlspecialchars((string)$t['code']) ?></td>
        <td class="content"><?= htmlspecialchars((string)$t['transaction_content']) ?></td>
        <td><code><?= htmlspecialchars((string)$t['reference_number']) ?></code></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

<?php elseif ($tab === 'settings'): ?>
  <h2>Cấu hình (chỉ đọc)</h2>
  <p>Sửa file <code>config.php</code> để thay đổi.</p>
  <pre class="npay-pre"><?php
    $safe = $cfg;
    $safe['db']['password']         = '***';
    $safe['webhook']['api_token']   = $safe['webhook']['api_token']   ? '***' : '';
    $safe['webhook']['hmac_secret'] = $safe['webhook']['hmac_secret'] ? '***' : '';
    $safe['admin']['password']      = '***';
    echo htmlspecialchars(print_r($safe, true));
  ?></pre>

  <h3>Webhook URL</h3>
  <p>Cấu hình URL này trong dashboard <a href="<?= htmlspecialchars($cfg['endpoints']['dashboard']) ?>" target="_blank"><?= htmlspecialchars($cfg['endpoints']['dashboard']) ?></a>:</p>
  <pre class="npay-pre"><?= htmlspecialchars(rtrim($cfg['app']['base_url'], '/') . '/webhook.php') ?></pre>
<?php endif; ?>
</main>
</body>
</html>
