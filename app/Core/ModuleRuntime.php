<?php
declare(strict_types=1);

namespace CoreCMS\Core;

use PDO;
use RuntimeException;
use Throwable;

final class ModuleRuntime
{
    public function __construct(private PDO $db, private string $directory) {}

    private function registry(): void
    {
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $this->db->exec('CREATE TABLE IF NOT EXISTS core_module_content_registry (
                module_id TEXT NOT NULL, content_type TEXT NOT NULL, content_key TEXT NOT NULL,
                content_id INTEGER NOT NULL, managed_hash TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (module_id,content_type,content_key)
            )');
            $columns = $this->db->query('PRAGMA table_info(core_module_content_registry)')->fetchAll(PDO::FETCH_ASSOC);
            $hasHash = false;
            foreach ($columns as $column) if (($column['name'] ?? '') === 'managed_hash') $hasHash = true;
            if (!$hasHash) $this->db->exec('ALTER TABLE core_module_content_registry ADD COLUMN managed_hash TEXT NULL');
            return;
        }
        $this->db->exec("CREATE TABLE IF NOT EXISTS core_module_content_registry (
            module_id VARCHAR(100) NOT NULL,
            content_type VARCHAR(20) NOT NULL,
            content_key VARCHAR(100) NOT NULL,
            content_id BIGINT UNSIGNED NOT NULL,
            managed_hash CHAR(64) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (module_id,content_type,content_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $check = $this->db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $check->execute(['core_module_content_registry','managed_hash']);
        if ((int)$check->fetchColumn() === 0) $this->db->exec('ALTER TABLE core_module_content_registry ADD COLUMN managed_hash CHAR(64) NULL');
    }

    private function contentHash(array $values): string
    {
        return hash('sha256', json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR));
    }

    private function pageHash(array $page): string
    {
        return $this->contentHash([
            'title' => (string)$page['title'],
            'slug' => (string)$page['slug'],
            'body' => (string)($page['body'] ?? ''),
            'status' => (string)$page['status'],
            'source_module' => (string)($page['source_module'] ?? ''),
        ]);
    }

    private function menuHash(array $menu): string
    {
        return $this->contentHash([
            'menu_key' => (string)$menu['menu_key'],
            'label' => (string)$menu['label'],
            'url' => (string)$menu['url'],
            'sort_order' => (int)$menu['sort_order'],
            'source_module' => (string)($menu['source_module'] ?? ''),
        ]);
    }

    private function registeredContentExists(string $type, int $id): bool
    {
        $table = $type === 'page' ? 'core_pages' : ($type === 'menu' ? 'core_menus' : '');
        if ($table === '') return true;
        $stmt = $this->db->prepare('SELECT 1 FROM ' . $table . ' WHERE id=?');
        $stmt->execute([$id]);
        return (bool)$stmt->fetchColumn();
    }

    private function tableExists(string $table): bool
    {
        if (!in_array($table, ['core_page_revisions','core_page_autosaves'], true)) return false;
        if ((string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $this->db->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        }
        $stmt = $this->db->prepare('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
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
                $stmt->execute([$title, $slug, htmlspecialchars((string)($page['body'] ?? ''), ENT_QUOTES, 'UTF-8'), (string)($page['status'] ?? 'published'), $moduleId]);
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
                || (str_starts_with($url, '/') ? str_starts_with($url, '//') : !preg_match('/^https:\\/\\//i', $url))) continue;
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
                if (!preg_match('#^/[A-Za-z0-9/_-]*(?:\\{[a-zA-Z][a-zA-Z0-9_]*\\}[A-Za-z0-9/_-]*)*$#D', $route['path'])) continue;
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
        require_once __DIR__ . '/ThemeManager.php';
        return (new ThemeManager($this->db, $this->directory))->active();
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
