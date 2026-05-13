<?php
/**
 * Dual-purpose webhook endpoint.
 *
 *   /webhook.php?source=ladipage   <-- LadiPage form submission (creates pending order, redirects to QR page)
 *   /webhook.php?source=npay       <-- NPay payment notification (matches & marks paid)
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/NPayClient.php';

$config = is_file(__DIR__ . '/config.php')
    ? require __DIR__ . '/config.php'
    : require __DIR__ . '/config.example.php';

date_default_timezone_set($config['timezone'] ?? 'Asia/Ho_Chi_Minh');

$db     = new Database($config['db_path']);
$client = new NPayClient($config);

$source = $_GET['source'] ?? '';

try {
    if ($source === 'ladipage') {
        handleLadiPage($db, $client, $config);
    } elseif ($source === 'npay') {
        handleNPay($db, $client, $config);
    } else {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'missing or invalid ?source= parameter']);
    }
} catch (Throwable $e) {
    error_log('[npay-ladipage] webhook error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'internal error']);
}

// ---------------------------------------------------------------------------

function handleLadiPage(Database $db, NPayClient $client, array $config): void
{
    // LadiPage normally POSTs form-encoded or JSON. Accept both.
    $raw = file_get_contents('php://input') ?: '';
    $data = $_POST;
    if (empty($data) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }

    // Flatten common LadiPage shapes: {"form_data": {...}} or {"data": {...}}
    foreach (['form_data', 'data', 'fields'] as $k) {
        if (isset($data[$k]) && is_array($data[$k])) {
            $data = array_merge($data, $data[$k]);
        }
    }

    if (empty($data)) {
        http_response_code(400);
        echo 'Empty form payload.';
        return;
    }

    // Resolve amount.
    $amount = (int) ($data['amount'] ?? $data['price'] ?? $data['total'] ?? $config['default_amount'] ?? 0);
    if ($amount <= 0) {
        $amount = (int) ($config['default_amount'] ?? 0);
    }

    // Reserve an id first so ref_code is deterministic.
    $pdo = $db->pdo();
    $pdo->beginTransaction();
    try {
        $tmp = 'TMP-' . bin2hex(random_bytes(6));
        $row = $db->createOrder($tmp, $amount, $data);
        $refCode = $client->makeRefCode((int) $row['id']);
        $upd = $pdo->prepare('UPDATE orders SET ref_code = :ref WHERE id = :id');
        $upd->execute([':ref' => $refCode, ':id' => $row['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    // LadiPage supports `redirect_url` for post-submit navigation; honor it if present.
    $redirect = $data['redirect_url'] ?? null;
    $qrUrl    = rtrim($config['base_url'], '/') . '/qr-page.php?ref=' . urlencode($refCode);

    // If LadiPage form posted via fetch/AJAX, return JSON; otherwise 302.
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xrw    = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    if (stripos($accept, 'application/json') !== false || strtolower($xrw) === 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'       => true,
            'ref_code' => $refCode,
            'amount'   => $amount,
            'redirect' => $redirect ?: $qrUrl,
        ]);
        return;
    }

    header('Location: ' . ($redirect ?: $qrUrl), true, 302);
}

function handleNPay(Database $db, NPayClient $client, array $config): void
{
    // Optional IP whitelist.
    $whitelist = $config['npay_webhook_ips'] ?? [];
    if (!empty($whitelist)) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!in_array($ip, $whitelist, true)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'ip not allowed']);
            return;
        }
    }

    $raw = file_get_contents('php://input') ?: '';
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    if (!$headers) {
        // Fallback when getallheaders() isn't available (CLI-server, some FPM setups).
        foreach ($_SERVER as $k => $v) {
            if (strpos($k, 'HTTP_') === 0) {
                $headers[str_replace('_', '-', substr($k, 5))] = $v;
            }
        }
    }

    if (!$client->verifyWebhook($raw, $headers)) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'invalid signature']);
        return;
    }

    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }
    if (empty($payload)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'empty payload']);
        return;
    }

    $parsed = $client->parseWebhookPayload($payload);

    // Try matching by ref_code substring inside transfer content.
    $order = $db->matchByContent($parsed['content'], $parsed['amount'] > 0 ? $parsed['amount'] : null);
    if (!$order) {
        // Fall back: match content only, ignoring amount.
        $order = $db->matchByContent($parsed['content']);
    }

    if (!$order) {
        // We accept the webhook but flag it as unmatched (return 200 so NPay doesn't retry forever).
        error_log('[npay-ladipage] unmatched payment: ' . $parsed['content']);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'matched' => false]);
        return;
    }

    $db->markPaid((int) $order['id'], $payload);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'       => true,
        'matched'  => true,
        'ref_code' => $order['ref_code'],
    ]);
}
