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

            $templates = ['layout' => 'layout.php'];
            foreach (($theme['templates'] ?? []) as $key => $relative) {
                if (!is_string($key) || !preg_match('/^[a-z0-9][a-z0-9._-]{0,79}$/',$key)
                    || !is_string($relative) || $relative === '' || str_starts_with($relative,'/')
                    || str_contains($relative,'..') || str_contains($relative,'\\')) continue;
                $file = realpath($path . '/' . $relative);
                if ($file !== false && str_starts_with($file,$path . DIRECTORY_SEPARATOR)
                    && is_file($file) && strtolower(pathinfo($file,PATHINFO_EXTENSION)) === 'php') {
                    $templates[$key] = $relative;
                }
            }
            $assets = is_array($theme['assets'] ?? null) ? array_values($theme['assets']) : [];
            $result[$module['id']] = [
                'module_id' => $module['id'],
                'name' => trim((string)($theme['name'] ?? $module['name'])),
                'path' => $path,
                'templates' => $templates,
                'assets' => $assets,
            ];
        }
        return $result;
    }

    public function renderTemplate(string $moduleId, string $templateKey, array $data = []): string
    {
        $available = $this->available();
        if (!isset($available[$moduleId])) throw new RuntimeException('Tema etkin değil veya bulunamadı.');
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{0,79}$/',$templateKey)
            || !isset($available[$moduleId]['templates'][$templateKey])) {
            throw new RuntimeException('Tema şablonu tanımlanmamış.');
        }
        $root = realpath($available[$moduleId]['path']);
        $file = $root !== false ? realpath($root . '/' . $available[$moduleId]['templates'][$templateKey]) : false;
        if ($root === false || $file === false || !str_starts_with($file,$root . DIRECTORY_SEPARATOR)
            || !is_file($file) || strtolower(pathinfo($file,PATHINFO_EXTENSION)) !== 'php') {
            throw new RuntimeException('Tema şablon dosyası bulunamadı.');
        }
        $db = $this->db; $theme = $available[$moduleId]; $themeManager = $this;
        extract($data,EXTR_SKIP);
        ob_start();
        try {
            require $file;
            return (string)ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw new RuntimeException('Tema şablonu oluşturulamadı: '.$e->getMessage(),0,$e);
        }
    }

    public function assetUrl(string $moduleId, string $relativePath): string
    {
        $available = $this->available();
        if (!isset($available[$moduleId]) || !in_array($relativePath,$available[$moduleId]['assets'],true)) {
            throw new RuntimeException('Tema varlığı manifest içinde tanımlı değil.');
        }
        return (new ModuleRuntime($this->db,$this->moduleDirectory))->assetUrl($moduleId,$relativePath);
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
