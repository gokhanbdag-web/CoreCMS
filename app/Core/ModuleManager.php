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

            foreach (['routes','assets','templates'] as $manifestSection) {
                if (isset($data[$manifestSection]) && !is_array($data[$manifestSection])) { $valid = false; break; }
            }
            if (!$valid) continue;
            foreach (['assets','templates'] as $pathSection) {
                foreach (($data[$pathSection] ?? []) as $pathKey => $relativePath) {
                    if (!is_string($relativePath) || $relativePath === '' || str_starts_with($relativePath,'/')
                        || str_contains($relativePath,'..') || str_contains($relativePath,'\\')) { $valid = false; break 2; }
                    if ($pathSection === 'templates' && (!is_string($pathKey) || !preg_match('/^[a-z0-9][a-z0-9._-]{0,79}$/',$pathKey))) { $valid = false; break 2; }
                }
            }
            if (!$valid) continue;

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
        $stmt = $this->db->prepare("SELECT id,from_version,to_version,status,is_reversible,error_text FROM core_module_update_history WHERE module_id=? AND status<>'rolled_back' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'available' => is_array($row) && in_array((string)$row['status'], ['complete','partial'], true) && (int)$row['is_reversible'] === 1,
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
                if ($this->db->inTransaction()) $this->db->commit();
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

    public function update(string $id): array
    {
        $modules = $this->discover();
        if (!isset($modules[$id])) throw new RuntimeException('Modül manifesti bulunamadı veya geçersiz.');
        $module = $modules[$id];
        $this->ensureRepositories();
        $this->ensureUpdateHistory();
        $status = $this->status($id);
        if (!$status['installed']) throw new RuntimeException('Güncellenecek modül kurulu değil.');
        if ($status['enabled']) throw new RuntimeException('Güncellemeden önce modülü devre dışı bırakın.');
        $fromVersion = (string)$status['installed_version'];
        $toVersion = (string)$module['version'];
        if (version_compare($toVersion,$fromVersion,'<')) throw new RuntimeException('Modül sürümü kurulu sürümden eski olamaz.');
        if ($toVersion === $fromVersion) return [];

        foreach ($module['dependencies'] as $dependency) {
            $stmt=$this->db->prepare('SELECT 1 FROM core_module_installs WHERE module_id=?');
            $stmt->execute([$dependency]);
            if (!$stmt->fetchColumn()) throw new RuntimeException('Güncelleme için eksik bağımlılık: '.$dependency);
        }

        $stmt=$this->db->prepare("INSERT INTO core_module_update_history (module_id,from_version,to_version,status,migrations_json,is_reversible) VALUES (?,?,?,'running','[]',1)");
        $stmt->execute([$id,$fromVersion,$toVersion]);
        $historyId=(int)$this->db->lastInsertId();
        $applied=[];
        $unsafeMigration=false;

        try {
            $context=['module_id'=>$id,'from_version'=>$fromVersion,'to_version'=>$toVersion];
            $this->invokeUpdateHook($module,'before_update',$context);

            $migrationDir=$module['_path'].'/migrations';
            $migrationRoot=realpath($migrationDir);
            $files=$migrationRoot!==false?(glob($migrationRoot.'/*.php')?:[]):[];
            sort($files,SORT_STRING);
            foreach($files as $file) {
                $realFile=realpath($file);
                if($realFile===false || !str_starts_with($realFile,$migrationRoot.DIRECTORY_SEPARATOR)) {
                    throw new RuntimeException('Modül migrasyon dosyası güvenli dizin dışında.');
                }
                $name=basename($realFile);
                $check=$this->db->prepare('SELECT 1 FROM core_module_migrations WHERE module_id=? AND migration=?');
                $check->execute([$id,$name]);
                if($check->fetchColumn()) continue;

                $definition=require $realFile;
                $up=is_callable($definition)?$definition:(is_array($definition)?($definition['up']??null):null);
                $down=is_array($definition)?($definition['down']??null):null;
                if(!is_callable($up)) throw new RuntimeException('Güncelleme migrasyonu up callable döndürmeli: '.$name);
                $unsafeMigration=true;
                if(!$this->db->beginTransaction()) throw new RuntimeException('Güncelleme migrasyonu başlatılamadı: '.$name);
                $up($this->db);
                $record=$this->db->prepare('INSERT INTO core_module_migrations (module_id,migration) VALUES (?,?)');
                $record->execute([$id,$name]);
                if($this->db->inTransaction()) $this->db->commit();
                $applied[]=['name'=>$name,'reversible'=>is_callable($down)];
                $unsafeMigration=false;
                $allReversible=true;
                foreach($applied as $item) if(!$item['reversible']) $allReversible=false;
                $this->updateHistoryRecord($historyId,'running',$applied,$allReversible);
            }

            $this->invokeUpdateHook($module,'after_update',$context);
            $stmt=$this->db->prepare('UPDATE core_module_installs SET version=? WHERE module_id=?');
            $stmt->execute([$toVersion,$id]);
            $stmt=$this->db->prepare('UPDATE core_modules SET version=? WHERE module_id=?');
            $stmt->execute([$toVersion,$id]);
            $allReversible=true;
            foreach($applied as $item) if(!$item['reversible']) $allReversible=false;
            $this->updateHistoryRecord($historyId,'complete',$applied,$allReversible);
            return array_column($applied,'name');
        } catch(Throwable $e) {
            $allReversible=!$unsafeMigration;
            foreach($applied as $item) if(!$item['reversible']) $allReversible=false;
            $historyStatus=($applied || $unsafeMigration)?'partial':'failed';
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->updateHistoryRecord($historyId,$historyStatus,$applied,$allReversible,$e->getMessage());
            throw new RuntimeException('Modül güncellemesi tamamlanamadı: '.$e->getMessage(),0,$e);
        }
    }

    public function rollback(string $id): array
    {
        $modules=$this->discover();
        if(!isset($modules[$id])) throw new RuntimeException('Modül manifesti bulunamadı veya geçersiz.');
        $module=$modules[$id];
        $this->ensureRepositories();
        $this->ensureUpdateHistory();
        $status=$this->status($id);
        if(!$status['installed']) throw new RuntimeException('Geri alınacak modül kurulu değil.');
        if($status['enabled']) throw new RuntimeException('Geri almadan önce modülü devre dışı bırakın.');

        $stmt=$this->db->prepare("SELECT * FROM core_module_update_history WHERE module_id=? AND status<>'rolled_back' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$id]);
        $history=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$history || !in_array((string)$history['status'],['complete','partial'],true)) throw new RuntimeException('Geri alınabilir bir modül güncellemesi bulunamadı.');
        if((int)$history['is_reversible']!==1) throw new RuntimeException('Bu güncelleme için tüm migrasyonlarda down işlemi bulunmuyor. Otomatik geri alma güvenli değil.');

        $migrations=json_decode((string)$history['migrations_json'],true);
        if(!is_array($migrations)) throw new RuntimeException('Güncelleme migrasyon geçmişi okunamadı.');
        $definitions=[];
        foreach(array_reverse($migrations) as $item) {
            $name=(string)($item['name']??'');
            $file=realpath($module['_path'].'/migrations/'.$name);
            $root=realpath($module['_path'].'/migrations');
            if($name==='' || $root===false || $file===false || !str_starts_with($file,$root.DIRECTORY_SEPARATOR) || basename($file)!==$name) {
                throw new RuntimeException('Geri alma migrasyon dosyası bulunamadı: '.$name);
            }
            $definition=require $file;
            $down=is_array($definition)?($definition['down']??null):null;
            if(!is_callable($down)) throw new RuntimeException('Geri alma için down callable bulunamadı: '.$name);
            $definitions[]=['name'=>$name,'down'=>$down];
        }

        $context=['module_id'=>$id,'from_version'=>(string)$history['to_version'],'to_version'=>(string)$history['from_version']];
        try {
            $this->invokeUpdateHook($module,'before_rollback',$context);
            foreach($definitions as $item) {
                if(!$this->db->beginTransaction()) throw new RuntimeException('Geri alma işlemi başlatılamadı: '.$item['name']);
                $item['down']($this->db);
                $remove=$this->db->prepare('DELETE FROM core_module_migrations WHERE module_id=? AND migration=?');
                $remove->execute([$id,$item['name']]);
                if($this->db->inTransaction()) $this->db->commit();
            }
            $stmt=$this->db->prepare('UPDATE core_module_installs SET version=? WHERE module_id=?');
            $stmt->execute([(string)$history['from_version'],$id]);
            $stmt=$this->db->prepare('UPDATE core_modules SET version=? WHERE module_id=?');
            $stmt->execute([(string)$history['from_version'],$id]);
            $this->invokeUpdateHook($module,'after_rollback',$context);
            $stmt=$this->db->prepare("UPDATE core_module_update_history SET status='rolled_back',rolled_back_at=CURRENT_TIMESTAMP,error_text=NULL WHERE id=?");
            $stmt->execute([(int)$history['id']]);
            return ['from_version'=>(string)$history['to_version'],'to_version'=>(string)$history['from_version'],'migrations'=>array_column($definitions,'name')];
        } catch(Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $stmt=$this->db->prepare("UPDATE core_module_update_history SET status='rollback_failed',error_text=? WHERE id=?");
            $stmt->execute([$e->getMessage(),(int)$history['id']]);
            throw new RuntimeException('Modül geri alma işlemi tamamlanamadı; manuel veritabanı kontrolü gerekli: '.$e->getMessage(),0,$e);
        }
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
        $stmt = $this->db->prepare('SELECT version FROM core_module_installs WHERE module_id=?');
        $stmt->execute([$id]);
        $installedVersion=$stmt->fetchColumn();
        if ($installedVersion===false) throw new RuntimeException('Önce modülü kurun.');
        if ((string)$installedVersion !== (string)$modules[$id]['version']) throw new RuntimeException('Etkinleştirmeden önce modülü güncelleyin.');
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
