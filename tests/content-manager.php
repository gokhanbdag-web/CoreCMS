<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core/ContentManager.php';
if(!in_array('sqlite',PDO::getAvailableDrivers(),true)) throw new RuntimeException('PDO SQLite required.');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE core_pages(id INTEGER PRIMARY KEY AUTOINCREMENT,title TEXT,slug TEXT UNIQUE,body TEXT,status TEXT,source_module TEXT,source_demo TEXT,seo_title TEXT,seo_description TEXT,canonical_url TEXT,scheduled_at TEXT,social_image_media_id INTEGER)");
$db->exec("CREATE TABLE core_media(id INTEGER PRIMARY KEY,file_path TEXT,alt_text TEXT)");
$db->exec("CREATE TABLE core_languages(locale_code TEXT PRIMARY KEY,name TEXT,is_default INTEGER,enabled INTEGER)");
$db->exec("INSERT INTO core_languages(locale_code,name,is_default,enabled) VALUES('tr','Türkçe',1,1),('en','English',0,1),('de','Deutsch',0,0)");
$translationsMigration = require dirname(__DIR__) . '/database/migrations/0020_per_language_content.php';
$translationsMigration($db);
$db->exec("INSERT INTO core_media(id,alt_text) VALUES(7,'Sosyal görsel')");
$db->exec("CREATE TABLE core_menus(id INTEGER PRIMARY KEY AUTOINCREMENT,menu_key TEXT,label TEXT,url TEXT,sort_order INTEGER,source_module TEXT,source_demo TEXT)");
$cm=new CoreCMS\Core\ContentManager($db); $checks=0;
$assert=static function(bool $ok,string $why)use(&$checks):void{$checks++;if(!$ok)throw new RuntimeException('FAIL: '.$why);};
$id=$cm->savePage(null,'Hakkımızda','','Merhaba <script>alert(1)</script>','published');
$row=$db->query('SELECT * FROM core_pages WHERE id='.(int)$id)->fetch();
$assert($row['slug']==='hakkimizda','slug generated');
$cm->savePageTranslation($id,'en','About us','about-us','Welcome to our website.','About us','Learn more about us.','/about-us');
$pageTranslation=$cm->pageTranslation($id,'en');
$assert($pageTranslation && $pageTranslation['title']==='About us' && $pageTranslation['slug']==='about-us' && str_contains($pageTranslation['body'],'Welcome'), 'page translation saved');
$localizedPage=$cm->publishedPage('about-us','en');
$assert($localizedPage && $localizedPage['title']==='About us' && $localizedPage['base_slug']==='hakkimizda','published page resolves translated slug and content');
$assert($cm->pageSlugForLocale($id,'en')==='about-us' && $cm->pageSlugForLocale($id,'tr')==='hakkimizda','locale-specific page slug lookup');
$failed=false;try{$cm->savePageTranslation($id,'tr','Hakkımızda','hakkimizda','Metin');}catch(RuntimeException){$failed=true;}
$assert($failed,'default language page content cannot be stored as a translation');
$otherPage=$cm->savePage(null,'Other page','other-page','Other','published');
$failed=false;try{$cm->savePageTranslation($id,'en','About','other-page','Text');}catch(RuntimeException){$failed=true;}
$assert($failed,'translated page slug cannot collide with another base page');
$cm->deletePage($otherPage);
$cm->savePageTranslation($id,'en','About our company','about-us','Updated English body');
$assert($cm->pageTranslation($id,'en')['title']==='About our company','page translation upsert');
$assert($db->query('SELECT title FROM core_pages WHERE id='.(int)$id)->fetchColumn()==='Hakkımızda','base page remains unchanged by translation save');
$failed=false;try{$cm->savePageTranslation($id,'de','Über uns','about-de','Text');}catch(RuntimeException){$failed=true;}
$assert($failed,'disabled language cannot receive page translation');
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
$cm->saveMenuTranslation($menu,'en','Start page','/start');
$menuTranslation=$cm->menuTranslation($menu,'en');
$assert($menuTranslation && $menuTranslation['label']==='Start page' && $menuTranslation['url']==='/start','menu translation saved');
$localizedMenus=$cm->menusForLocale('main','en');
$assert(count($localizedMenus)===1 && $localizedMenus[0]['label']==='Start page' && $localizedMenus[0]['url']==='/start','public menu uses localized label and URL');
$cm->saveMenuTranslation($menu,'en','Home','/home');
$assert($cm->menuTranslation($menu,'en')['label']==='Home','menu translation upsert');
$failed=false;try{$cm->saveMenuTranslation($menu,'tr','Ana sayfa','/');}catch(RuntimeException){$failed=true;}
$assert($failed,'default language menu content cannot be stored as a translation');
$failed=false;try{$cm->saveMenuTranslation($menu,'de','Startseite','/startseite');}catch(RuntimeException){$failed=true;}
$assert($failed,'disabled language cannot receive menu translation');
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
$fallbackDb = new PDO('sqlite::memory:');
$fallbackDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$fallbackDb->exec("CREATE TABLE core_pages(id INTEGER PRIMARY KEY,title TEXT,slug TEXT,body TEXT,status TEXT,seo_title TEXT,seo_description TEXT,canonical_url TEXT,social_image_media_id INTEGER)");
$fallbackDb->exec("CREATE TABLE core_media(id INTEGER PRIMARY KEY,file_path TEXT,alt_text TEXT)");
$fallbackDb->exec("CREATE TABLE core_menus(id INTEGER PRIMARY KEY,menu_key TEXT,label TEXT,url TEXT,sort_order INTEGER)");
$fallbackDb->exec("INSERT INTO core_pages(id,title,slug,body,status) VALUES(1,'Base title','base-title','Base body','published')");
$fallbackDb->exec("INSERT INTO core_menus(id,menu_key,label,url,sort_order) VALUES(1,'main','Home','/',0)");
$fallbackContent = new ContentManager($fallbackDb);
$fallbackPage = $fallbackContent->publishedPage('base-title','en');
$assert($fallbackPage && $fallbackPage['title']==='Base title','public page falls back while translation tables are missing');
$assert($fallbackContent->menusForLocale('main','en')[0]['label']==='Home','public menu falls back while translation table is missing');
$assert($fallbackContent->pageSlugForLocale(1,'en')==='base-title','locale slug falls back while translation table is missing');
echo 'Content manager checks passed ('.$checks.').'.PHP_EOL;
