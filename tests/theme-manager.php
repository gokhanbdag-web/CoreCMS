<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core/Url.php';
require dirname(__DIR__).'/app/Core/ModuleManager.php';
require dirname(__DIR__).'/app/Core/ModuleRuntime.php';
require dirname(__DIR__).'/app/Core/ThemeManager.php';
if(!in_array('sqlite',PDO::getAvailableDrivers(),true))throw new RuntimeException('PDO SQLite required.');
$root=sys_get_temp_dir().'/corecms-theme-'.bin2hex(random_bytes(4));mkdir($root,0700,true);
try{
 $module=$root.'/sample';mkdir($module.'/views/theme',0700,true);mkdir($module.'/assets/css',0700,true);
 file_put_contents($module.'/module.json',json_encode(['id'=>'sample','name'=>'Sample','version'=>'1.0.0','assets'=>['css/theme.css'],'theme'=>['name'=>'Sample Light','directory'=>'views/theme','templates'=>['card'=>'card.php'],'assets'=>['css/theme.css']]],JSON_THROW_ON_ERROR));
 file_put_contents($module.'/views/theme/layout.php','<?php echo $contentHtml;');
 file_put_contents($module.'/views/theme/card.php',"<?php echo '<h2>' . htmlspecialchars((string)\$heading, ENT_QUOTES, 'UTF-8') . '</h2>'; ");
 file_put_contents($module.'/assets/css/theme.css','body{color:#111;}');
 $db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $db->exec('CREATE TABLE core_modules(module_id TEXT PRIMARY KEY,version TEXT,enabled INTEGER)');
 $db->exec('CREATE TABLE core_settings(setting_key TEXT PRIMARY KEY,setting_value TEXT)');
 $db->exec("INSERT INTO core_modules(module_id,version,enabled) VALUES('sample','1.0.0',1)");
 $themes=new CoreCMS\Core\ThemeManager($db,$root);
 $available=$themes->available();
 if(!isset($available['sample'])||$available['sample']['name']!=='Sample Light')throw new RuntimeException('Enabled theme discovery failed.');
 $html=$themes->renderTemplate('sample','card',['heading'=>'Theme Card']);
 if($html!=='<h2>Theme Card</h2>')throw new RuntimeException('Theme template registry failed.');
 if(!str_contains($themes->assetUrl('sample','css/theme.css'),'/modules/sample/assets/css/theme.css'))throw new RuntimeException('Theme asset URL failed.');
 $themes->activate('sample');
 if($themes->active()['module_id']!=='sample')throw new RuntimeException('Selected theme was not activated.');
 $failed=false;try{$themes->activate('missing');}catch(RuntimeException){$failed=true;}
 if(!$failed)throw new RuntimeException('Unknown theme selection was accepted.');
 $themes->activate('');
 if($themes->active()['module_id']!=='sample')throw new RuntimeException('Automatic theme fallback failed.');
 $db->exec("UPDATE core_modules SET enabled=0 WHERE module_id='sample'");
 if($themes->active()!==null)throw new RuntimeException('Disabled module theme remained active.');
 echo 'Theme manager checks passed (5).'.PHP_EOL;
}finally{
 $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
 foreach($it as $f)$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($root);
}
