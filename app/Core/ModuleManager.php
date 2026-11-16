<?php
declare(strict_types=1);

namespace CoreCMS\Core;

use PDO;
use RuntimeException;

final class ModuleManager
{
    public function __construct(private PDO $db, private string $directory) {}

    public function discover(): array
    {
        $modules = [];
        foreach (glob(rtrim($this->directory, '/') . '/*/module.json') ?: [] as $manifest) {
            $data = json_decode((string)file_get_contents($manifest), true);
            if (!is_array($data) || empty($data['id']) || empty($data['name']) || empty($data['version'])) continue;
            $data['_path'] = dirname($manifest);
            $modules[$data['id']] = $data;
        }
        ksort($modules);
        return $modules;
    }

    public function enabledModules(): array
    {
        $enabled = [];
        try {
            $ids = $this->db->query('SELECT module_id FROM core_modules WHERE enabled=1')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ids as $id) {
                $all = $this->discover();
                if (isset($all[$id])) $enabled[] = $all[$id];
            }
        } catch (\Throwable $e) {
            error_log('[CoreCMS] Module list failed: ' . $e->getMessage());
        }
        return $enabled;
    }

    public function enable(string $id): void
    {
        $modules = $this->discover();
        if (!isset($modules[$id])) throw new RuntimeException('Module manifest not found.');
        $manifest = $modules[$id];
        foreach (($manifest['dependencies'] ?? []) as $dependency) {
            $stmt = $this->db->prepare('SELECT enabled FROM core_modules WHERE module_id=?');
            $stmt->execute([$dependency]);
            if (!(bool)$stmt->fetchColumn()) throw new RuntimeException('Missing enabled dependency: ' . $dependency);
        }
        $stmt = $this->db->prepare('INSERT INTO core_modules (module_id,version,enabled) VALUES (?,?,1) ON DUPLICATE KEY UPDATE version=VALUES(version),enabled=1');
        $stmt->execute([$id, $manifest['version']]);
    }

    public function disable(string $id): void
    {
        $stmt = $this->db->prepare('UPDATE core_modules SET enabled=0 WHERE module_id=?');
        $stmt->execute([$id]);
    }
}
