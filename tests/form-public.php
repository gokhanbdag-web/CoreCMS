<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/Core/FormManager.php';
require dirname(__DIR__).'/app/Core/FormRenderer.php';
require dirname(__DIR__).'/app/Core/FormSubmissionService.php';
require dirname(__DIR__).'/app/Core/MediaLibrary.php';
require dirname(__DIR__).'/app/Core/PageBodyRenderer.php';

if(!in_array('sqlite',PDO::getAvailableDrivers(),true))throw new RuntimeException('PDO SQLite required.');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE core_languages(locale_code TEXT PRIMARY KEY,enabled INTEGER)");
$db->exec("INSERT INTO core_languages VALUES('tr',1),('en',1)");
$migration=require dirname(__DIR__).'/database/migrations/0023_forms_and_email_templates.php';$migration($db);
$rateMigration=require dirname(__DIR__).'/database/migrations/0025_form_submission_rate_limits.php';$rateMigration($db);
$forms=new CoreCMS\Core\FormManager($db);
$forms->save(null,'contact_form','Contact form',[
 ['key'=>'name','type'=>'text','label'=>'Your name','required'=>true],
 ['key'=>'email','type'=>'email','label'=>'Email address','required'=>true],
 ['key'=>'message','type'=>'textarea','label'=>'Message','required'=>true],
 ['key'=>'topic','type'=>'select','label'=>'Topic','required'=>false,'options'=>['Sales','Support']],
 ['key'=>'consent','type'=>'checkbox','label'=>'I agree','required'=>true]
]);
$_SESSION=['corecms_form_feedback'=>['contact_form'=>['success'=>true,'message'=>'Received <ok>']]];
$renderer=new CoreCMS\Core\FormRenderer($db,'en','csrf-token','/contact');
$html=$renderer->render('contact_form');
$db->exec("CREATE TABLE core_media(id INTEGER PRIMARY KEY,file_path TEXT,alt_text TEXT)");
$pageHtml=CoreCMS\Core\PageBodyRenderer::render($db,"Intro text.\n\n[[form:contact_form]]",new CoreCMS\Core\MediaLibrary($db,dirname(__DIR__),''),$renderer);
if(!str_contains($pageHtml,'<section class="corecms-form"')||!str_contains($pageHtml,'name="message"'))throw new RuntimeException('Page body renderer did not expand form shortcode.');
foreach(['name="_corecms_form"','name="_csrf"','name="_website"','type="email"','<textarea','<select','type="checkbox"','Received &lt;ok&gt;'] as $expected)if(!str_contains($html,$expected))throw new RuntimeException('Missing rendered form output: '.$expected);
if(str_contains($renderer->render('missing_form'),'form-field'))throw new RuntimeException('Disabled or missing forms must not render.');

$service=new CoreCMS\Core\FormSubmissionService($db,'test-secret',1,600);
$id=$service->submit('contact_form','en',['name'=>'Alex','email'=>'alex@example.test','message'=>'Hello','topic'=>'Sales','consent'=>'1'],'203.0.113.8');
if(!$id)throw new RuntimeException('Valid public submission was not stored.');
$honeypot=$service->submit('contact_form','en',['_website'=>'bot'],'203.0.113.9');
if($honeypot!==null)throw new RuntimeException('Honeypot submission should be silently discarded.');
$service->submit('contact_form','en',['name'=>'Alex','email'=>'alex@example.test','message'=>'Hello','consent'=>'1'],'203.0.113.10');
$blocked=false;
try{$service->submit('contact_form','en',['name'=>'Alex','email'=>'alex@example.test','message'=>'Hello','consent'=>'1'],'203.0.113.10');}catch(RuntimeException $e){$blocked=str_contains($e->getMessage(),'Too many submissions');}
if(!$blocked)throw new RuntimeException('Rate limit did not block repeated submissions.');
$hashes=$db->query('SELECT ip_hash FROM core_form_rate_limits')->fetchAll(PDO::FETCH_COLUMN);
foreach($hashes as $hash)if($hash==='203.0.113.10'||strlen($hash)!==64)throw new RuntimeException('Raw IP address must not be stored.');
echo "Public form rendering and submission checks passed.\n";
