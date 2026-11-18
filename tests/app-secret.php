<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/AppSecret.php';

use CoreCMS\Core\AppSecret;

$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE core_settings (setting_key TEXT PRIMARY KEY, setting_value TEXT NULL)');

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
};

$secret = AppSecret::ensure($db);
$assert(strlen($secret) === 64 && ctype_xdigit($secret), 'a 256-bit random application secret is generated');
$assert(AppSecret::ensure($db) === $secret, 'application secret remains stable across requests');
$assert((string)$db->query("SELECT setting_value FROM core_settings WHERE setting_key='app_secret'")->fetchColumn() === $secret, 'secret is stored in core settings');

$db->prepare("UPDATE core_settings SET setting_value=? WHERE setting_key='app_secret'")->execute(['existing-legacy-secret']);
$assert(AppSecret::ensure($db) === 'existing-legacy-secret', 'existing non-empty secret is preserved during upgrade');

echo 'Application secret checks passed (' . $checks . ').' . PHP_EOL;
