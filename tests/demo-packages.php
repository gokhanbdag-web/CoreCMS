<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/ModuleManager.php';
require dirname(__DIR__) . '/app/Core/DemoPackageManager.php';

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('PDO SQLite is required for demo package tests.');
}

$root = sys_get_temp_dir() . '/corecms-demos-' . bin2hex(random_bytes(5));
mkdir($root, 0700, true);
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
};

try {
    $dir = $root . '/sample';
    mkdir($dir . '/migrations', 0700, true);
    file_put_contents($dir . '/module.json', json_encode([
        'id'=>'sample','name'=>'Sample module','version'=>'1.0.0','dependencies'=>[],
        'demo_packages'=>[[
            'id'=>'starter','name'=>'Starter demo','description'=>'Starter content preview',
            'pages'=>[
                'about'=>['title'=>'About','slug'=>'demo-about','body'=>'Original about','status'=>'published','seo_title'=>'About SEO'],
                'history'=>['title'=>'History','slug'=>'demo-history','body'=>'History page','status'=>'published'],
                'default'=>['title'=>'Default','slug'=>'demo-default','body'=>'Default page','status'=>'published'],
            ],
            'menus'=>[
                'main'=>['menu_key'=>'main','label'=>'About','url'=>'/demo-about','sort_order'=>10],
                'secondary'=>['menu_key'=>'main','label'=>'Default','url'=>'/demo-default','sort_order'=>20],
            ],
        ]],
    ], JSON_THROW_ON_ERROR));

    $db = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    ]);
    $db->exec('CREATE TABLE core_modules(module_id TEXT PRIMARY KEY,version TEXT NOT NULL,enabled INTEGER NOT NULL DEFAULT 0)');
    $db->exec('CREATE TABLE core_pages(id INTEGER PRIMARY KEY AUTOINCREMENT,title TEXT NOT NULL,slug TEXT NOT NULL UNIQUE,body TEXT,status TEXT,seo_title TEXT,seo_description TEXT,canonical_url TEXT,scheduled_at TEXT,social_image_media_id INTEGER,source_module TEXT,source_demo TEXT)');
    $db->exec('CREATE TABLE core_menus(id INTEGER PRIMARY KEY AUTOINCREMENT,menu_key TEXT,label TEXT,url TEXT,sort_order INTEGER,source_module TEXT,source_demo TEXT)');
    $db->exec('CREATE TABLE core_page_revisions(id INTEGER PRIMARY KEY AUTOINCREMENT,page_id INTEGER)');
    $db->exec('CREATE TABLE core_page_autosaves(page_id INTEGER,user_id INTEGER)');
    $db->exec("INSERT INTO core_modules(module_id,version,enabled) VALUES('sample','1.0.0',1)");

    $migration = require dirname(__DIR__) . '/database/migrations/0009_demo_package_installs.php';
    $migration($db);
    $migration($db);

    $manager = new CoreCMS\Core\DemoPackageManager($db,$root);
    $preview = $manager->preview('sample','starter');
    $assert($preview['page_count']===3 && $preview['menu_count']===2,'preview reports package content counts');
    $assert($preview['installed']===false && $preview['module_enabled']===true,'preview reports installation state');

    $installed = $manager->install('sample','starter');
    $assert($installed===['already_installed'=>false,'created_pages'=>3,'created_menus'=>2],'install provisions all demo content');
    $assert($manager->install('sample','starter')['already_installed']===true,'install is idempotent');

    $db->exec("UPDATE core_pages SET title='Admin edited about',seo_title='Custom SEO title' WHERE slug='demo-about'");
    $db->exec("UPDATE core_menus SET label='Admin edited menu' WHERE url='/demo-about'");
    $db->exec("INSERT INTO core_page_revisions(page_id) SELECT id FROM core_pages WHERE slug='demo-history'");

    $removed = $manager->remove('sample','starter');
    $assert($removed===['deleted_pages'=>1,'preserved_pages'=>2,'deleted_menus'=>1,'preserved_menus'=>1],'remove deletes untouched defaults and preserves edited/history content');
    $assert((int)$db->query("SELECT COUNT(*) FROM core_pages WHERE slug='demo-about' AND source_module IS NULL AND source_demo IS NULL")->fetchColumn()===1,'edited page detached and preserved');
    $assert((int)$db->query("SELECT COUNT(*) FROM core_pages WHERE slug='demo-history' AND source_module IS NULL")->fetchColumn()===1,'revision-backed page preserved');
    $assert((int)$db->query("SELECT COUNT(*) FROM core_pages WHERE slug='demo-default'")->fetchColumn()===0,'untouched page removed');
    $assert((int)$db->query("SELECT COUNT(*) FROM core_demo_installs WHERE module_id='sample' AND demo_id='starter'")->fetchColumn()===0,'demo installation record removed');

    $reinstalled = $manager->install('sample','starter');
    $assert($reinstalled===['already_installed'=>false,'created_pages'=>1,'created_menus'=>1],'reinstall skips preserved content and recreates removed defaults');
    $reset = $manager->reset('sample','starter');
    $assert($reset===['deleted_pages'=>1,'preserved_pages'=>2,'deleted_menus'=>1,'preserved_menus'=>1,'created_pages'=>1,'created_menus'=>1],'reset safely rebuilds defaults without replacing customer content');
    $assert($db->query("SELECT title FROM core_pages WHERE slug='demo-about'")->fetchColumn()==='Admin edited about','reset preserves customer page edits');
    $assert((int)$db->query("SELECT COUNT(*) FROM core_pages WHERE slug='demo-about'")->fetchColumn()===1,'reset does not duplicate preserved pages');
    $assert((int)$db->query("SELECT COUNT(*) FROM core_menus WHERE url='/demo-about'")->fetchColumn()===1,'reset does not duplicate preserved menus');

    $db->exec("UPDATE core_modules SET enabled=0 WHERE module_id='sample'");
    $blocked = false;
    try { $manager->install('sample','starter'); } catch (RuntimeException $e) { $blocked = str_contains($e->getMessage(),'etkinleştirin'); }
    $assert($blocked,'demo install requires an enabled module');

    echo 'Demo package checks passed (' . $checks . ').' . PHP_EOL;
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) $item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());
    rmdir($root);
}
