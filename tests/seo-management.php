<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/Core/RedirectManager.php';
require dirname(__DIR__).'/app/Core/SeoAnalyzer.php';
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) throw new RuntimeException('PDO SQLite required.');
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE core_pages(id INTEGER PRIMARY KEY,title TEXT,slug TEXT,body TEXT,status TEXT,seo_title TEXT,seo_description TEXT,canonical_url TEXT)');
$db->exec('CREATE TABLE core_page_revisions(id INTEGER PRIMARY KEY,page_id INTEGER,title TEXT,slug TEXT,body TEXT,status TEXT,editor_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$db->exec('CREATE TABLE core_page_autosaves(page_id INTEGER,user_id INTEGER,title TEXT,slug TEXT,body TEXT,PRIMARY KEY(page_id,user_id))');
$migration = require dirname(__DIR__).'/database/migrations/0007_seo_management.php';
$migration($db);
$migration($db);
foreach (['core_pages','core_page_revisions','core_page_autosaves'] as $table) {
    $columns = array_column($db->query('PRAGMA table_info('.$table.')')->fetchAll(), 'name');
    if (!in_array('social_image_media_id',$columns,true)) throw new RuntimeException('Missing social image column on '.$table);
}
if ((int)$db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='core_redirects'")->fetchColumn() !== 1) throw new RuntimeException('Redirect table missing.');

$redirects = new CoreCMS\Core\RedirectManager($db);
$id = $redirects->save(null,'/eski-yol','/yeni-yol',301);
$resolved = $redirects->resolve('/eski-yol');
if (!$resolved || $resolved['target_path'] !== '/yeni-yol' || (int)$resolved['http_code'] !== 301) throw new RuntimeException('301 redirect lookup failed.');
$redirects->save($id,'/eski-yol','/gecici-hedef',302);
if ((int)$redirects->resolve('/eski-yol')['http_code'] !== 302) throw new RuntimeException('Redirect update failed.');
$redirects->save(null,'/zincir','/son-hedef',301);
$failed = false;
try { $redirects->save(null,'/baska','/zincir',301); } catch (RuntimeException) { $failed = true; }
if (!$failed) throw new RuntimeException('Redirect chain should be rejected.');
foreach ([['//evil.test','/x'],['/x','https://evil.test'],['/loop','/loop'],['/admin','/x']] as [$source,$target]) {
    $failed = false;
    try { $redirects->save(null,$source,$target,301); } catch (RuntimeException) { $failed = true; }
    if (!$failed) throw new RuntimeException('Unsafe redirect accepted: '.$source);
}
$redirects->delete($id);
if ($redirects->resolve('/eski-yol') !== null) throw new RuntimeException('Redirect deletion failed.');

$insert = $db->prepare('INSERT INTO core_pages(id,title,slug,body,status,seo_title,seo_description,canonical_url) VALUES(?,?,?,?,?,?,?,?)');
$insert->execute([1,'Hakkımızda','hakkimizda',str_repeat('örnek içerik ',45)."\n## Ayrıntılar\n[İletişim](/iletisim)",'published','Hakkımızda sayfası için yeterince uzun SEO başlığı','Bu açıklama arama sonuçlarında sayfayı doğru biçimde anlatan ve ziyaretçiyi içeriğe davet eden kapsamlı bir meta açıklama metnidir.','/hakkimizda']);
$insert->execute([2,'İletişim','iletisim','İletişim sayfası','published','','','']);
$audit = CoreCMS\Core\SeoAnalyzer::audit($db);
if (count($audit['pages']) !== 2 || count($audit['broken_links']) !== 0) throw new RuntimeException('Internal link audit failed for valid links.');
$db->exec("UPDATE core_pages SET body='[Kırık](/olmayan-sayfa)' WHERE id=1");
$audit = CoreCMS\Core\SeoAnalyzer::audit($db);
if (count($audit['broken_links']) !== 1 || $audit['broken_links'][0]['target'] !== '/olmayan-sayfa') throw new RuntimeException('Broken internal link not detected.');
$score = CoreCMS\Core\SeoAnalyzer::analyze($audit['pages'][0]);
if (!isset($score['score'],$score['issues'],$score['word_count']) || $score['score'] < 0 || $score['score'] > 100) throw new RuntimeException('SEO score result invalid.');
echo 'SEO management checks passed (15).'.PHP_EOL;
