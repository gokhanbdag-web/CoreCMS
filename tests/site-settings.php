<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core/SiteSettings.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE core_settings(setting_key TEXT PRIMARY KEY,setting_value TEXT)');
$settings=new CoreCMS\Core\SiteSettings($db);
$defaults=$settings->all();
if($defaults['site_name']!=='CoreCMS'||$defaults['site_language']!=='tr')throw new RuntimeException('Default settings failed.');
$settings->save(['site_name'=>'Kod Vadisi','site_language'=>'tr','timezone'=>'Europe/Istanbul','home_eyebrow'=>'DİJİTAL ÜRÜNLER','home_title'=>'Merhaba','home_accent_title'=>'Daha iyi bir web.','home_description'=>'Düzenlenebilir ana sayfa açıklaması.']);
$values=$settings->all();
if($values['site_name']!=='Kod Vadisi'||$values['home_title']!=='Merhaba')throw new RuntimeException('Settings persistence failed.');
$failed=false;try{$settings->save(['site_name'=>'X','site_language'=>'invalid!','timezone'=>'Europe/Istanbul']);}catch(RuntimeException){$failed=true;}
if(!$failed)throw new RuntimeException('Invalid language accepted.');
$failed=false;try{$settings->save(['site_name'=>'X','site_language'=>'tr','timezone'=>'Not/AZone']);}catch(RuntimeException){$failed=true;}
if(!$failed)throw new RuntimeException('Invalid timezone accepted.');
echo 'Site settings checks passed (4).'.PHP_EOL;
