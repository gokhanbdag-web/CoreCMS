<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core/FormManager.php';
if(!in_array('sqlite',PDO::getAvailableDrivers(),true))throw new RuntimeException('PDO SQLite required.');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE core_languages(locale_code TEXT PRIMARY KEY,name TEXT,is_default INTEGER,enabled INTEGER)");
$db->exec("INSERT INTO core_languages(locale_code,name,is_default,enabled) VALUES('tr','Türkçe',1,1),('en','English',0,1)");
$migration=require dirname(__DIR__).'/database/migrations/0023_forms_and_email_templates.php';$migration($db);
$forms=new CoreCMS\Core\FormManager($db);
$fields=[['key'=>'name','type'=>'text','label'=>'Name'],['key'=>'email','type'=>'email','label'=>'Email']];
$idA=$forms->save(null,'contact_form','Contact form',$fields);
$idB=$forms->save(null,'quote_form','Quote request',$fields);
$a=$forms->submit('contact_form','tr',['name'=>'Alice Example','email'=>'alice@example.test']);
$b=$forms->submit('quote_form','en',['name'=>'Bob Example','email'=>'bob@example.test']);
$forms->updateSubmissionStatus($b,'archived');
$all=$forms->submissions(null,200);
$checks=[
 'all submissions remain visible'=>count($all)===2,
 'filter by form id'=>count($forms->submissions($idA,200))===1&&(int)$forms->submissions($idA,200)[0]['id']===$a,
 'filter by status'=>count($forms->submissions(null,200,['status'=>'archived']))===1,
 'filter by locale'=>count($forms->submissions(null,200,['locale'=>'tr']))===1,
 'search submitted payload'=>count($forms->submissions(null,200,['search'=>'Alice Example']))===1,
 'search form title and key'=>count($forms->submissions(null,200,['search'=>'Quote request']))===1,
 'combined filters'=>count($forms->submissions(null,200,['status'=>'archived','locale'=>'en','search'=>'Bob']))===1,
 'invalid status ignored safely'=>count($forms->submissions(null,200,['status'=>'unexpected']))===2,
];
foreach($checks as $label=>$ok)if(!$ok)throw new RuntimeException('FAIL: '.$label);
echo 'Form submissions inbox checks passed ('.count($checks).').'.PHP_EOL;
