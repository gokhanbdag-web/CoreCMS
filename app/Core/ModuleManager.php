<?php
declare(strict_types=1);

namespace CoreCMS\Core;

use PDO;
use RuntimeException;
use Throwable;

final class ModuleManager
{
    public function __construct(private PDO $db, private string $directory) {}

    public function discover(): array
    {
        $root = realpath($this->directory);
        if ($root === false || !is_dir($root)) return [];

        $modules = [];
        foreach (glob($root . '/*/module.json') ?: [] as $manifest) {
            $modulePath = realpath(dirname($manifest));
            if ($modulePath === false || !str_starts_with($modulePath, $root . DIRECTORY_SEPARATOR)) continue;
            $raw = file_get_contents($manifest);
            if (!is_string($raw) || strlen($raw) > 65536) continue;
            $data = json_decode($raw, true);
            if (!is_array($data)
                || !isset($data['id'], $data['name'], $data['version'])
                || !is_string($data['id'])
                || !preg_match('/^[a-z0-9][a-z0-9._-]{1,79}$/', $data['id'])
                || $data['id'] !== basename($modulePath)
                || !is_string($data['name']) || trim($data['name']) === ''
                || !is_string($data['version'])
                || !preg_match('/^\d+\.\d+\.\d+(?:[-+][a-zA-Z0-9.-]+)?$/', $data['version'])) continue;

            $dependencies = $data['dependencies'] ?? [];
            if (!is_array($dependencies)) continue;
            $valid = true;
            foreach ($dependencies as $dependency) {
                if (!is_string($dependency) || !preg_match('/^[a-z0-9][a-z0-9._-]{1,79}$/', $dependency)
                    || $dependency === $data['id']) { $valid = false; break; }
            }
            if (!$valid || count(array_unique($dependencies)) !== count($dependencies)) continue;
            $data['dependencies'] = array_values($dependencies);
            $data['_path'] = $modulePath;
            $modules[$data['id']] = $data;
        }
        ksort($modules);
        return $modules;
    }

    private function ensureRepositories(): void
    {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $this->db->exec('CREATE TABLE IF NOT EXISTS core_module_installs (module_id TEXT PRIMARY KEY, version TEXT NOT NULL, installed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
            $this->db->exec('CREATE TABLE IF NOT EXISTS core_module_migrations (module_id TEXT NOT NULL, migration TEXT NOT NULL, applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (module_id,migration))');
            return;
        }
        $this->db->exec("CREATE TABLE IF NOT EXISTS core_module_installs (
            module_id VARCHAR(100) PRIMARY KEY,
            version VARCHAR(40) NOT NULL,
            installed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->exec("CREATE TABLE IF NOT EXISTS core_module_migrations (
            module_id VARCHAR(100) NOT NULL,
            migration VARCHAR(190) NOT NULL,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (module_id,migration)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function ensureUpdateHistory(): void
    {
        if ((string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->db->exec('CREATE TABLE IF NOT EXISTS core_module_update_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                module_id TEXT NOT NULL,
                from_version TEXT NOT NULL,
                to_version TEXT NOT NULL,
                status TEXT NOT NULL,
                migrations_json TEXT NOT NULL,
                is_reversible INTEGER NOT NULL DEFAULT 0,
                error_text TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                rolled_back_at TEXT NULL
            )');
            return;
        }
        $this->db->exec("CREATE TABLE IF NOT EXISTS core_module_update_history (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            module_id VARCHAR(100) NOT NULL,
            from_version VARCHAR(40) NOT NULL,
            to_version VARCHAR(40) NOT NULL,
            status VARCHAR(30) NOT NULL,
            migrations_json LONGTEXT NOT NULL,
            is_reversible TINYINT(1) NOT NULL DEFAULT 0,
            error_text TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            rolled_back_at TIMESTAMP NULL,
            INDEX idx_module_update_history (module_id,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function invokeUpdateHook(array $module, string $hookName, array $context): void
    {
        $hooks = $module['update_hooks'] ?? [];
        if (!is_array($hooks) || !isset($hooks[$hookName])) return;
        $relative = $hooks[$hookName];
        if (!is_string($relative) || $relative === '' || str_starts_with($relative, '/')
            || str_contains($relative, '..') || str_contains($relative, '\\')) {
            throw new RuntimeException('Geçersiz modül güncelleme hook yolu: ' . $hookName);
        }
        $root = realpath($module['_path']);
        $file = realpath($module['_path'] . '/' . $relative);
        if ($root === false || $file === false || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) {
            throw new RuntimeException('Modül güncelleme hook dosyası bulunamadı: ' . $hookName);
        }
        $hook = require $file;
        if (!is_callable($hook)) throw new RuntimeException('Modül güncelleme hook dosyası callable döndürmeli: ' . $hookName);
        $hook($this->db, $context, $module);
    }

    private function updateHistoryRecord(int $historyId, string $status, array $migrations, bool $reversible, ?string $error = null): void
    {
        $stmt = $this->db->prepare('UPDATE core_module_update_history SET status=?,migrations_json=?,is_reversible=?,error_text=? WHERE id=?');
        $stmt->execute([$status,json_encode($migrations,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$reversible?1:0,$error,$historyId]);
    }

    public function rollbackStatus(string $id): array
    {
        $this->ensureUpdateHistory();
        $stmt = $this->db->prepare("SELECT id,from_version,to_version,status,is_reversible,error_text FROM core_module_update_history WHERE module_id=? AND status IN ('complete','partial') ORDER BY id DESC LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'available' => is_array($row) && (int)$row['is_reversible'] === 1,
            'history_id' => is_array($row) ? (int)$row['id'] : null,
            'from_version' => is_array($row) ? (string)$row['from_version'] : null,
            'to_version' => is_array($row) ? (string)$row['to_version'] : null,
            'status' => is_array($row) ? (string)$row['status'] : null,
            'error' => is_array($row) ? $row['error_text'] : null,
        ];
    }

    public function status(string $id): array
    {
        $this->ensureRepositories();
        $stmt = $this->db->prepare('SELECT version,installed_at FROM core_module_installs WHERE module_id=?');
        $stmt->execute([$id]);
        $installed = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = $this->db->prepare('SELECT enabled FROM core_modules WHERE module_id=?');
        $stmt->execute([$id]);
        return [
            'installed' => is_array($installed),
            'installed_version' => is_array($installed) ? (string)$installed['version'] : null,
            'enabled' => (int)$stmt->fetchColumn() === 1,
        ];
    }

    public function install(string $id): array
    {
        $modules = $this->discover();
        if (!isset($modules[$id])) throw new RuntimeException('Modül manifesti bulunamadı veya geçersiz.');
        $module = $modules[$id];
        $this->ensureRepositories();
        $current = $this->status($id);
        if ($current['installed']) {
            if (version_compare($module['version'], (string)$current['installed_version'], '<')) {
                throw new RuntimeException('Modül sürümü kurulu sürümden eski; kurulumla geriye düşürülemez.');
            }
            if ($module['version'] === $current['installed_version']) return [];
            return $this->update($id);
        }

        foreach ($module['dependencies'] as $dependency) {
            if (!isset($modules[$dependency])) throw new RuntimeException('Eksik modül bağımlılığı: ' . $dependency);
            $stmt = $this->db->prepare('SELECT 1 FROM core_module_installs WHERE module_id=?');
            $stmt->execute([$dependency]);
            if (!$stmt->fetchColumn()) throw new RuntimeException('Önce bağımlı modülü kurun: ' . $dependency);
        }

        $migrationDir = $module['_path'] . '/migrations';
        $migrationRoot = realpath($migrationDir);
        $files = $migrationRoot !== false ? (glob($migrationRoot . '/*.php') ?: []) : [];
        sort($files, SORT_STRING);
        $applied = [];
        foreach ($files as $file) {
            $realFile = realpath($file);
            if ($realFile === false || !str_starts_with($realFile, $migrationRoot . DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Modül migrasyon dosyası güvenli dizin dışında.');
            }
            $name = basename($realFile);
            $stmt = $this->db->prepare('SELECT 1 FROM core_module_migrations WHERE module_id=? AND migration=?');
            $stmt->execute([$id, $name]);
            if ($stmt->fetchColumn()) continue;

            $definition = require $realFile;
            $migration = is_callable($definition) ? $definition : (is_array($definition) ? ($definition['up'] ?? null) : null);
            if (!is_callable($migration)) throw new RuntimeException('Modül migrasyonu callable veya up callable döndürmeli: ' . $name);
            try {
                if (!$this->db->beginTransaction()) throw new RuntimeException('Migrasyon işlemi başlatılamadı.');
                $migration($this->db);
                $stmt = $this->db->prepare('INSERT INTO core_module_migrations (module_id,migration) VALUES (?,?)');
                $stmt->execute([$id, $name]);
                $this->db->commit();
                $applied[] = $name;
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) $this->db->rollBack();
                throw new RuntimeException('Modül migrasyonu başarısız (' . $name . '): ' . $e->getMessage(), 0, $e);
            }
        }

        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->db->prepare('INSERT INTO core_module_installs (module_id,version) VALUES (?,?) ON CONFLICT(module_id) DO UPDATE SET version=excluded.version');
            $stmt->execute([$id, $module['version']]);
            $stmt = $this->db->prepare('INSERT INTO core_modules (module_id,version,enabled) VALUES (?,?,0) ON CONFLICT(module_id) DO UPDATE SET version=excluded.version');
        } else {
            $stmt = $this->db->prepare('INSERT INTO core_module_installs (module_id,version) VALUES (?,?) ON DUPLICATE KEY UPDATE version=VALUES(version)');
            $stmt->execute([$id, $module['version']]);
            $stmt = $this->db->prepare('INSERT INTO core_modules (module_id,version,enabled) VALUES (?,?,0) ON DUPLICATE KEY UPDATE version=VALUES(version)');
        }
        $stmt->execute([$id, $module['version']]);
        return $applied;
    }

    public function enabledModules(): array
    {
        try {
            $ids = $this->db->query('SELECT module_id FROM core_modules WHERE enabled=1')->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            error_log('[CoreCMS] Module list failed: ' . $e->getMessage());
            return [];
        }
        $all = $this->discover();
        $enabled = [];
        foreach ($ids as $id) if (isset($all[$id])) $enabled[] = $all[$id];
        return $enabled;
    }

    public function enable(string $id): void
    {
        $modules = $this->discover();
        if (!isset($modules[$id])) throw new RuntimeException('Modül manifesti bulunamadı veya geçersiz.');
        $this->ensureRepositories();
        $stmt = $this->db->prepare('SELECT 1 FROM core_module_installs WHERE module_id=?');
        $stmt->execute([$id]);
        if (!$stmt->fetchColumn()) throw new RuntimeException('Önce modülü kurun.');
        foreach ($modules[$id]['dependencies'] as $dependency) {
            if (!isset($modules[$dependency])) throw new RuntimeException('Eksik modül bağımlılığı: ' . $dependency);
            $stmt = $this->db->prepare('SELECT enabled FROM core_modules WHERE module_id=?');
            $stmt->execute([$dependency]);
            if ((int)$stmt->fetchColumn() !== 1) throw new RuntimeException('Önce bağımlı modülü etkinleştirin: ' . $dependency);
        }
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? 'INSERT INTO core_modules (module_id,version,enabled) VALUES (?,?,1) ON CONFLICT(module_id) DO UPDATE SET version=excluded.version,enabled=1'
            : 'INSERT INTO core_modules (module_id,version,enabled) VALUES (?,?,1) ON DUPLICATE KEY UPDATE version=VALUES(version),enabled=1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id, $modules[$id]['version']]);
    }

    public function disable(string $id): void
    {
        $modules = $this->discover();
        if (!isset($modules[$id])) throw new RuntimeException('Modül bulunamadı.');
        foreach ($modules as $candidate) {
            if ($candidate['id'] === $id || !in_array($id, $candidate['dependencies'], true)) continue;
            $stmt = $this->db->prepare('SELECT enabled FROM core_modules WHERE module_id=?');
            $stmt->execute([$candidate['id']]);
            if ((int)$stmt->fetchColumn() === 1) {
                throw new RuntimeException('Bu modüle bağlı etkin modül var: ' . $candidate['id'] . '. Önce onu devre dışı bırakın.');
            }
        }
        $stmt = $this->db->prepare('UPDATE core_modules SET enabled=0 WHERE module_id=?');
        $stmt->execute([$id]);
    }
}
