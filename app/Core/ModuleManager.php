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
                || !is_string($data['name'])
                || !is_string($data['version'])
                || !preg_match('/^\d+\.\d+\.\d+(?:[-+][a-zA-Z0-9.-]+)?$/', $data['version'])) continue;

            $data['dependencies'] = is_array($data['dependencies'] ?? null)
                ? array_values(array_filter($data['dependencies'], 'is_string'))
                : [];
            $data['_path'] = $modulePath;
            $modules[$data['id']] = $data;
        }
        ksort($modules);
        return $modules;
    }

    public function enabledModules(): array
    {
        try {
            $ids = $this->db->query('SELECT module_id FROM core_modules WHERE enabled=1')
                ->fetchAll(PDO::FETCH_COLUMN);
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
        foreach ($modules[$id]['dependencies'] as $dependency) {
            if (!isset($modules[$dependency])) throw new RuntimeException('Eksik modül bağımlılığı: ' . $dependency);
            $stmt = $this->db->prepare('SELECT enabled FROM core_modules WHERE module_id=?');
            $stmt->execute([$dependency]);
            if ((int)$stmt->fetchColumn() !== 1) {
                throw new RuntimeException('Önce bağımlı modülü etkinleştirin: ' . $dependency);
            }
        }
        $stmt = $this->db->prepare(
            'INSERT INTO core_modules (module_id,version,enabled) VALUES (?,?,1) ' .
            'ON DUPLICATE KEY UPDATE version=VALUES(version),enabled=1'
        );
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
                throw new RuntimeException(
                    'Bu modüle bağlı etkin modül var: ' . $candidate['id'] . '. Önce onu devre dışı bırakın.'
                );
            }
        }
        $stmt = $this->db->prepare('UPDATE core_modules SET enabled=0 WHERE module_id=?');
        $stmt->execute([$id]);
    }
}
