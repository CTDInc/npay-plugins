<?php
declare(strict_types=1);

namespace NPay\Haravan;

/**
 * Admin access control.
 *
 * Two ways in, both stored in a PHP session:
 *  - operator: `admin_password` from config, compared in constant time → every shop;
 *  - shop owner: finishing the Haravan OAuth install (/install → /oauth/callback) → that shop only.
 * An empty `admin_password` disables the operator login entirely.
 */
class AdminAuth
{
    private const SESSION_NAME = 'npay_haravan_admin';
    private const SESSION_TTL  = 8 * 3600;

    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = str_starts_with((string)($this->config['app_url'] ?? ''), 'https://')
            || (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off');
        session_name(self::SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();

        $now = time();
        if (isset($_SESSION['expires_at']) && $_SESSION['expires_at'] < $now) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['expires_at'] = $now + self::SESSION_TTL;
    }

    public function passwordConfigured(): bool
    {
        return $this->password() !== '';
    }

    public function login(string $password): bool
    {
        $this->start();
        $expected = $this->password();
        if ($expected === '' || !hash_equals($expected, $password)) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['operator'] = true;
        return true;
    }

    public function logout(): void
    {
        $this->start();
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public function grantShop(string $shop): void
    {
        $this->start();
        session_regenerate_id(true);
        $shops = $_SESSION['shops'] ?? [];
        if (!in_array($shop, $shops, true)) {
            $shops[] = $shop;
        }
        $_SESSION['shops'] = $shops;
    }

    public function isOperator(): bool
    {
        $this->start();
        return ($_SESSION['operator'] ?? false) === true && $this->passwordConfigured();
    }

    public function isAuthenticated(): bool
    {
        return $this->isOperator() || $this->grantedShops() !== [];
    }

    /** @return string[] */
    public function grantedShops(): array
    {
        $this->start();
        return array_values(array_filter($_SESSION['shops'] ?? [], 'is_string'));
    }

    public function csrfToken(): string
    {
        $this->start();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['csrf'];
    }

    public function checkCsrf(string $token): bool
    {
        $this->start();
        $expected = (string)($_SESSION['csrf'] ?? '');
        return $expected !== '' && hash_equals($expected, $token);
    }

    private function password(): string
    {
        $pw = (string)($this->config['admin_password'] ?? '');
        return $pw === 'CHANGE_ME' ? '' : $pw;
    }
}
