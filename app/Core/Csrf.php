<?php
declare(strict_types=1);

namespace CoreCMS\Core;

final class Csrf
{
    private const SESSION_KEY = '_corecms_csrf';

    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new \LogicException('Session must be started before generating a CSRF token.');
        }
        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' .
            htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function verify(?string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE || !is_string($token) || $token === '') {
            return false;
        }
        $known = $_SESSION[self::SESSION_KEY] ?? '';
        return is_string($known) && $known !== '' && hash_equals($known, $token);
    }

    public static function requireValidPost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }
        if (!self::verify(isset($_POST['_csrf']) ? (string)$_POST['_csrf'] : null)) {
            http_response_code(419);
            exit('Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.');
        }
    }
}
