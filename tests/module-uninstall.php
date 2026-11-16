<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/ModuleManager.php';
require dirname(__DIR__) . '/app/Core/ModuleRuntime.php';

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('PDO SQLite is required for module uninstall tests.');
}

$root = sys_get_temp_dir() . '/corecms-uninstall-' . bin2hex(random_bytes(5));
mkdir($root, 0700, true);
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
};

try {
    $moduleDir = $root . '/sample';
    mkdir($moduleDir . '/migrations', 0700, true);
    file_put_contents($moduleDir . '/module.json', json_encode([
        'id' => 'sample',
        'name' => 'Sample module',
        'version' => '1.0.0',
        'dependencies' => [],
        'pages' => [
            'about' => ['title' => 'About', 'slug' => 'sample-about', 'body' => 'Original about'],
            'terms' => ['title' => 'Terms', 'slug' => 'sample-terms', 'body' => 'Original terms'],
            'history' => ['title' => 'History', 'slug' => 'sample-history', 'body' => 'Revision-backed content'],
        ],
        'menus' => [
            'about' => ['label' => 'About', 'url' => '/sample-about', 'menu_key' => 'main'],
            'terms' => ['label' => 'Terms', 'url' => '/sample-terms', 'menu_key' => 'main', 'sort_order' => 2],
        ],
    ], JSON_THROW_ON_ERROR));

    $dependentDir = $root . '/dependent';
    mkdir($dependentDir . '/migrations', 0700, true);
    file_put_contents($dependentDir . '/module.json', json_encode([
        'id' => 'dependent', 'name' => 'Dependent module', 'version' => '1.0.0', 'dependencies' => ['sample'],
    ], JSON_THROW_ON_ERROR));

    $db = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db->exec('CREATE TABLE core_modules (module_id TEXT PRIMARY KEY, version TEXT NOT NULL, enabled INTEGER NOT NULL DEFAULT 0)');
    $db->exec('CREATE TABLE core_pages (id INTEGER PRIMARY KEY AUTOINCREMENT,title TEXT NOT NULL,slug TEXT NOT NULL UNIQUE,body TEXT,status TEXT,seo_title TEXT,seo_description TEXT,canonical_url TEXT,scheduled_at TEXT,social_image_media_id INTEGER,source_module TEXT,source_demo TEXT)');
    $db->exec('CREATE TABLE core_menus (id INTEGER PRIMARY KEY AUTOINCREMENT,menu_key TEXT,label TEXT,url TEXT,sort_order INTEGER,source_module TEXT,source_demo TEXT)');
    $db->exec('CREATE TABLE core_page_revisions (id INTEGER PRIMARY KEY AUTOINCREMENT,page_id INTEGER)');
    $db->exec('CREATE TABLE core_page_autosaves (page_id INTEGER,user_id INTEGER)');
    $db->exec('CREATE TABLE sample_records (id INTEGER PRIMARY KEY AUTOINCREMENT,value TEXT)');
    $db->exec("INSERT INTO sample_records(value) VALUES ('keep this data')");

    $migration = require dirname(__DIR__) . '/database/migrations/0008_module_uninstall_contract.php';
    $migration($db);
    $migration($db);
    $columns = $db->query('PRAGMA table_info(core_module_content_registry)')->fetchAll(PDO::FETCH_ASSOC);
    $assert(in_array('managed_hash', array_column($columns, 'name'), true), 'managed hash migration is idempotent');

    $db->exec("CREATE TABLE core_module_installs (module_id TEXT PRIMARY KEY,version TEXT NOT NULL,installed_at TEXT DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE core_module_migrations (module_id TEXT,migration TEXT,applied_at TEXT DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(module_id,migration))");
    $db->exec("INSERT INTO core_module_installs(module_id,version) VALUES ('sample','1.0.0')");
    $db->exec("INSERT INTO core_modules(module_id,version,enabled) VALUES ('sample','1.0.0',0)");
    $db->exec("INSERT INTO core_module_migrations(module_id,migration) VALUES ('sample','001_initial.php')");

    $runtime = new CoreCMS\Core\ModuleRuntime($db, $root);
    $created = $runtime->syncContent('sample');
    $assert($created === ['pages' => 2, 'menus' => 2], 'module content provisioned');

    $db->exec("UPDATE core_pages SET title='Admin edited about',seo_title='Changed SEO title' WHERE slug='sample-about'");
    $db->exec("UPDATE core_menus SET label='Admin edited menu' WHERE url='/sample-about'");
    $db->exec("INSERT INTO core_page_revisions(page_id) SELECT id FROM core_pages WHERE slug='sample-history'");

    $db->exec("INSERT INTO core_module_installs(module_id,version) VALUES ('dependent','1.0.0')");
    $db->exec("INSERT INTO core_modules(module_id,version,enabled) VALUES ('dependent','1.0.0',0)");
    $blockedDependency = false;
    try { $runtime->uninstall('sample'); } catch (RuntimeException $e) {
        $blockedDependency = str_contains($e->getMessage(), 'bağlı kurulu modül');
    }
    $assert($blockedDependency, 'installed dependent module blocks uninstall');
    $db->exec("DELETE FROM core_module_installs WHERE module_id='dependent'");
    $db->exec("DELETE FROM core_modules WHERE module_id='dependent'");

    $db->exec("UPDATE core_modules SET enabled=1 WHERE module_id='sample'");
    $blockedEnabled = false;
    try { $runtime->uninstall('sample'); } catch (RuntimeException $e) {
        $blockedEnabled = str_contains($e->getMessage(), 'devre dışı');
    }
    $assert($blockedEnabled, 'enabled module cannot be uninstalled');
    $db->exec("UPDATE core_modules SET enabled=0 WHERE module_id='sample'");

    $result = $runtime->uninstall('sample');
    $assert($result === ['deleted_pages' => 1, 'preserved_pages' => 2, 'deleted_menus' => 1, 'preserved_menus' => 1], 'only untouched provisioned content removed');
    $assert((int)$db->query("SELECT COUNT(*) FROM core_pages WHERE slug='sample-about' AND source_module IS NULL")->fetchColumn() === 1, 'edited page preserved and detached');
    $assert((int)$db->query("SELECT COUNT(*) FROM core_menus WHERE url='/sample-about' AND source_module IS NULL")->fetchColumn() === 1, 'edited menu preserved and detached');
    $assert((int)$db->query("SELECT COUNT(*) FROM core_pages WHERE slug='sample-terms'")->fetchColumn() === 0, 'untouched page removed');
    $assert((int)$db->query("SELECT COUNT(*) FROM core_pages WHERE slug='sample-history' AND source_module IS NULL")->fetchColumn() === 1, 'page with revision history preserved and detached');
    $assert((int)$db->query("SELECT COUNT(*) FROM core_menus WHERE url='/sample-terms'")->fetchColumn() === 0, 'untouched menu removed');
    $assert((int)$db->query('SELECT COUNT(*) FROM core_module_installs WHERE module_id="sample"')->fetchColumn() === 0, 'module install registration removed');
    $assert((int)$db->query('SELECT COUNT(*) FROM core_module_migrations WHERE module_id="sample"')->fetchColumn() === 1, 'migration ledger retained to prevent destructive reruns');
    $assert((int)$db->query('SELECT COUNT(*) FROM sample_records')->fetchColumn() === 1, 'module-owned table data preserved');

    $manager = new CoreCMS\Core\ModuleManager($db, $root);
    $manager->install('sample');
    $again = $runtime->syncContent('sample');
    $assert($again === ['pages' => 1, 'menus' => 1], 'reinstall skips preserved content and recreates removed defaults');
    $assert((int)$db->query("SELECT COUNT(*) FROM core_pages WHERE slug='sample-about'")->fetchColumn() === 1, 'reinstall does not duplicate preserved page');
    $assert((int)$db->query("SELECT COUNT(*) FROM core_menus WHERE url='/sample-about'")->fetchColumn() === 1, 'reinstall does not duplicate preserved menu');

    echo 'Module uninstall checks passed (' . $checks . ').' . PHP_EOL;
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($root);
}
