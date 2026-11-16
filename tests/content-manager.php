<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core/ContentManager.php';
if(!in_array('sqlite',PDO::getAvailableDrivers(),true)) throw new RuntimeException('PDO SQLite required.');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE core_pages(id INTEGER PRIMARY KEY AUTOINCREMENT,title TEXT,slug TEXT UNIQUE,body TEXT,status TEXT,source_module TEXT,source_demo TEXT)");
$db->exec("CREATE TABLE core_menus(id INTEGER PRIMARY KEY AUTOINCREMENT,menu_key TEXT,label TEXT,url TEXT,sort_order INTEGER,source_module TEXT,source_demo TEXT)");
$cm=new CoreCMS\Core\ContentManager($db); $checks=0;
$assert=static function(bool $ok,string $why)use(&$checks):void{$checks++;if(!$ok)throw new RuntimeException('FAIL: '.$why);};
$id=$cm->savePage(null,'Hakkımızda','','Merhaba <script>alert(1)</script>','published');
$row=$db->query('SELECT * FROM core_pages WHERE id='.(int)$id)->fetch();
$assert($row['slug']==='hakkimizda','slug generated');
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
echo 'Content manager checks passed ('.$checks.').'.PHP_EOL;
