<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core/SmtpTransport.php';
use CoreCMS\Core\SmtpTransport;
$base=['smtp_host'=>'smtp.example.test','smtp_port'=>'587','smtp_security'=>'starttls','mail_from_email'=>'noreply@example.test','smtp_username'=>'user','smtp_password'=>'secret','smtp_timeout'=>'15'];
SmtpTransport::validateSettings($base);
$checks=0;
$assert=static function(bool $ok,string $name)use(&$checks):void{$checks++;if(!$ok)throw new RuntimeException('FAIL: '.$name);};
foreach([
 ['smtp_host'=>'','message'=>'empty host rejected'],
 ['smtp_host'=>"smtp.example.test\r\nMAIL FROM:<evil@example.test>",'message'=>'header injection in host rejected'],
 ['smtp_port'=>'70000','message'=>'invalid port rejected'],
 ['smtp_security'=>'unknown','message'=>'unknown TLS mode rejected'],
 ['mail_from_email'=>'bad address','message'=>'invalid sender rejected'],
 ['smtp_username'=>"user\r\n",'message'=>'credential line breaks rejected'],
] as $case){
 $candidate=$base;foreach($case as $key=>$value)if($key!=='message')$candidate[$key]=$value;
 $failed=false;try{SmtpTransport::validateSettings($candidate);}catch(RuntimeException){$failed=true;}
 $assert($failed,$case['message']);
}
$failed=false;try{SmtpTransport::validateSettings(array_merge($base,['smtp_port'=>'0']));}catch(RuntimeException){$failed=true;}
$assert($failed,'zero port rejected');
echo 'SMTP transport validation checks passed ('.$checks.').'.PHP_EOL;
