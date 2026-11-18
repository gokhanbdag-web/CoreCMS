<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/Core/Url.php';
require dirname(__DIR__).'/app/Core/SiteSettings.php';
require dirname(__DIR__).'/app/Core/ContentManager.php';
require dirname(__DIR__).'/app/Core/LocaleManager.php';
require dirname(__DIR__).'/modules/real-estate/Frontend/RealEstateSiteShell.php';

if(!in_array('sqlite',PDO::getAvailableDrivers(),true))throw new RuntimeException('PDO SQLite is required.');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE core_settings(setting_key TEXT PRIMARY KEY,setting_value TEXT NOT NULL)');
$db->exec('CREATE TABLE core_languages(locale_code TEXT PRIMARY KEY,name TEXT,is_default INTEGER,enabled INTEGER)');
$db->exec('CREATE TABLE core_menus(id INTEGER PRIMARY KEY AUTOINCREMENT,menu_key TEXT,label TEXT,url TEXT,sort_order INTEGER)');
$db->exec("INSERT INTO core_languages(locale_code,name,is_default,enabled) VALUES('tr','Türkçe',1,1),('en','English',0,1)");
$db->exec("INSERT INTO core_settings(setting_key,setting_value) VALUES('site_name','Örnek Emlak'),('seo_org_name','Örnek Gayrimenkul'),('seo_logo_url','https://example.test/logo.png')");
$db->exec("INSERT INTO core_menus(menu_key,label,url,sort_order) VALUES('main','Portföy','/emlak',10),('main','Hakkımızda','/hakkimizda',20)");
$shell=new CoreCMS\\Modules\\RealEstate\\RealEstateSiteShell($db);
$header=$shell->header('İlan başlığı','Açıklama','/emlak');
$footer=$shell->footer();
$checks=[
 str_contains($header,'Örnek Gayrimenkul')=>'brand uses editable organization name',
 str_contains($header,'https://example.test/logo.png')=>'brand uses editable logo',
 str_contains($header,'/hakkimizda')=>'navigation uses CMS menu',
 str_contains($header,'lang="tr"')=>'shell uses default locale',
 str_contains($footer,'Örnek Gayrimenkul')=>'footer uses editable organization name',
];
foreach($checks as $ok=>$label){if(!$ok)throw new RuntimeException('FAIL: '.$label);}
echo 'Real estate site shell checks passed ('.count($checks).').'.PHP_EOL;
