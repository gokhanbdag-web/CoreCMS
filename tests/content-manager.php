<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core/ContentManager.php';
if(!in_array('sqlite',PDO::getAvailableDrivers(),true)) throw new RuntimeException('PDO SQLite required.');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE core_pages(id INTEGER PRIMARY KEY AUTOINCREMENT,title TEXT,slug TEXT UNIQUE,body TEXT,status TEXT,source_module TEXT,source_demo TEXT,seo_title TEXT,seo_description TEXT,canonical_url TEXT,scheduled_at TEXT,social_image_media_id INTEGER)");
$db->exec("CREATE TABLE core_media(id INTEGER PRIMARY KEY,alt_text TEXT)");
$db->exec("INSERT INTO core_media(id,alt_text) VALUES(7,'Sosyal görsel')");
$db->exec("CREATE TABLE core_menus(id INTEGER PRIMARY KEY AUTOINCREMENT,menu_key TEXT,label TEXT,url TEXT,sort_order INTEGER,source_module TEXT,source_demo TEXT)");
$cm=new CoreCMS\Core\ContentManager($db); $checks=0;
$assert=static function(bool $ok,string $why)use(&$checks):void{$checks++;if(!$ok)throw new RuntimeException('FAIL: '.$why);};
$id=$cm->savePage(null,'Hakkımızda','','Merhaba <script>alert(1)</script>','published');
$row=$db->query('SELECT * FROM core_pages WHERE id='.(int)$id)->fetch();
$assert($row['slug']==='hakkimizda','slug generated');
$cm->savePage($id,'Hakkımızda','','SEO içerik','published','Özel SEO başlığı','Açıklama','/hakkimizda');
$seo=$db->query('SELECT seo_title,seo_description,canonical_url FROM core_pages WHERE id='.(int)$id)->fetch();
$assert($seo['seo_title']==='Özel SEO başlığı' && $seo['seo_description']==='Açıklama' && $seo['canonical_url']==='/hakkimizda','SEO metadata saved');
$cm->savePage($id,'Hakkımızda','','Sosyal görsel testi','published','','','',null,7);
$assert((int)$db->query('SELECT social_image_media_id FROM core_pages WHERE id='.(int)$id)->fetchColumn()===7,'page social image saved');
$failed=false;try{$cm->savePage($id,'Hakkımızda','','İçerik','published','','','',null,999);}catch(RuntimeException){$failed=true;}
$assert($failed,'unknown social image rejected');
$failed=false;try{$cm->savePage($id,'Hakkımızda','','İçerik','scheduled');}catch(RuntimeException){$failed=true;}
$assert($failed,'scheduled publish requires future date');
$assert(!str_contains($row['body'],'<script>'),'page body escaped');
$cm->savePage($id,'Hakkımızda güncel','hakkimizda','Yeni metin','draft');
$assert($db->query('SELECT status FROM core_pages WHERE id='.(int)$id)->fetchColumn()==='draft','page update');
$cm->deletePage($id);
$assert((int)$db->query('SELECT COUNT(*) FROM core_pages')->fetchColumn()===0,'page delete');
$menu=$cm->saveMenu(null,'main','Ana sayfa','/',10);
$cm->saveMenu($menu,'main','Başlangıç','https://example.com',20);
$assert($db->query('SELECT url FROM core_menus')->fetchColumn()==='https://example.com','menu update');
$failed=false;try{$cm->saveMenu(null,'main','Kötü','javascript:alert(1)',0);}catch(RuntimeException){$failed=true;}
$assert($failed,'unsafe URL rejected');
$db->exec("INSERT INTO core_pages(title,slug,status,source_module) VALUES('Module','module-page','published','sample')");
$failed=false;try{$cm->deletePage((int)$db->query("SELECT id FROM core_pages WHERE slug='module-page'")->fetchColumn());}catch(RuntimeException){$failed=true;}
$assert($failed,'module page deletion protected');
$modulePage=(int)$db->query("SELECT id FROM core_pages WHERE slug='module-page'")->fetchColumn();
$cm->detachPage($modulePage);
$assert($db->query('SELECT source_module FROM core_pages WHERE id='.$modulePage)->fetchColumn()===null,'module page detachment');
$cm->deletePage($modulePage);
$db->exec("INSERT INTO core_menus(menu_key,label,url,sort_order,source_module) VALUES('main','Module link','/module',1,'sample')");
$moduleMenu=(int)$db->lastInsertId();
$cm->detachMenu($moduleMenu);
$assert($db->query('SELECT source_module FROM core_menus WHERE id='.$moduleMenu)->fetchColumn()===null,'module menu detachment');
$cm->deleteMenu($moduleMenu);
echo 'Content manager checks passed ('.$checks.').'.PHP_EOL;
