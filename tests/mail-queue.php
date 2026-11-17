<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/Core/MailQueue.php';
require dirname(__DIR__).'/app/Core/EmailTemplateManager.php';
require dirname(__DIR__).'/app/Core/FormNotificationService.php';
if(!in_array('sqlite',PDO::getAvailableDrivers(),true))throw new RuntimeException('PDO SQLite required.');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE core_forms(id INTEGER PRIMARY KEY)");
$queueMigration=require dirname(__DIR__).'/database/migrations/0027_mail_queue.php';
$queueMigration($db);
$db->exec("CREATE TABLE core_email_templates(template_key TEXT PRIMARY KEY,name TEXT,subject TEXT,body TEXT,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)");
$db->exec("CREATE TABLE core_email_template_translations(template_key TEXT,locale_code TEXT,subject TEXT,body TEXT,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(template_key,locale_code))");
$sent=[];
$queue=new CoreCMS\Core\MailQueue($db,static function($to,$subject,$body,$headers)use(&$sent){$sent[]=compact('to','subject','body','headers');return true;});
$templates=new CoreCMS\Core\EmailTemplateManager($db);
$service=new CoreCMS\Core\FormNotificationService($db,$queue,$templates);
$form=['name'=>'Contact form','recipient_email'=>'owner@example.test','fields'=>[['key'=>'name','label'=>'Name','type'=>'text'],['key'=>'message','label'=>'Message','type'=>'textarea']]];
$id=$service->queueSubmission($form,42,['name'=>'<Alex>','message'=>'Hello'], 'en','Example site');
if(!$id)throw new RuntimeException('Notification was not queued.');
$result=$queue->process(['mail_from_email'=>'noreply@example.test'],5);
if($result['sent']!==1||count($sent)!==1)throw new RuntimeException('Queued notification was not sent.');
if(!str_contains($sent[0]['body'],'&lt;Alex&gt;')||!str_contains($sent[0]['headers'],'Content-Type: text/html'))throw new RuntimeException('Notification content or headers were not safely prepared.');
if(($queue->recent(1)[0]['status']??'')!=='sent')throw new RuntimeException('Successful mail was not marked sent.');

$failedQueue=new CoreCMS\Core\MailQueue($db,static fn()=>false);
$failedId=$failedQueue->enqueue('owner@example.test','Retry me','<p>Retry</p>');
$out=$failedQueue->process(['mail_from_email'=>'noreply@example.test'],5);
$row=$db->query('SELECT status,attempts,last_error FROM core_mail_queue WHERE id='.(int)$failedId)->fetch(PDO::FETCH_ASSOC);
if($out['failed']!==1||$row['status']!=='pending'||(int)$row['attempts']!==1||!$row['last_error'])throw new RuntimeException('Failed delivery was not scheduled for retry.');
$failedQueue->retry($failedId);
$row=$db->query('SELECT status,attempts FROM core_mail_queue WHERE id='.(int)$failedId)->fetch(PDO::FETCH_ASSOC);
if($row['status']!=='pending'||(int)$row['attempts']!==0)throw new RuntimeException('Manual retry did not reset queue state.');
$skipped=$queue->process([],5);
if(!$skipped['skipped'])throw new RuntimeException('Missing sender address must not attempt delivery.');
echo "Mail queue and notification checks passed.\n";
