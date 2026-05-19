<?php
declare(strict_types=1);

namespace App;

final class Auth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = (bool)Config::get('session_secure_cookie', true);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name('IWOSESSID');
        session_start();
    }

    public static function attempt(string $username, string $password): bool
    {
        $stmt = Database::pdo()->prepare(
            "SELECT id, username, password_hash, name, role, active FROM users WHERE username = ? LIMIT 1"
        );
        $stmt->execute([$username]);
        $u = $stmt->fetch();
        if (!$u || (int)$u['active'] !== 1) {
            return false;
        }
        if (!password_verify($password, $u['password_hash'])) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'       => (int)$u['id'],
            'username' => $u['username'],
            'name'     => $u['name'],
            'role'     => $u['role'],
        ];
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']['id']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function role(): ?string
    {
        return $_SESSION['user']['role'] ?? null;
    }

    public static function userId(): ?int
    {
        return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
    }

    /**
     * Role-based authorisation.
     * 'admin'   -> all
     * 'operator'-> read + write (no user mgmt)
     * 'viewer'  -> read only
     */
    public static function can(string $action): bool
    {
        $role = self::role();
        if ($role === null) return false;
        if ($role === 'admin') return true;
        return match ($action) {
            'view'   => true,
            'write'  => in_array($role, ['admin', 'operator'], true),
            'admin'  => $role === 'admin',
            default  => false,
        };
    }
}
