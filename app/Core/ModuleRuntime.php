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
            'seo_title' => (string)($page['seo_title'] ?? ''),
            'seo_description' => (string)($page['seo_description'] ?? ''),
            'canonical_url' => (string)($page['canonical_url'] ?? ''),
            'scheduled_at' => (string)($page['scheduled_at'] ?? ''),
            'social_image_media_id' => isset($page['social_image_media_id']) ? (int)$page['social_image_media_id'] : null,
            'source_module' => (string)($page['source_module'] ?? ''),
            'source_demo' => (string)($page['source_demo'] ?? ''),
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
            'source_demo' => (string)($menu['source_demo'] ?? ''),
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
        if (!in_array($table, ['core_page_revisions','core_page_autosaves','core_demo_installs'], true)) return false;
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
            $registeredId = (int)$stmt->fetchColumn();
            if ($registeredId > 0) {
                if ($this->registeredContentExists('page', $registeredId)) continue;
                $cleanup = $this->db->prepare('DELETE FROM core_module_content_registry WHERE module_id=? AND content_type=? AND content_key=?');
                $cleanup->execute([$moduleId, 'page', $key]);
            }
            $title = trim((string)($page['title'] ?? ''));
            $slug = trim((string)($page['slug'] ?? ''));
            if ($title === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) continue;
            $stmt = $this->db->prepare('INSERT INTO core_pages (title,slug,body,status,source_module,source_demo) VALUES (?,?,?,?,?,NULL)');
            try {
                $stmt->execute([$title, $slug, htmlspecialchars((string)($page['body'] ?? ''), ENT_QUOTES, 'UTF-8'), (string)($page['status'] ?? 'published'), $moduleId]);
                $contentId = (int)$this->db->lastInsertId();
                $row = $this->db->prepare('SELECT title,slug,body,status,seo_title,seo_description,canonical_url,scheduled_at,social_image_media_id,source_module,source_demo FROM core_pages WHERE id=?');
                $row->execute([$contentId]);
                $this->register($moduleId, 'page', $key, $contentId, $this->pageHash($row->fetch(PDO::FETCH_ASSOC) ?: []));
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
            $registeredId = (int)$stmt->fetchColumn();
            if ($registeredId > 0) {
                if ($this->registeredContentExists('menu', $registeredId)) continue;
                $cleanup = $this->db->prepare('DELETE FROM core_module_content_registry WHERE module_id=? AND content_type=? AND content_key=?');
                $cleanup->execute([$moduleId, 'menu', $key]);
            }
            $menuKey = trim((string)($menu['menu_key'] ?? 'main'));
            $label = trim((string)($menu['label'] ?? ''));
            $url = trim((string)($menu['url'] ?? ''));
            if ($label === '' || $url === '' || !preg_match('/^[a-zA-Z0-9._-]{1,100}$/', $menuKey)
                || (str_starts_with($url, '/') ? str_starts_with($url, '//') : !preg_match('/^https:\\/\\//i', $url))) continue;
            $stmt = $this->db->prepare('INSERT INTO core_menus (menu_key,label,url,sort_order,source_module,source_demo) VALUES (?,?,?,?,?,NULL)');
            $stmt->execute([$menuKey, $label, $url, (int)($menu['sort_order'] ?? 0), $moduleId]);
            $contentId = (int)$this->db->lastInsertId();
            $row = $this->db->prepare('SELECT menu_key,label,url,sort_order,source_module,source_demo FROM core_menus WHERE id=?');
            $row->execute([$contentId]);
            $this->register($moduleId, 'menu', $key, $contentId, $this->menuHash($row->fetch(PDO::FETCH_ASSOC) ?: []));
            $created['menus']++;
        }
        return $created;
    }

    private function register(string $moduleId, string $type, string $key, int $contentId, string $managedHash): void
    {
        $stmt = $this->db->prepare('INSERT INTO core_module_content_registry (module_id,content_type,content_key,content_id,managed_hash) VALUES (?,?,?,?,?)');
        $stmt->execute([$moduleId, $type, $key, $contentId, $managedHash]);
    }

    private function relatedRecordsExist(string $table, int $pageId): bool
    {
        if (!$this->tableExists($table)) return false;
        $stmt = $this->db->prepare('SELECT 1 FROM ' . $table . ' WHERE page_id=? LIMIT 1');
        $stmt->execute([$pageId]);
        return (bool)$stmt->fetchColumn();
    }

    public function uninstall(string $moduleId): array
    {
        $modules = (new ModuleManager($this->db, $this->directory))->discover();
        if (!isset($modules[$moduleId])) throw new RuntimeException('Modül manifesti bulunamadı veya geçersiz.');
        $manager = new ModuleManager($this->db, $this->directory);
        $status = $manager->status($moduleId);
        if (!$status['installed']) throw new RuntimeException('Modül kurulu değil.');
        if ($status['enabled']) throw new RuntimeException('Önce modülü devre dışı bırakın.');

        foreach ($modules as $candidate) {
            if ($candidate['id'] === $moduleId || !in_array($moduleId, $candidate['dependencies'], true)) continue;
            $stmt = $this->db->prepare('SELECT 1 FROM core_module_installs WHERE module_id=? LIMIT 1');
            $stmt->execute([$candidate['id']]);
            if ($stmt->fetchColumn()) {
                throw new RuntimeException('Bu modüle bağlı kurulu modül var: ' . $candidate['id'] . '. Önce bağımlı modülü kaldırın.');
            }
        }

        $this->registry();
        $result = ['deleted_pages'=>0,'preserved_pages'=>0,'deleted_menus'=>0,'preserved_menus'=>0];
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT content_type,content_key,content_id,managed_hash FROM core_module_content_registry WHERE module_id=?');
            $stmt->execute([$moduleId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $entry) {
                $type = (string)$entry['content_type'];
                $contentId = (int)$entry['content_id'];
                $managedHash = is_string($entry['managed_hash'] ?? null) ? $entry['managed_hash'] : '';

                if ($type === 'page') {
                    $find = $this->db->prepare('SELECT id,title,slug,body,status,seo_title,seo_description,canonical_url,scheduled_at,social_image_media_id,source_module,source_demo FROM core_pages WHERE id=?');
                    $find->execute([$contentId]);
                    $page = $find->fetch(PDO::FETCH_ASSOC);
                    if (!$page) {
                        $remove = $this->db->prepare('DELETE FROM core_module_content_registry WHERE module_id=? AND content_type=? AND content_key=?');
                        $remove->execute([$moduleId,$type,$entry['content_key']]);
                        continue;
                    }
                    $hasHistory = $this->relatedRecordsExist('core_page_revisions',$contentId)
                        || $this->relatedRecordsExist('core_page_autosaves',$contentId);
                    $unchanged = $managedHash !== '' && hash_equals($managedHash,$this->pageHash($page)) && !$hasHistory;
                    if ($unchanged && (string)($page['source_module'] ?? '') === $moduleId) {
                        $remove = $this->db->prepare('DELETE FROM core_pages WHERE id=?');
                        $remove->execute([$contentId]);
                        $remove = $this->db->prepare('DELETE FROM core_module_content_registry WHERE module_id=? AND content_type=? AND content_key=?');
                        $remove->execute([$moduleId,$type,$entry['content_key']]);
                        $result['deleted_pages']++;
                    } else {
                        if ((string)($page['source_module'] ?? '') === $moduleId) {
                            $detach = $this->db->prepare('UPDATE core_pages SET source_module=NULL,source_demo=NULL WHERE id=?');
                            $detach->execute([$contentId]);
                        }
                        $preserve = $this->db->prepare('UPDATE core_module_content_registry SET managed_hash=NULL WHERE module_id=? AND content_type=? AND content_key=?');
                        $preserve->execute([$moduleId,$type,$entry['content_key']]);
                        $result['preserved_pages']++;
                    }
                    continue;
                }

                if ($type === 'menu') {
                    $find = $this->db->prepare('SELECT id,menu_key,label,url,sort_order,source_module,source_demo FROM core_menus WHERE id=?');
                    $find->execute([$contentId]);
                    $menu = $find->fetch(PDO::FETCH_ASSOC);
                    if (!$menu) {
                        $remove = $this->db->prepare('DELETE FROM core_module_content_registry WHERE module_id=? AND content_type=? AND content_key=?');
                        $remove->execute([$moduleId,$type,$entry['content_key']]);
                        continue;
                    }
                    $unchanged = $managedHash !== '' && hash_equals($managedHash,$this->menuHash($menu));
                    if ($unchanged && (string)($menu['source_module'] ?? '') === $moduleId) {
                        $remove = $this->db->prepare('DELETE FROM core_menus WHERE id=?');
                        $remove->execute([$contentId]);
                        $remove = $this->db->prepare('DELETE FROM core_module_content_registry WHERE module_id=? AND content_type=? AND content_key=?');
                        $remove->execute([$moduleId,$type,$entry['content_key']]);
                        $result['deleted_menus']++;
                    } else {
                        if ((string)($menu['source_module'] ?? '') === $moduleId) {
                            $detach = $this->db->prepare('UPDATE core_menus SET source_module=NULL,source_demo=NULL WHERE id=?');
                            $detach->execute([$contentId]);
                        }
                        $preserve = $this->db->prepare('UPDATE core_module_content_registry SET managed_hash=NULL WHERE module_id=? AND content_type=? AND content_key=?');
                        $preserve->execute([$moduleId,$type,$entry['content_key']]);
                        $result['preserved_menus']++;
                    }
                    continue;
                }

                // Unknown future content types are retained conservatively.
                $preserve = $this->db->prepare('UPDATE core_module_content_registry SET managed_hash=NULL WHERE module_id=? AND content_type=? AND content_key=?');
                $preserve->execute([$moduleId,$type,$entry['content_key']]);
            }

            // Module tables and migration history intentionally remain untouched.
            $remove = $this->db->prepare('DELETE FROM core_modules WHERE module_id=?');
            $remove->execute([$moduleId]);
            $remove = $this->db->prepare('DELETE FROM core_module_installs WHERE module_id=?');
            $remove->execute([$moduleId]);
            if ($this->tableExists('core_demo_installs')) {
                $remove = $this->db->prepare('DELETE FROM core_demo_installs WHERE module_id=?');
                $remove->execute([$moduleId]);
            }
            $this->db->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw new RuntimeException('Modül güvenli şekilde kaldırılamadı: ' . $e->getMessage(), 0, $e);
        }
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
                $frontendRoot = realpath($module['_path'] . '/Frontend');
                $handlerRelative = preg_replace('#^Frontend/#','',ltrim($route['handler'],'/'));
                $handlerPath = ($frontendRoot !== false && is_string($handlerRelative)
                    && $handlerRelative !== '' && !str_contains($handlerRelative,'..') && !str_contains($handlerRelative,'\\'))
                    ? realpath($frontendRoot . '/' . $handlerRelative) : false;
                if ($handlerPath === false || $frontendRoot === false
                    || !str_starts_with($handlerPath, $frontendRoot . DIRECTORY_SEPARATOR)
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

    public function renderTemplate(string $moduleId, string $templateKey, array $data = []): string
    {
        $modules=(new ModuleManager($this->db,$this->directory))->enabledModules();
        if(!isset($modules[$moduleId])) throw new RuntimeException('Şablon için modül etkin değil.');
        if(!preg_match('/^[a-z0-9][a-z0-9._-]{0,79}$/',$templateKey)) throw new RuntimeException('Geçersiz modül şablon anahtarı.');
        $templates=$modules[$moduleId]['templates']??[];
        $relative=is_array($templates)?($templates[$templateKey]??null):null;
        if(!is_string($relative)||$relative===''||str_starts_with($relative,'/')||str_contains($relative,'..')||str_contains($relative,'\\')) {
            throw new RuntimeException('Modül şablonu tanımlanmamış veya yolu geçersiz.');
        }
        $root=realpath($modules[$moduleId]['_path'].'/templates');
        $file=$root!==false?realpath($root.'/'.$relative):false;
        if($root===false||$file===false||!str_starts_with($file,$root.DIRECTORY_SEPARATOR)||!is_file($file)||strtolower(pathinfo($file,PATHINFO_EXTENSION))!=='php') {
            throw new RuntimeException('Modül şablon dosyası bulunamadı.');
        }
        $db=$this->db; $module=$modules[$moduleId]; $runtime=$this;
        extract($data,EXTR_SKIP);
        ob_start();
        try {
            require $file;
            return (string)ob_get_clean();
        } catch(Throwable $e) {
            ob_end_clean();
            throw new RuntimeException('Modül şablonu oluşturulamadı: '.$e->getMessage(),0,$e);
        }
    }

    public function assetUrl(string $moduleId, string $relativePath): string
    {
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{1,79}$/',$moduleId)
            || $relativePath==='' || str_starts_with($relativePath,'/')
            || str_contains($relativePath,'..') || str_contains($relativePath,'\\')
            || !preg_match('#^[A-Za-z0-9._/-]+$#',$relativePath)) {
            throw new RuntimeException('Geçersiz modül varlığı yolu.');
        }
        $modules=(new ModuleManager($this->db,$this->directory))->enabledModules();
        if(!isset($modules[$moduleId])) throw new RuntimeException('Varlık için modül etkin değil.');
        $declared=$modules[$moduleId]['assets']??[];
        if(!is_array($declared)||!in_array($relativePath,array_values($declared),true)) {
            throw new RuntimeException('Modül varlığı manifest içinde tanımlı değil.');
        }
        $root=realpath($modules[$moduleId]['_path'].'/assets');
        $file=$root!==false?realpath($root.'/'.$relativePath):false;
        $allowed=['css','js','svg','png','jpg','jpeg','webp','gif','ico','woff','woff2','ttf','otf'];
        if($root===false||$file===false||!str_starts_with($file,$root.DIRECTORY_SEPARATOR)||!is_file($file)
            ||!in_array(strtolower(pathinfo($file,PATHINFO_EXTENSION)),$allowed,true)) {
            throw new RuntimeException('Modül varlığı bulunamadı veya güvenli bir dosya türü değil.');
        }
        return Url::to('/modules/'.rawurlencode($moduleId).'/assets/'.implode('/',array_map('rawurlencode',explode('/',$relativePath))));
    }
}
