<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/Core/PageRevisionManager.php';
require dirname(__DIR__).'/app/Core/PageAutosaveManager.php';
require dirname(__DIR__).'/app/Core/SeoBulkEditor.php';
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) throw new RuntimeException('PDO SQLite required.');
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE core_pages(id INTEGER PRIMARY KEY,title TEXT,slug TEXT,body TEXT,status TEXT,seo_title TEXT,seo_description TEXT,canonical_url TEXT,scheduled_at TEXT,social_image_media_id INTEGER)');
$db->exec('CREATE TABLE core_page_revisions(id INTEGER PRIMARY KEY AUTOINCREMENT,page_id INTEGER,title TEXT,slug TEXT,body TEXT,status TEXT,editor_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,seo_title TEXT,seo_description TEXT,canonical_url TEXT,scheduled_at TEXT,social_image_media_id INTEGER)');
$db->exec('CREATE TABLE core_page_autosaves(page_id INTEGER,user_id INTEGER,title TEXT,slug TEXT,body TEXT,seo_title TEXT,seo_description TEXT,canonical_url TEXT,social_image_media_id INTEGER,saved_at TEXT,PRIMARY KEY(page_id,user_id))');
$db->exec("INSERT INTO core_pages(id,title,slug,body,status,seo_title,seo_description) VALUES
 (1,'Hakkımızda','hakkimizda','Metin','published','',''),
 (2,'İletişim','iletisim','Metin','published','Mevcut başlık','Mevcut açıklama'),
 (3,'Taslak','taslak','Metin','draft','','')");
$db->exec("INSERT INTO core_page_autosaves(page_id,user_id,title,slug,body) VALUES(1,9,'Taslak','hakkimizda','Taslak içerik')");
$editor = new CoreCMS\Core\SeoBulkEditor($db,new CoreCMS\Core\PageRevisionManager($db),new CoreCMS\Core\PageAutosaveManager($db));
$changed = $editor->apply([1,2],'{title} | {site_name}','{title} hakkında güncel bilgiler. {site_name}','fill_missing','Örnek Site',9);
if ($changed !== 1) throw new RuntimeException('Fill-missing mode changed an existing SEO record.');
$row = $db->query('SELECT seo_title,seo_description FROM core_pages WHERE id=1')->fetch();
if ($row['seo_title'] !== 'Hakkımızda | Örnek Site' || $row['seo_description'] !== 'Hakkımızda hakkında güncel bilgiler. Örnek Site') throw new RuntimeException('Template expansion failed.');
if ((int)$db->query('SELECT COUNT(*) FROM core_page_revisions WHERE page_id=1')->fetchColumn() !== 1) throw new RuntimeException('Bulk edit did not create a revision snapshot.');
if ((int)$db->query('SELECT COUNT(*) FROM core_page_autosaves WHERE page_id=1')->fetchColumn() !== 0) throw new RuntimeException('Bulk edit did not clear stale autosave.');
$changed = $editor->apply([2],'{title} - yeni','Yeni açıklama: {title}','overwrite','Örnek Site',9);
if ($changed !== 1 || $db->query('SELECT seo_title FROM core_pages WHERE id=2')->fetchColumn() !== 'İletişim - yeni') throw new RuntimeException('Overwrite mode failed.');
foreach ([[3],[],array_fill(0,101,1)] as $ids) {
    $failed = false;
    try { $editor->apply($ids,'{title}','Açıklama','fill_missing','Site',9); } catch (RuntimeException) { $failed = true; }
    if (!$failed) throw new RuntimeException('Invalid page selection was accepted.');
}
$failed = false;
try { $editor->apply([1],str_repeat('x',256),'','overwrite','Site',9); } catch (RuntimeException) { $failed = true; }
if (!$failed) throw new RuntimeException('Oversized template was accepted.');
echo 'SEO bulk editor checks passed (9).'.PHP_EOL;
