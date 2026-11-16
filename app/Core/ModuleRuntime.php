<?php
declare(strict_types=1);

namespace CoreCMS\\Core;

use PDO;
use RuntimeException;
use Throwable;

final class ModuleRuntime
{
    public function __construct(private PDO $db, private string $directory) {}

    private function registry(): void
    {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $this->db->exec('CREATE TABLE IF NOT EXISTS core_module_content_registry (
                module_id TEXT NOT NULL, content_type TEXT NOT NULL, content_key TEXT NOT NULL,
                content_id INTEGER NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (module_id,content_type,content_key)
            )');
            return;
        }
        $this->db->exec("CREATE TABLE IF NOT EXISTS core_module_content_registry (
            module_id VARCHAR(100) NOT NULL,
            content_type VARCHAR(20) NOT NULL,
            content_key VARCHAR(100) NOT NULL,
            content_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (module_id,content_type,content_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function syncContent(string $moduleId): array
    {
        $modules = (new ModuleManager($this->db, $this->directory))->discover();
        if (!isset($modules[$moduleId])) throw new RuntimeException('Modül bulunamadı.');
        $module = $modules[$moduleId];
        $this->registry();
        $created = ['pages' => 0, 'menus' => 0];

        foreach (($module['pages'] ?? []) as $key => $page) {
            if (!is_array($page)) continue;
            $key = is_string($key) ? $key : (string)($page['key'] ?? '');
            if (!preg_match('/^[a-z0-9][a-z0-9._-]{0,99}$/', $key)) continue;
            $stmt = $this->db->prepare('SELECT content_id FROM core_module_content_registry WHERE module_id=? AND content_type=? AND content_key=?');
            $stmt->execute([$moduleId, 'page', $key]);
            if ($stmt->fetchColumn()) continue;
            $title = trim((string)($page['title'] ?? ''));
            $slug = trim((string)($page['slug'] ?? ''));
            if ($title === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) continue;
            $stmt = $this->db->prepare('INSERT INTO core_pages (title,slug,body,status,source_module,source_demo) VALUES (?,?,?,?,?,NULL)');
            try {
                $stmt->execute([$title, $slug, (string)($page['body'] ?? ''), (string)($page['status'] ?? 'published'), $moduleId]);
                $contentId = (int)$this->db->lastInsertId();
                $this->register($moduleId, 'page', $key, $contentId);
                $created['pages']++;
            } catch (Throwable $e) {
                error_log('[CoreCMS] Module page provisioning skipped (' . $moduleId . '/' . $key . '): ' . $e->getMessage());
            }
        }

        foreach (($module['menus'] ?? []) as $key => $menu) {
            if (!is_array($menu)) continue;
            $key = is_string($key) ? $key : (string)($menu['key'] ?? '');
            if (!preg_match('/^[a-z0-9][a-z0-9._-]{0,99}$/', $key)) continue;
            $stmt = $this->db->prepare('SELECT content_id FROM core_module_content_registry WHERE module_id=? AND content_type=? AND content_key=?');
            $stmt->execute([$moduleId, 'menu', $key]);
            if ($stmt->fetchColumn()) continue;
            $menuKey = trim((string)($menu['menu_key'] ?? 'main'));
            $label = trim((string)($menu['label'] ?? ''));
            $url = trim((string)($menu['url'] ?? ''));
            if ($label === '' || $url === '' || !preg_match('/^[a-zA-Z0-9._-]{1,100}$/', $menuKey)
                || (!str_starts_with($url, '/') && !preg_match('/^https:\\/\\//i', $url))) continue;
            $stmt = $this->db->prepare('INSERT INTO core_menus (menu_key,label,url,sort_order,source_module,source_demo) VALUES (?,?,?,?,?,NULL)');
            $stmt->execute([$menuKey, $label, $url, (int)($menu['sort_order'] ?? 0), $moduleId]);
            $this->register($moduleId, 'menu', $key, (int)$this->db->lastInsertId());
            $created['menus']++;
        }
        return $created;
    }

    private function register(string $moduleId, string $type, string $key, int $contentId): void
    {
        $stmt = $this->db->prepare('INSERT INTO core_module_content_registry (module_id,content_type,content_key,content_id) VALUES (?,?,?,?)');
        $stmt->execute([$moduleId, $type, $key, $contentId]);
    }

    private function routeMatch(string $pattern, string $path): ?array
    {
        $names = [];
        $regex = preg_replace_callback('/\\{([a-zA-Z][a-zA-Z0-9_]*)\\}/', static function (array $m) use (&$names): string {
            $names[] = $m[1];
            return '([^/]+)';
        }, $pattern);
        if (!is_string($regex) || !preg_match('#^' . $regex . '/?$#D', $path, $matches)) return null;
        array_shift($matches);
        $params = [];
        foreach ($names as $index => $name) $params[$name] = rawurldecode($matches[$index] ?? '');
        return $params;
    }

    public function dispatch(string $path, string $method = 'GET'): bool
    {
        $manager = new ModuleManager($this->db, $this->directory);
        foreach ($manager->enabledModules() as $module) {
            foreach (($module['routes'] ?? []) as $route) {
                if (!is_array($route) || !isset($route['path'], $route['handler'])
                    || !is_string($route['path']) || !is_string($route['handler'])) continue;
                $methods = array_map('strtoupper', (array)($route['methods'] ?? ['GET']));
                if (!in_array(strtoupper($method), $methods, true)) continue;
                if (!str_starts_with($route['path'], '/') || str_contains($route['path'], '..')) continue;
                $params = $this->routeMatch($route['path'], $path);
                if ($params === null) continue;
                $handlerPath = realpath($module['_path'] . '/' . ltrim($route['handler'], '/'));
                $moduleRoot = realpath($module['_path']);
                if ($handlerPath === false || $moduleRoot === false
                    || !str_starts_with($handlerPath, $moduleRoot . DIRECTORY_SEPARATOR)
                    || !is_file($handlerPath)) {
                    http_response_code(500);
                    echo 'Modül rota işleyicisi bulunamadı.';
                    return true;
                }
                try {
                    $handler = require $handlerPath;
                    if (!is_callable($handler)) throw new RuntimeException('Route handler must return a callable.');
                    $result = $handler($this->db, $params, $_REQUEST, $module);
                    if (is_string($result)) echo $result;
                    elseif ($result !== null) throw new RuntimeException('Route handler must return string or null.');
                } catch (Throwable $e) {
                    error_log('[CoreCMS] Module route failed: ' . $module['id'] . ' ' . $e->getMessage());
                    http_response_code(500);
                    echo 'Sayfa şu anda görüntülenemiyor.';
                }
                return true;
            }
        }
        return false;
    }

    public function theme(): ?array
    {
        foreach ((new ModuleManager($this->db, $this->directory))->enabledModules() as $module) {
            $theme = $module['theme'] ?? null;
            if (!is_array($theme) || !isset($theme['directory']) || !is_string($theme['directory'])) continue;
            $path = realpath($module['_path'] . '/' . $theme['directory']);
            if ($path === false || !str_starts_with($path, $module['_path'] . DIRECTORY_SEPARATOR) || !is_dir($path)) continue;
            return ['module_id' => $module['id'], 'name' => (string)($theme['name'] ?? $module['name']), 'path' => $path];
        }
        return null;
    }

    public function assetUrl(string $moduleId, string $relativePath): string
    {
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{1,79}$/', $moduleId)
            || $relativePath === '' || str_contains($relativePath, '..')
            || str_starts_with($relativePath, '/')) {
            throw new RuntimeException('Geçersiz modül varlığı yolu.');
        }
        return Url::to('/modules/' . rawurlencode($moduleId) . '/assets/' . implode('/', array_map('rawurlencode', explode('/', $relativePath))));
    }
}
