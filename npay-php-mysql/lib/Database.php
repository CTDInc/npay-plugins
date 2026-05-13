<?php
/**
 * lib/Database.php — PDO singleton cho NPay PHP+MySQL example.
 */

namespace NPay;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $pdo = null;

    private function __construct() {}

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $cfg = self::config()['db'] ?? [];
        $dsn = $cfg['dsn']      ?? '';
        $usr = $cfg['username'] ?? '';
        $pwd = $cfg['password'] ?? '';

        if ($dsn === '') {
            throw new RuntimeException('Missing db.dsn in config.php');
        }

        $options = ($cfg['options'] ?? []) + [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4'",
        ];

        try {
            self::$pdo = new PDO($dsn, $usr, $pwd, $options);
        } catch (PDOException $e) {
            // Hide creds in error
            throw new RuntimeException('DB connection failed: ' . $e->getMessage());
        }

        return self::$pdo;
    }

    /**
     * Load config.php once and cache it.
     *
     * @return array<string,mixed>
     */
    public static function config(): array
    {
        static $cfg = null;
        if ($cfg !== null) {
            return $cfg;
        }
        $path = dirname(__DIR__) . '/config.php';
        if (!is_file($path)) {
            throw new RuntimeException('config.php not found. Copy config.example.php to config.php.');
        }
        $cfg = require $path;
        if (!is_array($cfg)) {
            throw new RuntimeException('config.php must return an array.');
        }
        return $cfg;
    }
}
