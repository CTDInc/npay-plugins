<?php
declare(strict_types=1);

namespace NPay\Sapo;

use PDO;

/**
 * Thin PDO wrapper around stores / orders / webhook_logs.
 */
class Database
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $dsn  = $config['db_dsn']  ?? 'sqlite::memory:';
        $user = $config['db_user'] ?? null;
        $pass = $config['db_pass'] ?? null;
        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    // ---------- stores ----------

    public function upsertStore(string $sapoStore, string $accessToken, ?string $scope = null): array
    {
        $existing = $this->findStoreByDomain($sapoStore);
        if ($existing) {
            $stmt = $this->pdo->prepare(
                'UPDATE stores SET access_token = :t, scope = :s WHERE id = :id'
            );
            $stmt->execute([':t' => $accessToken, ':s' => $scope, ':id' => $existing['id']]);
            return $this->findStore((int)$existing['id']);
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO stores (sapo_store, access_token, scope) VALUES (:store, :t, :s)'
        );
        $stmt->execute([':store' => $sapoStore, ':t' => $accessToken, ':s' => $scope]);
        return $this->findStore((int)$this->pdo->lastInsertId());
    }

    public function findStore(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM stores WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findStoreByDomain(string $domain): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM stores WHERE sapo_store = :d');
        $stmt->execute([':d' => $domain]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateStoreSettings(int $id, array $fields): void
    {
        $allowed = ['api_key', 'account_number', 'account_name', 'bank_code', 'webhook_secret'];
        $set = [];
        $params = [':id' => $id];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $fields)) {
                $set[] = "$k = :$k";
                $params[":$k"] = $fields[$k];
            }
        }
        if (!$set) {
            return;
        }
        $sql = 'UPDATE stores SET ' . implode(', ', $set) . ' WHERE id = :id';
        $this->pdo->prepare($sql)->execute($params);
    }

    // ---------- orders ----------

    public function createOrder(int $storeId, string $sapoOrderId, string $refCode, float $amount, array $payload = []): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO orders (sapo_store_id, sapo_order_id, ref_code, amount, payload)
             VALUES (:sid, :oid, :ref, :amt, :pl)'
        );
        $stmt->execute([
            ':sid' => $storeId,
            ':oid' => $sapoOrderId,
            ':ref' => $refCode,
            ':amt' => $amount,
            ':pl'  => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        return $this->findOrderByRef($refCode) ?? [];
    }

    public function findOrderByRef(string $ref): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE ref_code = :r');
        $stmt->execute([':r' => $ref]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findOrderById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findOrderBySapoId(int $storeId, string $sapoOrderId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM orders WHERE sapo_store_id = :s AND sapo_order_id = :o'
        );
        $stmt->execute([':s' => $storeId, ':o' => $sapoOrderId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function markOrderPaid(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE orders SET status = 'paid', paid_at = NOW() WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
    }

    public function setOrderStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE orders SET status = :s WHERE id = :id');
        $stmt->execute([':s' => $status, ':id' => $id]);
    }

    /** @return array<int, array> */
    public function listOrders(int $storeId, int $limit = 50): array
    {
        $limit = max(1, min(500, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM orders WHERE sapo_store_id = :s ORDER BY id DESC LIMIT $limit"
        );
        $stmt->execute([':s' => $storeId]);
        return $stmt->fetchAll();
    }

    // ---------- webhook log ----------

    public function logWebhook(string $source, ?string $topic, array $payload): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO webhook_logs (source, topic, payload) VALUES (:s, :t, :p)'
        );
        $stmt->execute([
            ':s' => $source,
            ':t' => $topic,
            ':p' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
