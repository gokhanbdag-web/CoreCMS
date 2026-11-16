<?php
declare(strict_types=1);

namespace CoreCMS\Core;

final class Url
{
    public static function basePath(): string
    {
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $script = '/' . ltrim($script, '/');
        if (preg_match('#/admin/[^/]+\.php$#', $script) === 1) {
            $base = dirname(dirname($script));
            return $base === '/' || $base === '.' ? '' : rtrim($base, '/');
        }
        foreach (['/admin/index.php', '/installer/index.php', '/index.php'] as $suffix) {
            if (str_ends_with($script, $suffix)) {
                $base = substr($script, 0, -strlen($suffix));
                return $base === '' ? '' : rtrim($base, '/');
            }
        }
        $directory = rtrim(str_replace('\\', '/', dirname($script)), '/');
        return $directory === '' || $directory === '.' ? '' : $directory;
    }

    public static function path(): string
    {
        $uriPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = '/' . trim(is_string($uriPath) ? $uriPath : '/', '/');
        $base = self::basePath();
        if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
            $path = substr($path, strlen($base)) ?: '/';
        }
        return $path === '//' ? '/' : $path;
    }

    public static function to(string $path = '/'): string
    {
        return self::basePath() . '/' . ltrim($path, '/');
    }

    public static function asset(string $path): string
    {
        return self::to('/assets/' . ltrim($path, '/'));
    }
}
