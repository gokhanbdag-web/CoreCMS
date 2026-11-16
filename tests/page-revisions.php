<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core/PageRevisionManager.php';
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) throw new RuntimeException('PDO SQLite required.');
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE core_pages(id INTEGER PRIMARY KEY AUTOINCREMENT,title TEXT,slug TEXT,body TEXT,status TEXT)');
$db->exec('CREATE TABLE core_page_revisions(id INTEGER PRIMARY KEY AUTOINCREMENT,page_id INTEGER,title TEXT,slug TEXT,body TEXT,status TEXT,editor_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$db->exec("INSERT INTO core_pages(title,slug,body,status) VALUES('İlk başlık','ilk','ilk içerik','draft')");
$manager = new CoreCMS\Core\PageRevisionManager($db);
$checks = 0;
$assert = static function(bool $ok,string $message) use (&$checks): void { $checks++; if (!$ok) throw new RuntimeException('FAIL: '.$message); };
$manager->snapshot(1, 7);
$assert(count($manager->history(1)) === 1, 'initial snapshot stored');
$manager->snapshot(1, 7);
$assert(count($manager->history(1)) === 1, 'duplicate consecutive snapshot skipped');
$db->exec("UPDATE core_pages SET title='Yayın başlığı',body='yeni içerik',status='published' WHERE id=1");
$manager->snapshot(1, 7);
$history = $manager->history(1);
$assert(count($history) === 2, 'changed version stored');
$restored = $manager->restore((int)$history[1]['id'], 9);
$current = $db->query('SELECT title,body,status FROM core_pages WHERE id=1')->fetch();
$assert($restored === 1 && $current['title'] === 'İlk başlık' && $current['body'] === 'ilk içerik' && $current['status'] === 'draft', 'revision restored');
$assert(count($manager->history(1)) === 3, 'restore first snapshots current version');
$failed = false;
try { $manager->restore(999, 9); } catch (RuntimeException) { $failed = true; }
$assert($failed, 'unknown revision rejected');
echo 'Page revision checks passed ('.$checks.').'.PHP_EOL;