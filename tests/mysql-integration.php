<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/Migrator.php';
require dirname(__DIR__) . '/app/Core/ModuleManager.php';
require dirname(__DIR__) . '/app/Core/FormManager.php';
require dirname(__DIR__) . '/app/Core/FormSubmissionService.php';

use CoreCMS\Core\Migrator;
use CoreCMS\Core\ModuleManager;

$host = (string)(getenv('CORECMS_DB_HOST') ?: '127.0.0.1');
$port = (int)(getenv('CORECMS_DB_PORT') ?: 3306);
$user = (string)(getenv('CORECMS_DB_USER') ?: 'root');
$password = (string)(getenv('CORECMS_DB_PASSWORD') ?: '');
$database = (string)(getenv('CORECMS_DB_NAME') ?: 'corecms_test');

$connect = static function (string $name) use ($host, $port, $user, $password): PDO {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
    return new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
};

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
};
$tableExists = static function (PDO $db, string $table): bool {
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() === 1;
};

$root = dirname(__DIR__);
$migrations = $root . '/database/migrations';
$fresh = $connect($database);
$freshMigrator = new Migrator($fresh, $migrations);
$applied = $freshMigrator->migrate();
$assert(count($applied) > 0, 'fresh MySQL installation applies core migrations');
$assert($freshMigrator->pending() === [], 'fresh installation has no pending core migrations');
$assert($freshMigrator->migrate() === [], 're-running the core migrator is idempotent');
foreach (['core_users','core_settings','core_pages','core_roles','core_translations','core_login_attempts'] as $table) {
    $assert($tableExists($fresh, $table), 'fresh installation creates ' . $table);
}

date_default_timezone_set('Europe/Istanbul');
$formManager = new CoreCMS\Core\FormManager($fresh);
$formManager->save(null, 'mysql_rate_check', 'MySQL rate check', [
    ['key'=>'name','type'=>'text','label'=>'Name','required'=>true],
    ['key'=>'email','type'=>'email','label'=>'Email','required'=>true],
], '', '', 'Received', true);
$formService = new CoreCMS\Core\FormSubmissionService($fresh, bin2hex(random_bytes(32)), 1, 600);
$formService->submit('mysql_rate_check', 'tr', ['name'=>'Test','email'=>'test@example.test'], '203.0.113.45');
$rateLimited = false;
try {
    $formService->submit('mysql_rate_check', 'tr', ['name'=>'Test','email'=>'test@example.test'], '203.0.113.45');
} catch (RuntimeException $e) {
    $rateLimited = str_contains($e->getMessage(), 'Too many submissions');
}
$assert($rateLimited, 'MySQL form rate limiting uses database time and row locking');
$assert(!$fresh->inTransaction(), 'MySQL form limiter leaves no open transaction');

$modules = new ModuleManager($fresh, $root . '/modules');
foreach (['real-estate','tourism','hair-salon'] as $moduleId) {
    $modules->install($moduleId);
    $modules->enable($moduleId);
    $status = $modules->status($moduleId);
    $assert($status['installed'] === true && $status['enabled'] === true, $moduleId . ' installs and enables on MySQL');
}
foreach (['re_listings','re_agents','re_leads','tm_tours','tm_departures','tm_bookings','hs_services','hs_staff','hs_appointments'] as $table) {
    $assert($tableExists($fresh, $table), 'sector module creates ' . $table);
}

// Simulate an existing customer database at migration 0020, then apply all later migrations.
$upgradeDatabase = $database . '_upgrade';
$server = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port), $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$server->exec('DROP DATABASE IF EXISTS `' . $upgradeDatabase . '`');
$server->exec('CREATE DATABASE `' . $upgradeDatabase . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$upgrade = $connect($upgradeDatabase);
$upgradeMigrator = new Migrator($upgrade, $migrations);
$upgradeMigrator->ensureRepository();

$files = glob($migrations . '/*.php') ?: [];
sort($files, SORT_STRING);
$record = $upgrade->prepare('INSERT INTO core_migrations (migration,batch) VALUES (?,1)');
$priorCount = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (strcmp($name, '0020_per_language_content.php') > 0) break;
    $migration = require $file;
    if (!is_callable($migration)) throw new RuntimeException('Legacy migration is not callable: ' . $name);
    $migration($upgrade);
    $record->execute([$name]);
    $priorCount++;
}
$assert($priorCount > 0, 'legacy upgrade fixture applies its prior migration set');
$remaining = $upgradeMigrator->migrate();
$assert(count($remaining) > 0, 'upgrade applies migrations newer than the prior release');
$assert($upgradeMigrator->pending() === [], 'upgrade completes with no pending core migrations');
$assert($tableExists($upgrade, 'core_login_attempts'), 'upgrade creates the login rate-limit table');

$server->exec('DROP DATABASE IF EXISTS `' . $upgradeDatabase . '`');

echo 'MySQL install and upgrade checks passed (' . $checks . ').' . PHP_EOL;
