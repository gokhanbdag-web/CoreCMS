<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/Url.php';
require dirname(__DIR__) . '/app/Core/ModuleManager.php';
require dirname(__DIR__) . '/app/Core/ModuleRuntime.php';

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) throw new RuntimeException('PDO SQLite required.');
$root = sys_get_temp_dir() . '/corecms-runtime-' . bin2hex(random_bytes(5));
mkdir($root, 0700, true);
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
};
try {
    $dir = $root . '/sample';
    mkdir($dir . '/migrations', 0700, true);
    mkdir($dir . '/Frontend', 0700, true);
    mkdir($dir . '/assets/css', 0700, true);
    mkdir($dir . '/templates', 0700, true);
    file_put_contents($dir . '/module.json', json_encode([
        'id'=>'sample','name'=>'Sample Module','version'=>'1.0.0',
        'pages'=>['about'=>['title'=>'About','slug'=>'sample-about','body'=>'Original text']],
        'menus'=>['about'=>['label'=>'About us','url'=>'/sample-about','menu_key'=>'main']],
        'routes'=>[['path'=>'/sample/item/{slug}','methods'=>['GET'],'handler'=>'Frontend/item.php']],
        'assets'=>['css/module.css'],
        'templates'=>['card'=>'card.php'],
        'theme'=>['name'=>'Sample Theme','directory'=>'views/theme'],
    ], JSON_THROW_ON_ERROR));
    mkdir($dir . '/views',0700,true); mkdir($dir . '/views/theme',0700,true);
    file_put_contents($dir . '/views/theme/layout.php', '<?php echo $contentHtml;');
    file_put_contents($dir . '/assets/css/module.css', 'body{display:block;}');
    file_put_contents($dir . '/templates/card.php', "<?php echo '<strong>' . htmlspecialchars((string)\$title, ENT_QUOTES, 'UTF-8') . '</strong>'; ");
    file_put_contents($dir . '/Frontend/item.php', "<?php return static function (PDO \$db, array \$params): string { return 'item:' . \$params['slug']; };");
    $db = new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec('CREATE TABLE core_modules (module_id TEXT PRIMARY KEY, version TEXT NOT NULL, enabled INTEGER NOT NULL DEFAULT 0)');
    $db->exec('CREATE TABLE core_pages (id INTEGER PRIMARY KEY AUTOINCREMENT,title TEXT NOT NULL,slug TEXT NOT NULL UNIQUE,body TEXT,status TEXT,seo_title TEXT,seo_description TEXT,canonical_url TEXT,scheduled_at TEXT,social_image_media_id INTEGER,source_module TEXT,source_demo TEXT)');
    $db->exec('CREATE TABLE core_menus (id INTEGER PRIMARY KEY AUTOINCREMENT,menu_key TEXT,label TEXT,url TEXT,sort_order INTEGER,source_module TEXT,source_demo TEXT)');
    $db->exec('CREATE TABLE core_module_installs (module_id TEXT PRIMARY KEY,version TEXT NOT NULL,installed_at TEXT DEFAULT CURRENT_TIMESTAMP)');
    $db->exec('CREATE TABLE core_module_migrations (module_id TEXT,migration TEXT,applied_at TEXT DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(module_id,migration))');
    $db->exec("INSERT INTO core_module_installs(module_id,version) VALUES('sample','1.0.0')");
    $db->exec("INSERT INTO core_modules(module_id,version,enabled) VALUES('sample','1.0.0',1)");
    $runtime = new CoreCMS\Core\ModuleRuntime($db,$root);
    $created = $runtime->syncContent('sample');
    $assert($created === ['pages'=>1,'menus'=>1], 'initial page/menu provisioning');
    $db->exec("UPDATE core_pages SET title='Admin edited title' WHERE slug='sample-about'");
    $assert($runtime->syncContent('sample') === ['pages'=>0,'menus'=>0], 'sync is idempotent');
    $assert($db->query("SELECT title FROM core_pages WHERE slug='sample-about'")->fetchColumn()==='Admin edited title', 'admin edits are preserved');
    $assert($runtime->dispatch('/sample/item/blue-widget','GET') === true, 'route dispatched');
    $assert($runtime->theme()['name']==='Sample Theme', 'module theme discovered');
    $assetUrl = $runtime->assetUrl('sample','css/module.css');
    $assert(str_contains($assetUrl,'/modules/sample/assets/css/module.css'), 'declared module asset URL');
    $undeclaredBlocked = false;
    try { $runtime->assetUrl('sample','css/secret.css'); } catch (RuntimeException $e) { $undeclaredBlocked = true; }
    $assert($undeclaredBlocked, 'undeclared module assets are rejected');
    $traversalBlocked = false;
    try { $runtime->assetUrl('sample','../module.json'); } catch (RuntimeException $e) { $traversalBlocked = true; }
    $assert($traversalBlocked, 'module asset traversal is rejected');
    $templateHtml = $runtime->renderTemplate('sample','card',['title'=>'Runtime Card']);
    $assert($templateHtml === '<strong>Runtime Card</strong>', 'module template rendered from registered template path');
    echo 'Module runtime checks passed (' . $checks . ').' . PHP_EOL;
} finally {
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($it as $f) $f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());
    rmdir($root);
}
