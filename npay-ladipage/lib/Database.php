<?php
/**
 * Tiny PDO/SQLite wrapper for npay-ladipage.
 *
 * Schema:
 *   orders(id, ref_code, amount, status, ladipage_data, created_at, paid_at, npay_payload)
 */

class Database
{
    /** @var PDO */
    private $pdo;

    public function __construct(string $dbPath)
    {
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $this->pdo = new PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA journal_mode=WAL;');
        $this->pdo->exec('PRAGMA foreign_keys=ON;');

        $this->migrate();
    }

    private function migrate(): void
    {
        $this->pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS orders (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    ref_code        TEXT NOT NULL UNIQUE,
    amount          INTEGER NOT NULL DEFAULT 0,
    status          TEXT NOT NULL DEFAULT 'pending', -- pending|paid|expired|cancelled
    ladipage_data   TEXT,        -- JSON snapshot of LadiPage form payload
    npay_payload    TEXT,        -- JSON snapshot of NPay webhook payload (when paid)
    created_at      TEXT NOT NULL,
    paid_at         TEXT
);
SQL
        );

        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_orders_status   ON orders(status);');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_orders_ref_code ON orders(ref_code);');
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Create a pending order. Returns the inserted row.
     */
    public function createOrder(string $refCode, int $amount, array $ladipageData): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO orders (ref_code, amount, status, ladipage_data, created_at)
             VALUES (:ref, :amt, "pending", :data, :ts)'
        );
        $stmt->execute([
            ':ref'  => $refCode,
            ':amt'  => $amount,
            ':data' => json_encode($ladipageData, JSON_UNESCAPED_UNICODE),
            ':ts'   => gmdate('Y-m-d H:i:s'),
        ]);
        return $this->findByRef($refCode);
    }

    public function findByRef(string $refCode): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE ref_code = :ref LIMIT 1');
        $stmt->execute([':ref' => $refCode]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Match an incoming NPay webhook payment to a pending order by ref_code
     * appearing anywhere inside the transfer content.
     */
    public function matchByContent(string $content, ?int $amount = null): ?array
    {
        $sql = 'SELECT * FROM orders WHERE status = "pending"';
        if ($amount !== null) {
            $sql .= ' AND amount = ' . (int) $amount;
        }
        $sql .= ' ORDER BY id DESC LIMIT 200';

        foreach ($this->pdo->query($sql) as $row) {
            if (stripos($content, $row['ref_code']) !== false) {
                return $row;
            }
        }
        return null;
    }

    public function markPaid(int $id, array $npayPayload): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE orders
               SET status = "paid",
                   paid_at = :ts,
                   npay_payload = :payload
             WHERE id = :id AND status = "pending"'
        );
        $stmt->execute([
            ':ts'      => gmdate('Y-m-d H:i:s'),
            ':payload' => json_encode($npayPayload, JSON_UNESCAPED_UNICODE),
            ':id'      => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * @return array<int, array>
     */
    public function listOrders(?string $status = null, int $limit = 100): array
    {
        if ($status) {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM orders WHERE status = :s ORDER BY id DESC LIMIT :lim'
            );
            $stmt->bindValue(':s', $status);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        } else {
            $stmt = $this->pdo->prepare('SELECT * FROM orders ORDER BY id DESC LIMIT :lim');
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function counts(): array
    {
        $out = ['pending' => 0, 'paid' => 0, 'expired' => 0, 'cancelled' => 0, 'total' => 0];
        $rs = $this->pdo->query('SELECT status, COUNT(*) c FROM orders GROUP BY status');
        foreach ($rs as $r) {
            $out[$r['status']] = (int) $r['c'];
            $out['total'] += (int) $r['c'];
        }
        return $out;
    }

    public function expireStale(int $expirySeconds): int
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - $expirySeconds);
        $stmt = $this->pdo->prepare(
            'UPDATE orders SET status = "expired"
              WHERE status = "pending" AND created_at < :cut'
        );
        $stmt->execute([':cut' => $cutoff]);
        return $stmt->rowCount();
    }
}
