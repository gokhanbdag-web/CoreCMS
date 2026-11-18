<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Istanbul');

require dirname(__DIR__).'/app/Core/FormManager.php';
require dirname(__DIR__).'/app/Core/FormRenderer.php';
require dirname(__DIR__).'/app/Core/FormSubmissionService.php';
require dirname(__DIR__).'/app/Core/MediaLibrary.php';
require dirname(__DIR__).'/app/Core/PageBodyRenderer.php';

if(!in_array('sqlite',PDO::getAvailableDrivers(),true))throw new RuntimeException('PDO SQLite required.');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE core_languages(locale_code TEXT PRIMARY KEY,enabled INTEGER,is_default INTEGER DEFAULT 0)");
$db->exec("INSERT INTO core_languages VALUES('tr',1,1),('en',1,0)");
$migration=require dirname(__DIR__).'/database/migrations/0023_forms_and_email_templates.php';$migration($db);
$fieldTranslationMigration=require dirname(__DIR__).'/database/migrations/0029_form_field_translations.php';$fieldTranslationMigration($db);
$rateMigration=require dirname(__DIR__).'/database/migrations/0025_form_submission_rate_limits.php';$rateMigration($db);
$forms=new CoreCMS\Core\FormManager($db);
$formId=$forms->save(null,'contact_form','Contact form',[
 ['key'=>'name','type'=>'text','label'=>'Your name','required'=>true],
 ['key'=>'email','type'=>'email','label'=>'Email address','required'=>true],
 ['key'=>'message','type'=>'textarea','label'=>'Message','required'=>true],
 ['key'=>'topic','type'=>'select','label'=>'Topic','required'=>false,'options'=>['Sales','Support']],
 ['key'=>'consent','type'=>'checkbox','label'=>'I agree','required'=>true]
]);
$forms->saveFieldTranslations($formId,'en',['_form'=>['name'=>'Contact us','description'=>'Send us a note','success_message'=>'Thank you!'],'name'=>['label'=>'Your full name'],'topic'=>['label'=>'Reason','options'=>['Sales'=>'Commercial','Support'=>'Technical help']]]);
$_SESSION=['corecms_form_feedback'=>['contact_form'=>['success'=>true,'message'=>'Received <ok>']]];
$renderer=new CoreCMS\Core\FormRenderer($db,'en','csrf-token','/contact');
$html=$renderer->render('contact_form');
$db->exec("CREATE TABLE core_media(id INTEGER PRIMARY KEY,file_path TEXT,alt_text TEXT)");
$pageHtml=CoreCMS\Core\PageBodyRenderer::render($db,"Intro text.\n\n[[form:contact_form]]",new CoreCMS\Core\MediaLibrary($db,dirname(__DIR__),''),$renderer);
if(!str_contains($pageHtml,'<section class="corecms-form"')||!str_contains($pageHtml,'name="message"')||!str_contains($pageHtml,'Send us a note'))throw new RuntimeException('Page body renderer did not expand localized form shortcode and description.');
foreach(['name="_corecms_form"','name="_csrf"','name="_website"','type="email"','<textarea','<select','type="checkbox"','Received &lt;ok&gt;'] as $expected)if(!str_contains($html,$expected))throw new RuntimeException('Missing rendered form output: '.$expected);
$translatedRenderer=new CoreCMS\Core\FormRenderer($db,'en','csrf-token','/contact');
$translatedHtml=$translatedRenderer->render('contact_form');
foreach(['Send us a note','Your full name','Reason','Commercial','Technical help','value="Sales"'] as $expected)if(!str_contains($translatedHtml,$expected))throw new RuntimeException('Missing localized form label or option: '.$expected);

if(str_contains($renderer->render('missing_form'),'form-field'))throw new RuntimeException('Disabled or missing forms must not render.');

$service=new CoreCMS\Core\FormSubmissionService($db,'test-secret',1,600);
$id=$service->submit('contact_form','en',['name'=>'Alex','email'=>'alex@example.test','message'=>'Hello','topic'=>'Sales','consent'=>'1'],'203.0.113.8');
if(!$id)throw new RuntimeException('Valid public submission was not stored.');
if($db->inTransaction())throw new RuntimeException('Rate limit transaction must be committed before form processing.');
$honeypot=$service->submit('contact_form','en',['_website'=>'bot'],'203.0.113.9');
if($honeypot!==null)throw new RuntimeException('Honeypot submission should be silently discarded.');
$service->submit('contact_form','en',['name'=>'Alex','email'=>'alex@example.test','message'=>'Hello','consent'=>'1'],'203.0.113.10');
$blocked=false;
try{$service->submit('contact_form','en',['name'=>'Alex','email'=>'alex@example.test','message'=>'Hello','consent'=>'1'],'203.0.113.10');}catch(RuntimeException $e){$blocked=str_contains($e->getMessage(),'Too many submissions');}
if(!$blocked)throw new RuntimeException('Rate limit did not block repeated submissions.');
if($db->inTransaction())throw new RuntimeException('Blocked rate limit request must not leave an open transaction.');
$hashes=$db->query('SELECT ip_hash FROM core_form_rate_limits')->fetchAll(PDO::FETCH_COLUMN);
foreach($hashes as $hash)if($hash==='203.0.113.10'||strlen($hash)!==64)throw new RuntimeException('Raw IP address must not be stored.');
echo "Public form rendering and submission checks passed.\n";
