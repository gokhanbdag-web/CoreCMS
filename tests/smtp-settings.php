<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core/SiteSettings.php';
use CoreCMS\Core\SiteSettings;
if(!in_array('sqlite',PDO::getAvailableDrivers(),true))throw new RuntimeException('PDO SQLite required.');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE core_settings(setting_key TEXT PRIMARY KEY,setting_value TEXT NOT NULL)');
$settings=new SiteSettings($db);
$base=[
 'site_name'=>'Example site','home_title'=>'Welcome','mail_from_name'=>'Example',
 'mail_from_email'=>'noreply@example.test','smtp_enabled'=>'1','smtp_host'=>'smtp.example.test',
 'smtp_port'=>'587','smtp_security'=>'starttls','smtp_username'=>'mailer@example.test',
 'smtp_password'=>'secret-password','smtp_timeout'=>'15'
];
$settings->save($base);
$asserted=$settings->all();
if($asserted['smtp_password']!=='secret-password'||$asserted['smtp_enabled']!=='1')throw new RuntimeException('SMTP settings were not persisted.');
$base['smtp_password']='';
$settings->save($base);
if($settings->all()['smtp_password']!=='secret-password')throw new RuntimeException('Blank password did not preserve the saved SMTP password.');
$invalid=$base;$invalid['smtp_host']="smtp.example.test\r\nMAIL FROM:<bad@example.test>";
$failed=false;try{$settings->save($invalid);}catch(RuntimeException){$failed=true;}
if(!$failed)throw new RuntimeException('SMTP host header injection was accepted.');
$invalid=$base;$invalid['smtp_security']='insecure';
$failed=false;try{$settings->save($invalid);}catch(RuntimeException){$failed=true;}
if(!$failed)throw new RuntimeException('Invalid SMTP security mode was accepted.');
echo "SMTP settings checks passed (4).\n";
