<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/ModuleManager.php';

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('PDO SQLite is required for module lifecycle tests.');
}

$root = sys_get_temp_dir() . '/corecms-modules-' . bin2hex(random_bytes(5));
mkdir($root, 0700, true);
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
};
$module = static function (string $id, array $dependencies = [], ?string $migration = null) use ($root): void {
    $dir = $root . '/' . $id;
    mkdir($dir . '/migrations', 0700, true);
    file_put_contents($dir . '/module.json', json_encode([
        'id' => $id, 'name' => ucfirst($id), 'version' => '1.0.0', 'dependencies' => $dependencies,
    ], JSON_THROW_ON_ERROR));
    if ($migration !== null) file_put_contents($dir . '/migrations/001_initial.php', $migration);
};

try {
    $module('base', [], "<?php return static function (PDO \$db): void { \$db->exec('CREATE TABLE IF NOT EXISTS base_records (id INTEGER PRIMARY KEY)'); };");
    $module('child', ['base']);
    $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $db->exec('CREATE TABLE core_modules (module_id TEXT PRIMARY KEY, version TEXT NOT NULL, enabled INTEGER NOT NULL DEFAULT 0)');
    $manager = new CoreCMS\Core\ModuleManager($db, $root);

    try { $manager->install('child'); $assert(false, 'dependency must be installed first'); }
    catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'Önce bağımlı modülü kurun'), 'dependency install guard'); }

    $applied = $manager->install('base');
    $assert($applied === ['001_initial.php'], 'first module migration applied');
    $assert($manager->status('base')['installed'] === true, 'module marked installed');
    $assert($manager->install('base') === [], 'repeated install does not rerun migration');
    $assert((int)$db->query('SELECT COUNT(*) FROM core_module_migrations')->fetchColumn() === 1, 'migration recorded once');
    $assert((int)$db->query('SELECT COUNT(*) FROM sqlite_master WHERE type="table" AND name="base_records"')->fetchColumn() === 1, 'module table created');

    $manager->enable('base');
    $manager->install('child');
    $manager->enable('child');
    try { $manager->disable('base'); $assert(false, 'enabled dependent must block disable'); }
    catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'etkin modül var'), 'dependent disable guard'); }
    $manager->disable('child');
    $manager->disable('base');
    $assert(!$manager->status('base')['enabled'], 'module disabled');
    $assert($manager->status('base')['installed'], 'disable preserves installed state and data');

    echo 'Module lifecycle checks passed (' . $checks . ').' . PHP_EOL;
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($root);
}
