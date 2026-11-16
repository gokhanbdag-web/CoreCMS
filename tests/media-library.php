<?php
declare(strict_types=1);

use CoreCMS\Core\MediaLibrary;

require dirname(__DIR__) . '/app/Core/MediaLibrary.php';

function mediaAssert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE core_media (id INTEGER PRIMARY KEY AUTOINCREMENT,file_name TEXT NOT NULL,file_path TEXT NOT NULL,mime_type TEXT NULL,source_module TEXT NULL,source_demo TEXT NULL,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$root = sys_get_temp_dir() . '/corecms-media-' . bin2hex(random_bytes(5));
$library = new MediaLibrary($db, $root, '/cms');
$pdoMigration = require dirname(__DIR__) . '/database/migrations/0003_media_metadata.php';
$pdoMigration($db);
$columns = array_column($db->query('PRAGMA table_info(core_media)')->fetchAll(PDO::FETCH_ASSOC), 'name');
foreach (['file_size','width','height','alt_text','uploaded_by'] as $column) mediaAssert(in_array($column, $columns, true), 'Missing column: ' . $column);
$pdoMigration($db);
mediaAssert(count($library->list()) === 0, 'Empty library should return no records.');
mediaAssert($library->url('../config.php') === '', 'Traversal path must be rejected.');
mediaAssert($library->url('uploads/media/' . str_repeat('a',40) . '.jpg') === '/cms/uploads/media/' . str_repeat('a',40) . '.jpg', 'Valid media URL should be based at app path.');

$unsafe = str_repeat('a',40) . '.php';
$db->prepare('INSERT INTO core_media (file_name,file_path) VALUES (?,?)')->execute([$unsafe, 'uploads/media/' . $unsafe]);
$badId = (int)$db->lastInsertId();
try { $library->delete($badId); throw new RuntimeException('Unsafe media path should not be deleted.'); }
catch (RuntimeException $e) { mediaAssert(str_contains($e->getMessage(), 'Güvenli olmayan'), 'Unexpected unsafe-path rejection.'); }
mediaAssert((int)$db->query('SELECT COUNT(*) FROM core_media')->fetchColumn() === 1, 'Unsafe record must remain untouched.');

@rmdir($root . '/uploads/media');
@rmdir($root . '/uploads');
@rmdir($root);
echo "Media library tests passed.\n";
