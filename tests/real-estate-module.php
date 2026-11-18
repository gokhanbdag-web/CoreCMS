<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/Core/ModuleManager.php';
require dirname(__DIR__).'/modules/real-estate/Admin/RealEstateManager.php';

use CoreCMS\Core\ModuleManager;
use CoreCMS\Modules\RealEstate\RealEstateManager;

if(!in_array('sqlite',PDO::getAvailableDrivers(),true))throw new RuntimeException('PDO SQLite is required.');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('PRAGMA foreign_keys=ON');
$db->exec('CREATE TABLE core_modules(module_id TEXT PRIMARY KEY,version TEXT NOT NULL,enabled INTEGER NOT NULL DEFAULT 0)');
$db->exec('CREATE TABLE core_languages(locale_code TEXT PRIMARY KEY,name TEXT NOT NULL,is_default INTEGER NOT NULL DEFAULT 0,enabled INTEGER NOT NULL DEFAULT 1)');
$db->exec('CREATE TABLE core_translations(id INTEGER PRIMARY KEY AUTOINCREMENT,locale_code TEXT NOT NULL,translation_key TEXT NOT NULL,translation_value TEXT NOT NULL,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(locale_code,translation_key))');
$db->exec("INSERT INTO core_languages(locale_code,name) VALUES('tr','Türkçe'),('en','English')");
$manager=new ModuleManager($db,dirname(__DIR__).'/modules');
$assertions=0;
$assert=static function(bool $condition,string $message)use(&$assertions):void{$assertions++;if(!$condition)throw new RuntimeException('FAIL: '.$message);};
$manager->install('real-estate');
$manager->enable('real-estate');
$assert($manager->status('real-estate')['enabled']===true,'module installs and enables');
$assert((int)$db->query("SELECT COUNT(*) FROM core_translations WHERE translation_key='re.admin.title'")->fetchColumn()===2,'Turkish and English admin translations seeded');
$re=new RealEstateManager($db);
$agentId=$re->saveAgent(['name'=>'Ayşe Danışman','email'=>'ayse@example.test','phone'=>'+90 555 000 00 00','title'=>'Gayrimenkul Danışmanı','status'=>'active']);
$assert($agentId>0,'agent created');
$listingId=$re->saveListing(['title'=>'Çankaya Satılık Daire','slug'=>'cankaya-satilik-daire','listing_type'=>'sale','property_type'=>'apartment','price'=>'4250000','currency'=>'TRY','city'=>'Ankara','district'=>'Çankaya','neighborhood'=>'Ayrancı','description'=>'Geniş ve aydınlık daire.','agent_id'=>$agentId,'bedrooms'=>'3','bathrooms'=>'1','area_gross'=>'145','area_net'=>'125','features'=>"Balkon\nAsansör",'status'=>'published','featured'=>'1']);
$assert($listingId>0,'listing created');
$listing=$re->findListing('cankaya-satilik-daire');
$assert($listing!==null&&$listing['agent_name']==='Ayşe Danışman','listing detail and agent relation');
$assert(count($listing['features'])===2,'listing features decoded');
$assert(count($re->listings(['status'=>'published','city'=>'Ankara','listing_type'=>'sale']))===1,'listing filters');
$re->saveListing(['id'=>$listingId,'title'=>'Çankaya Satılık Daire Güncel','slug'=>'cankaya-satilik-daire','listing_type'=>'sale','property_type'=>'apartment','price'=>'4300000','currency'=>'TRY','city'=>'Ankara','district'=>'Çankaya','description'=>'Güncellenmiş açıklama','status'=>'draft']);
$updated=$re->findListing($listingId);
$assert($updated['title']==='Çankaya Satılık Daire Güncel'&&$updated['status']==='draft','listing updated and unpublished');
$failed=false;try{$re->saveListing(['title'=>'Invalid','slug'=>'../bad','listing_type'=>'sale','property_type'=>'apartment','price'=>1,'city'=>'Ankara','district'=>'Çankaya','description'=>'x']);}catch(RuntimeException){$failed=true;}
$assert($failed,'invalid slug rejected');
$re->saveListing(['id'=>$listingId,'title'=>'Çankaya Satılık Daire','slug'=>'cankaya-satilik-daire','listing_type'=>'sale','property_type'=>'apartment','price'=>'4250000','currency'=>'TRY','city'=>'Ankara','district'=>'Çankaya','description'=>'Açıklama','status'=>'published']);
$leadId=$re->createLead(['listing_id'=>$listingId,'name'=>'Müşteri Adı','email'=>'customer@example.test','phone'=>'5551234567','message'=>'İlan hakkında bilgi almak istiyorum.'],'127.0.0.1');
$assert($leadId>0&&count($re->leads())===1,'public lead stored');
$re->updateLeadStatus($leadId,'contacted');$assert($re->leads()[0]['status']==='contacted','lead status updated');
$failed=false;try{$re->createLead(['name'=>'Bad','email'=>'not-an-email','message'=>'x']);}catch(RuntimeException){$failed=true;}
$assert($failed,'invalid lead rejected');
$re->deleteAgent($agentId);$assert($re->findListing($listingId)['agent_id']===null,'deleting agent preserves listing and nulls relation');
$re->deleteListing($listingId);$assert($re->leads()[0]['listing_id']===null,'deleting listing preserves lead and nulls relation');
$assert($re->stats()['leads']===0,'lead dashboard counters reflect workflow state');
$modules=$manager->discover();
$assert(count($modules['real-estate']['routes']??[])===3,'public listing, detail and enquiry routes declared');
echo 'Real estate module checks passed ('.$assertions.').'.PHP_EOL;
