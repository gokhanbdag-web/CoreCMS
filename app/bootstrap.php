<?php
declare(strict_types=1);

require_once __DIR__ . '/Core/Url.php';
define('CORECMS_ROOT', dirname(__DIR__));
$configFile = CORECMS_ROOT . '/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    exit('Kurulum gerekli. <a href="' . htmlspecialchars(CoreCMS\Core\Url::to('/installer/'), ENT_QUOTES, 'UTF-8') . '">Kurulumu başlat</a>.');
}
$config = require $configFile;
require_once CORECMS_ROOT . '/app/Core/Database.php';
require_once CORECMS_ROOT . '/app/Core/Migrator.php';
require_once CORECMS_ROOT . '/app/Core/ModuleManager.php';
require_once CORECMS_ROOT . '/app/Core/ModuleRuntime.php';
require_once CORECMS_ROOT . '/app/Core/Csrf.php';
require_once CORECMS_ROOT . '/app/Core/Auth.php';
require_once CORECMS_ROOT . '/app/Core/RoleManager.php';
require_once CORECMS_ROOT . '/app/Core/DemoPackageManager.php';

$db = CoreCMS\Core\Database::connect($config['database']);
$settings = [];
try {
    $stmt = $db->query('SELECT setting_key, setting_value FROM core_settings');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $settings[$row['setting_key']] = $row['setting_value'];
} catch (Throwable $e) {
    error_log('[CoreCMS] Settings load failed: ' . $e->getMessage());
}
if (!empty($settings['timezone']) && in_array($settings['timezone'], DateTimeZone::listIdentifiers(), true)) {
    date_default_timezone_set($settings['timezone']);
}
