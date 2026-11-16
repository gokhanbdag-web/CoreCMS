<?php
declare(strict_types=1);

namespace CoreCMS\Core;

use PDO;

final class Auth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
        session_name('corecms_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => Url::basePath() !== '' ? Url::basePath() . '/' : '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function attempt(PDO $db, string $email, string $password): bool
    {
        $stmt = $db->prepare(
            "SELECT id, name, password_hash, role FROM core_users WHERE email=? AND status='active' LIMIT 1"
        );
        $stmt->execute([trim($email)]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !password_verify($password, (string)$user['password_hash'])) return false;
        if (!in_array($user['role'], ['admin', 'super_admin'], true)) return false;

        session_regenerate_id(true);
        unset($_SESSION['_corecms_csrf']);
        $_SESSION['corecms_user_id'] = (int)$user['id'];
        $_SESSION['corecms_user_name'] = (string)$user['name'];
        $_SESSION['corecms_user_role'] = (string)$user['role'];
        return true;
    }

    public static function check(): bool
    {
        return !empty($_SESSION['corecms_user_id'])
            && in_array($_SESSION['corecms_user_role'] ?? '', ['admin', 'super_admin'], true);
    }

    public static function requireAdmin(): void
    {
        if (self::check()) return;
        header('Location: ' . Url::to('/admin/'));
        exit;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }
}
