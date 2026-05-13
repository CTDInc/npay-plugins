<?php
/**
 * tools/npay-simulate.php — Gửi 1 webhook giả lập tới webhook.php để test cục bộ.
 *
 * CLI:
 *   php tools/npay-simulate.php https://localhost/npay/webhook.php NPAY12 100000
 *
 * Web (chỉ chạy được khi truy cập từ 127.0.0.1):
 *   /tools/npay-simulate.php?url=http://localhost/npay/webhook.php&code=NPAY12&amount=100000
 */

declare(strict_types=1);

require __DIR__ . '/../lib/Database.php';

use NPay\Database;

$cfg = Database::config();
$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($remote, ['127.0.0.1', '::1'], true)) {
        http_response_code(403);
        exit('Forbidden — simulate chỉ chạy localhost');
    }
}

if ($isCli) {
    $url    = $argv[1] ?? rtrim($cfg['app']['base_url'], '/') . '/webhook.php';
    $code   = $argv[2] ?? 'NPAY1';
    $amount = (float)($argv[3] ?? 10000);
} else {
    $url    = $_GET['url']    ?? rtrim($cfg['app']['base_url'], '/') . '/webhook.php';
    $code   = $_GET['code']   ?? 'NPAY1';
    $amount = (float)($_GET['amount'] ?? 10000);
}

$token  = $cfg['webhook']['api_token']   ?? '';
$secret = $cfg['webhook']['hmac_secret'] ?? '';
$acc    = $cfg['account'] ?? [];

$payload = [
    'id'              => random_int(100000, 999999),
    'gateway'         => $acc['bank_short'] ?? 'MB',
    'transactionDate' => date('Y-m-d H:i:s'),
    'accountNumber'   => $acc['account_number'] ?? '',
    'subAccount'      => null,
    'code'            => null, // để webhook auto-detect từ content
    'content'         => 'Khach hang thanh toan don ' . $code,
    'transferType'    => 'in',
    'transferAmount'  => $amount,
    'accumulated'     => $amount,
    'referenceCode'   => 'SIM' . date('YmdHis') . random_int(100, 999),
    'description'     => 'Simulated by npay-simulate.php',
];

$body = json_encode($payload, JSON_UNESCAPED_UNICODE);
$headers = [
    'Content-Type: application/json',
    'Authorization: Apikey ' . $token,
];
if ($secret !== '') {
    $headers[] = 'X-NPay-Signature: ' . hash_hmac('sha256', $body, $secret);
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$resp = curl_exec($ch);
$code_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

$out = [
    'sent_to'     => $url,
    'http_status' => $code_http,
    'request'     => $payload,
    'response'    => json_decode((string)$resp, true) ?? (string)$resp,
    'curl_error'  => $err,
];

if ($isCli) {
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
