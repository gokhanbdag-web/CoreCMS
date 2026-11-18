<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core/ModuleManager.php';
require dirname(__DIR__).'/modules/hair-salon/Admin/HairSalonManager.php';
use CoreCMS\Core\ModuleManager;
use CoreCMS\Modules\HairSalon\HairSalonManager;
if(!in_array('sqlite',PDO::getAvailableDrivers(),true))throw new RuntimeException('PDO SQLite is required.');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('PRAGMA foreign_keys=ON');
$db->exec('CREATE TABLE core_modules(module_id TEXT PRIMARY KEY,version TEXT NOT NULL,enabled INTEGER NOT NULL DEFAULT 0)');
$db->exec('CREATE TABLE core_languages(locale_code TEXT PRIMARY KEY,name TEXT NOT NULL,is_default INTEGER NOT NULL DEFAULT 0,enabled INTEGER NOT NULL DEFAULT 1)');
$db->exec('CREATE TABLE core_translations(id INTEGER PRIMARY KEY AUTOINCREMENT,locale_code TEXT NOT NULL,translation_key TEXT NOT NULL,translation_value TEXT NOT NULL,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(locale_code,translation_key))');
$db->exec("INSERT INTO core_languages(locale_code,name) VALUES('tr','Türkçe'),('en','English')");
$modules=new ModuleManager($db,dirname(__DIR__).'/modules');$modules->install('hair-salon');$modules->enable('hair-salon');
$assertions=0;$assert=static function(bool $ok,string $message)use(&$assertions):void{$assertions++;if(!$ok)throw new RuntimeException('FAIL: '.$message);};
$assert($modules->status('hair-salon')['enabled']===true,'salon module installs and enables');
$assert((int)$db->query("SELECT COUNT(*) FROM core_translations WHERE translation_key='hs.admin.title'")->fetchColumn()===2,'Turkish and English admin labels seeded');
$m=new HairSalonManager($db);
$service=$m->saveService(['name'=>'Saç Kesimi','slug'=>'sac-kesimi','category'=>'hair','duration_minutes'=>60,'price'=>750,'currency'=>'TRY','description'=>'Uzman saç kesimi.','status'=>'active']);
$assert($service>0,'service created');
$staff=$m->saveStaff(['name'=>'Uzman Ayşe','title'=>'Kuaför','email'=>'ayse@example.test','phone'=>'5551234567','working_days'=>[0,1,2,3,4,5,6],'work_start'=>'09:00','work_end'=>'18:00','service_ids'=>[$service],'status'=>'active']);
$assert($staff>0,'staff created and service assignment stored');
$staffRows=$m->staff(true,$service);$assert(count($staffRows)===1&&in_array($service,$staffRows[0]['service_ids'],true),'staff service relation and filter');
$date=(new DateTimeImmutable('+2 days'))->format('Y-m-d');
$slots=$m->availableSlots($service,$staff,$date);$assert(in_array('10:00',$slots,true)&&count($slots)>0,'available appointment slots generated');
$appointment=$m->createAppointment(['service_id'=>$service,'staff_id'=>$staff,'date'=>$date,'time'=>'10:00','customer_name'=>'Test Müşteri','email'=>'customer@example.test','phone'=>'5550000000','message'=>'İlk randevu'],'127.0.0.1');
$assert($appointment>0,'appointment request created');
$slotsAfter=$m->availableSlots($service,$staff,$date);$assert(!in_array('10:00',$slotsAfter,true)&&!in_array('10:30',$slotsAfter,true),'overlapping appointment slots blocked');
$second=$m->createAppointment(['service_id'=>$service,'staff_id'=>$staff,'date'=>$date,'time'=>'11:00','customer_name'=>'İkinci Müşteri','email'=>'second@example.test'],'127.0.0.2');
$assert($second>0,'non-overlapping appointment accepted');
$m->updateAppointmentStatus($appointment,'cancelled');$assert(in_array('10:00',$m->availableSlots($service,$staff,$date),true),'cancellation releases appointment time');
$m->updateAppointmentStatus($second,'confirmed');$assert(!in_array('11:00',$m->availableSlots($service,$staff,$date),true),'confirmed appointment remains blocked');
$failed=false;try{$m->createAppointment(['service_id'=>$service,'staff_id'=>$staff,'date'=>$date,'time'=>'11:30','customer_name'=>'Çakışma','email'=>'overlap@example.test'],'127.0.0.3');}catch(RuntimeException){$failed=true;}
$assert($failed,'overlapping appointment submission rejected');
$failed=false;try{$m->createAppointment(['service_id'=>$service,'staff_id'=>$staff,'date'=>'2000-01-01','time'=>'10:00','customer_name'=>'Geçmiş','email'=>'past@example.test'],'127.0.0.4');}catch(RuntimeException){$failed=true;}
$assert($failed,'past date rejected');
$staff2=$m->saveStaff(['name'=>'Uzman Deniz','working_days'=>[0,1,2,3,4,5,6],'work_start'=>'09:00','work_end'=>'18:00','service_ids'=>[$service]]);
$slots2=$m->availableSlots($service,$staff2,$date);$assert(count($slots2)>5,'second staff schedule is independent');
$times=['09:00','10:00','11:00','12:00','13:00'];
foreach($times as $i=>$time)$m->createAppointment(['service_id'=>$service,'staff_id'=>$staff2,'date'=>$date,'time'=>$time,'customer_name'=>'Rate '.$i,'email'=>'rate'.$i.'@example.test'],'127.0.0.9');
$failed=false;try{$m->createAppointment(['service_id'=>$service,'staff_id'=>$staff2,'date'=>$date,'time'=>'16:00','customer_name'=>'Rate blocked','email'=>'blocked@example.test'],'127.0.0.9');}catch(RuntimeException){$failed=true;}
$assert($failed,'per-IP appointment request rate limit enforced');
$failed=false;try{$m->deleteService($service);}catch(RuntimeException){$failed=true;}
$assert($failed,'service with appointments cannot be deleted');
$failed=false;try{$m->deleteStaff($staff);}catch(RuntimeException){$failed=true;}
$assert($failed,'staff with appointments cannot be deleted');
$m->saveService(['id'=>$service,'name'=>'Saç Kesimi Plus','slug'=>'sac-kesimi','category'=>'hair','duration_minutes'=>75,'price'=>900,'currency'=>'TRY','description'=>'Güncellendi','status'=>'active']);
$assert($m->services(true)[0]['name']==='Saç Kesimi Plus','service updated');
$assert($m->stats()['appointments']>=1,'appointment dashboard counters');
$assert(count($modules->discover()['hair-salon']['routes']??[])===2,'public salon and booking routes registered');
echo 'Hair salon module checks passed ('.$assertions.').'.PHP_EOL;
