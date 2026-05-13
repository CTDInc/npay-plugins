<?php

declare(strict_types=1);

namespace NPay\Haravan\Payment;

use NPay\Haravan\Database;
use NPay\Haravan\NPayClient;

class PaymentPage
{
    private array $config;
    private Database $db;

    public function __construct(array $config, Database $db)
    {
        $this->config = $config;
        $this->db    = $db;
    }

    public function render(string $orderId): void
    {
        $payment = $this->db->findPaymentByOrderId($orderId);
        if (!$payment) {
            http_response_code(404);
            echo 'Order not found';
            return;
        }

        $npay     = new NPayClient($this->config);
        $qrUrl    = $npay->buildQrUrl((string)$payment['order_code'], (float)$payment['amount']);
        $bank     = $npay->bankInfo();
        $config   = $this->config;

        include dirname(__DIR__, 2) . '/templates/payment.php';
    }

    public function status(string $orderId): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $payment = $this->db->findPaymentByOrderId($orderId);
        if (!$payment) {
            http_response_code(404);
            echo json_encode(['status' => 'not_found']);
            return;
        }
        echo json_encode([
            'status'      => $payment['status'],
            'paid_at'     => $payment['paid_at'],
            'amount'      => $payment['amount'],
            'order_code'  => $payment['order_code'],
            'npay_txn_id' => $payment['npay_txn_id'],
        ]);
    }
}
