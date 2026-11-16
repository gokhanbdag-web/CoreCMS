<?php
declare(strict_types=1);

namespace CoreCMS\Core;

use PDO;
use RuntimeException;

final class ThemeManager
{
    public function __construct(private PDO $db, private string $moduleDirectory) {}

    public function available(): array
    {
        $result = [];
        foreach ((new ModuleManager($this->db, $this->moduleDirectory))->enabledModules() as $module) {
            $theme = $module['theme'] ?? null;
            if (!is_array($theme) || !isset($theme['directory']) || !is_string($theme['directory'])) continue;
            $path = realpath($module['_path'] . '/' . $theme['directory']);
            $root = realpath($module['_path']);
            if ($path === false || $root === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_dir($path)) continue;
            if (!is_file($path . '/layout.php')) continue;
            $result[$module['id']] = [
                'module_id' => $module['id'],
                'name' => trim((string)($theme['name'] ?? $module['name'])),
                'path' => $path,
            ];
        }
        return $result;
    }

    public function selectedId(): string
    {
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM core_settings WHERE setting_key=?");
            $stmt->execute(['active_theme_module']);
            return (string)($stmt->fetchColumn() ?: '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function activate(string $moduleId): void
    {
        $available = $this->available();
        if ($moduleId !== '' && !isset($available[$moduleId])) {
            throw new RuntimeException('Seçilen tema etkin bir modülde bulunamadı veya geçerli bir layout.php dosyası yok.');
        }
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $sql = 'INSERT INTO core_settings (setting_key,setting_value) VALUES (?,?) ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value';
        } else {
            $sql = 'INSERT INTO core_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=CURRENT_TIMESTAMP';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['active_theme_module', $moduleId]);
    }

    public function active(): ?array
    {
        $available = $this->available();
        $selected = $this->selectedId();
        if ($selected !== '') return $available[$selected] ?? null;
        return $available ? reset($available) : null;
    }
}
