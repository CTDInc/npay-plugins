<?php

declare(strict_types=1);

namespace NPay\Haravan;

use PDO;

class Database
{
    private PDO $pdo;
    private string $driver;

    public function __construct(array $config)
    {
        $dsn  = (string)($config['db_dsn']  ?? '');
        $user = $config['db_user'] ?? null;
        $pass = $config['db_pass'] ?? null;

        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function migrate(): void
    {
        if ($this->driver === 'sqlite') {
            $this->pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS haravan_shops (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    shop TEXT NOT NULL UNIQUE,
    access_token TEXT NOT NULL,
    scope TEXT,
    installed_at TEXT
);
CREATE TABLE IF NOT EXISTS payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    shop TEXT NOT NULL,
    order_id TEXT NOT NULL,
    order_number TEXT,
    order_code TEXT NOT NULL UNIQUE,
    amount REAL NOT NULL,
    currency TEXT DEFAULT 'VND',
    customer_name TEXT,
    customer_email TEXT,
    customer_phone TEXT,
    status TEXT NOT NULL DEFAULT 'pending',
    npay_txn_id TEXT,
    paid_at TEXT,
    created_at TEXT,
    updated_at TEXT
);
CREATE INDEX IF NOT EXISTS idx_payments_order ON payments(shop, order_id);
CREATE INDEX IF NOT EXISTS idx_payments_status ON payments(status);
SQL
            );
        } else {
            $this->pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS haravan_shops (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    shop VARCHAR(255) NOT NULL UNIQUE,
    access_token VARCHAR(255) NOT NULL,
    scope VARCHAR(255) NULL,
    installed_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
            );
            $this->pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    shop VARCHAR(255) NOT NULL,
    order_id VARCHAR(64) NOT NULL,
    order_number VARCHAR(64) NULL,
    order_code VARCHAR(64) NOT NULL UNIQUE,
    amount DECIMAL(15,2) NOT NULL,
    currency VARCHAR(8) NOT NULL DEFAULT 'VND',
    customer_name VARCHAR(255) NULL,
    customer_email VARCHAR(255) NULL,
    customer_phone VARCHAR(64) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    npay_txn_id VARCHAR(128) NULL,
    paid_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    INDEX idx_payments_order (shop, order_id),
    INDEX idx_payments_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
            );
        }
    }

    public function upsertShop(array $row): void
    {
        if ($this->driver === 'sqlite') {
            $sql = 'INSERT INTO haravan_shops (shop, access_token, scope, installed_at)
                    VALUES (:shop, :access_token, :scope, :installed_at)
                    ON CONFLICT(shop) DO UPDATE SET
                        access_token = excluded.access_token,
                        scope        = excluded.scope,
                        installed_at = excluded.installed_at';
        } else {
            $sql = 'INSERT INTO haravan_shops (shop, access_token, scope, installed_at)
                    VALUES (:shop, :access_token, :scope, :installed_at)
                    ON DUPLICATE KEY UPDATE
                        access_token = VALUES(access_token),
                        scope        = VALUES(scope),
                        installed_at = VALUES(installed_at)';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($row);
    }

    public function findShop(string $shop): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM haravan_shops WHERE shop = :s LIMIT 1');
        $stmt->execute([':s' => $shop]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findFirstShop(): ?array
    {
        $stmt = $this->pdo->query('SELECT * FROM haravan_shops ORDER BY id ASC LIMIT 1');
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createPayment(array $row): int
    {
        $row['created_at'] = $row['created_at'] ?? date('Y-m-d H:i:s');
        $row['updated_at'] = $row['updated_at'] ?? $row['created_at'];
        $sql = 'INSERT INTO payments
                (shop, order_id, order_number, order_code, amount, currency,
                 customer_name, customer_email, customer_phone, status, created_at, updated_at)
                VALUES
                (:shop, :order_id, :order_number, :order_code, :amount, :currency,
                 :customer_name, :customer_email, :customer_phone, :status, :created_at, :updated_at)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($row);
        return (int)$this->pdo->lastInsertId();
    }

    public function findPaymentByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payments WHERE order_code = :c LIMIT 1');
        $stmt->execute([':c' => $code]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findPaymentByOrderId(string $orderId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payments WHERE order_id = :o ORDER BY id DESC LIMIT 1');
        $stmt->execute([':o' => $orderId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function markPaid(int $id, string $txnId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE payments
             SET status = \'paid\', npay_txn_id = :t, paid_at = :p, updated_at = :p
             WHERE id = :id'
        );
        $stmt->execute([
            ':t'  => $txnId,
            ':p'  => date('Y-m-d H:i:s'),
            ':id' => $id,
        ]);
    }

    public function listRecentOrders(int $limit = 50): array
    {
        $limit = max(1, min(500, $limit));
        $stmt = $this->pdo->query('SELECT * FROM payments ORDER BY id DESC LIMIT ' . $limit);
        return $stmt->fetchAll();
    }
}
