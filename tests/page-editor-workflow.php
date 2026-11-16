<?php
declare(strict_types=1);
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) throw new RuntimeException('PDO SQLite required.');
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE core_pages (id INTEGER PRIMARY KEY, title TEXT, slug TEXT, body TEXT, status TEXT)');
$db->exec('CREATE TABLE core_page_revisions (id INTEGER PRIMARY KEY, page_id INTEGER, title TEXT, slug TEXT, body TEXT, status TEXT, editor_id INTEGER, created_at TEXT)');
$migration = require dirname(__DIR__).'/database/migrations/0005_page_editor_workflow.php';
$migration($db);
$migration($db);
$columns = array_column($db->query('PRAGMA table_info(core_pages)')->fetchAll(), 'name');
foreach (['seo_title','seo_description','canonical_url','scheduled_at'] as $column) {
    if (!in_array($column, $columns, true)) throw new RuntimeException('Missing page column: '.$column);
}
$revisionColumns = array_column($db->query('PRAGMA table_info(core_page_revisions)')->fetchAll(), 'name');
foreach (['seo_title','seo_description','canonical_url','scheduled_at','social_image_media_id'] as $column) {
    if (!in_array($column, $revisionColumns, true)) throw new RuntimeException('Missing revision column: '.$column);
}
$db->exec("INSERT INTO core_pages (id,title,slug,body,status) VALUES (1,'Test','test','body','draft')");
$db->exec("INSERT INTO core_page_autosaves (page_id,user_id,title,slug,body) VALUES (1,7,'Autosave','test','draft body')");
$db->exec("INSERT INTO core_page_autosaves (page_id,user_id,title,slug,body) VALUES (1,7,'Autosave 2','test','draft body 2') ON CONFLICT(page_id,user_id) DO UPDATE SET title=excluded.title,body=excluded.body");
$saved = $db->query('SELECT title,body FROM core_page_autosaves WHERE page_id=1 AND user_id=7')->fetch();
if ($saved['title'] !== 'Autosave 2' || $saved['body'] !== 'draft body 2') throw new RuntimeException('Autosave upsert failed.');
echo 'Page editor workflow migration checks passed (10).'.PHP_EOL;