<?php
/**
 * Status JSON endpoint — used by qr-page.php JS poll.
 *
 *   GET /status.php?ref=NP000123
 *   -> {"ok":true,"ref_code":"NP000123","status":"pending","amount":100000,"paid_at":null}
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/Database.php';

$config = is_file(__DIR__ . '/config.php')
    ? require __DIR__ . '/config.php'
    : require __DIR__ . '/config.example.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$ref = isset($_GET['ref']) ? (string) $_GET['ref'] : '';
if ($ref === '' || !preg_match('/^[A-Za-z0-9_-]{3,32}$/', $ref)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid ref']);
    exit;
}

try {
    $db    = new Database($config['db_path']);
    $order = $db->findByRef($ref);
    if (!$order) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'not found']);
        exit;
    }

    // Auto-expire when too old.
    $expirySec = (int) ($config['expiry_seconds'] ?? 900);
    if ($order['status'] === 'pending') {
        $createdTs = strtotime($order['created_at'] . ' UTC') ?: time();
        if (time() - $createdTs > $expirySec) {
            $db->expireStale($expirySec);
            $order = $db->findByRef($ref);
        }
    }

    echo json_encode([
        'ok'       => true,
        'ref_code' => $order['ref_code'],
        'status'   => $order['status'],
        'amount'   => (int) $order['amount'],
        'paid_at'  => $order['paid_at'],
    ]);
} catch (Throwable $e) {
    error_log('[npay-ladipage] status error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'internal']);
}
