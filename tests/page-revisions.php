<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core/PageRevisionManager.php';
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) throw new RuntimeException('PDO SQLite required.');
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE core_pages(id INTEGER PRIMARY KEY AUTOINCREMENT,title TEXT,slug TEXT,body TEXT,status TEXT,seo_title TEXT,seo_description TEXT,canonical_url TEXT,scheduled_at TEXT,social_image_media_id INTEGER)');
$db->exec('CREATE TABLE core_page_revisions(id INTEGER PRIMARY KEY AUTOINCREMENT,page_id INTEGER,title TEXT,slug TEXT,body TEXT,status TEXT,editor_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,seo_title TEXT,seo_description TEXT,canonical_url TEXT,scheduled_at TEXT,social_image_media_id INTEGER)');
$db->exec("INSERT INTO core_pages(title,slug,body,status,seo_title,seo_description,canonical_url,social_image_media_id) VALUES('İlk başlık','ilk','ilk içerik','draft','SEO ilk','ilk açıklama','/ilk',42)");
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
$current = $db->query('SELECT title,body,status,seo_title,seo_description,canonical_url,social_image_media_id FROM core_pages WHERE id=1')->fetch();
$assert($restored === 1 && $current['title'] === 'İlk başlık' && $current['body'] === 'ilk içerik' && $current['status'] === 'draft' && $current['seo_title'] === 'SEO ilk' && $current['seo_description'] === 'ilk açıklama' && $current['canonical_url'] === '/ilk' && (int)$current['social_image_media_id'] === 42, 'revision restored');
$assert(count($manager->history(1)) === 2, 'restore does not duplicate an already snapshotted current version');
$failed = false;
try { $manager->restore(999, 9); } catch (RuntimeException) { $failed = true; }
$assert($failed, 'unknown revision rejected');
echo 'Page revision checks passed ('.$checks.').'.PHP_EOL;