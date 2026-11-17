<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/Core/FormManager.php';
require dirname(__DIR__).'/app/Core/EmailTemplateManager.php';
if(!in_array('sqlite',PDO::getAvailableDrivers(),true))throw new RuntimeException('PDO SQLite required.');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE core_languages(locale_code TEXT PRIMARY KEY,name TEXT,is_default INTEGER,enabled INTEGER)");
$db->exec("INSERT INTO core_languages(locale_code,name,is_default,enabled) VALUES('tr','Türkçe',1,1),('en','English',0,1),('de','Deutsch',0,0)");
$migration=require dirname(__DIR__).'/database/migrations/0023_forms_and_email_templates.php';
$migration($db);
$fieldTranslationMigration=require dirname(__DIR__).'/database/migrations/0029_form_field_translations.php';
$fieldTranslationMigration($db);
$forms=new CoreCMS\Core\FormManager($db);
$emails=new CoreCMS\Core\EmailTemplateManager($db);
$checks=0;
$assert=static function(bool $condition,string $message)use(&$checks):void{$checks++;if(!$condition)throw new RuntimeException('FAIL: '.$message);};
$fields=[
 ['key'=>'name','type'=>'text','label'=>'Name','required'=>true],
 ['key'=>'email','type'=>'email','label'=>'Email address','required'=>true],
 ['key'=>'interest','type'=>'select','label'=>'Interest','required'=>true,'options'=>['Rent','Buy']],
 ['key'=>'consent','type'=>'checkbox','label'=>'I agree','required'=>true]
];
$formId=$forms->save(null,'contact_request','Contact request',$fields,'hello@example.test','Send us a message','Thank you for contacting us.',true);
$form=$forms->findByKey('contact_request');
$assert($form!==null&&count($form['fields'])===4,'form saved and fields decoded');
$forms->saveFieldTranslations($formId,'en',[
 '_form'=>['name'=>'Contact us','description'=>'Tell us how we can help.','success_message'=>'Thanks, we will reply soon.'],
 'name'=>['label'=>'Your name'],
 'interest'=>['label'=>'What are you interested in?','options'=>['Rent'=>'Lease','Buy'=>'Purchase']]
]);
$translatedForm=$forms->findByKey('contact_request',true,'en');
$assert($translatedForm['name']==='Contact us'&&$translatedForm['description']==='Tell us how we can help.'&&$translatedForm['success_message']==='Thanks, we will reply soon.','form name, description and success message translations are applied');
$assert($translatedForm['fields'][0]['label']==='Your name','field label translation is applied for requested locale');
$assert(($translatedForm['fields'][2]['option_labels']['Rent']??'')==='Lease','select option label translation is applied while value stays stable');
$assert($forms->findByKey('contact_request',true,'tr')['fields'][0]['label']==='Name','default locale keeps original field labels');
$failed=false;try{$forms->saveFieldTranslations($formId,'de',['name'=>['label'=>'Dein Name']]);}catch(RuntimeException){$failed=true;}
$assert($failed,'disabled form translation locale rejected');
$failed=false;try{$forms->saveFieldTranslations($formId,'en',['missing'=>['label'=>'Unknown']]);}catch(RuntimeException){$failed=true;}
$assert($failed,'unknown form field translation rejected');
$failed=false;try{$forms->saveFieldTranslations($formId,'en',['_form'=>['name'=>'']]);}catch(RuntimeException){$failed=true;}
$assert($failed,'empty translated form name rejected');

$submissionId=$forms->submit('contact_request','en',['name'=>'Alex','email'=>'alex@example.test','interest'=>'Rent','consent'=>'1','unexpected'=>'ignored']);
$submission=$forms->submissions($formId,10)[0]??null;
$assert($submission!==null&&(int)$submission['id']===$submissionId&&$submission['payload']['name']==='Alex','form submission stored and unknown fields ignored');
$assert(!array_key_exists('unexpected',$submission['payload']),'unknown form fields are not persisted');
$forms->updateSubmissionStatus($submissionId,'read');
$assert($forms->submissions($formId,10)[0]['status']==='read','submission status updated');
$failed=false;try{$forms->submit('contact_request','en',['name'=>'Alex','email'=>'invalid','interest'=>'Rent','consent'=>'1']);}catch(RuntimeException){$failed=true;}
$assert($failed,'invalid email submission rejected');
$failed=false;try{$forms->submit('contact_request','en',['name'=>'Alex','email'=>'alex@example.test','interest'=>'Rent']);}catch(RuntimeException){$failed=true;}
$assert($failed,'required checkbox enforced');
$failed=false;try{$forms->submit('contact_request','de',['name'=>'Alex','email'=>'alex@example.test','interest'=>'Rent','consent'=>'1']);}catch(RuntimeException){$failed=true;}
$assert($failed,'disabled locale cannot submit form');
$failed=false;try{$forms->save(null,'bad key','Bad form',$fields);}catch(RuntimeException){$failed=true;}
$assert($failed,'invalid form key rejected');

$emails->save('contact.receipt','Contact receipt','We received your message','Hello {{name}}, your message is received.');
$base=$emails->get('contact.receipt','en');
$assert($base!==null&&$base['subject']==='We received your message'&&(int)$base['translated']===0,'email template base fallback works');
$emails->saveTranslation('contact.receipt','en','We received your enquiry','Hello {{name}}, we will reply soon.');
$translated=$emails->get('contact.receipt','en');
$assert($translated!==null&&$translated['subject']==='We received your enquiry'&&(int)$translated['translated']===1,'email template translation saved');
$rendered=$emails->render('contact.receipt','en',['name'=>'<Alex>']);
$assert($rendered!==null&&$rendered['body']==='Hello &lt;Alex&gt;, we will reply soon.','email template placeholders escaped');
$failed=false;try{$emails->saveTranslation('contact.receipt','tr','Başlık','Gövde');}catch(RuntimeException){$failed=true;}
$assert($failed,'default locale email template uses base record');
$failed=false;try{$emails->saveTranslation('contact.receipt','de','Betreff','Text');}catch(RuntimeException){$failed=true;}
$assert($failed,'disabled locale email translation rejected');

echo 'Forms and email template checks passed (20).'.PHP_EOL;
