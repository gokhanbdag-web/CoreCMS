<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core/ModuleManager.php';
require dirname(__DIR__).'/modules/tourism/Admin/TourismManager.php';
use CoreCMS\Core\ModuleManager;
use CoreCMS\Modules\Tourism\TourismManager;
if(!in_array('sqlite',PDO::getAvailableDrivers(),true))throw new RuntimeException('PDO SQLite is required.');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('PRAGMA foreign_keys=ON');
$db->exec('CREATE TABLE core_modules(module_id TEXT PRIMARY KEY,version TEXT NOT NULL,enabled INTEGER NOT NULL DEFAULT 0)');
$db->exec('CREATE TABLE core_languages(locale_code TEXT PRIMARY KEY,name TEXT NOT NULL,is_default INTEGER NOT NULL DEFAULT 0,enabled INTEGER NOT NULL DEFAULT 1)');
$db->exec('CREATE TABLE core_translations(id INTEGER PRIMARY KEY AUTOINCREMENT,locale_code TEXT NOT NULL,translation_key TEXT NOT NULL,translation_value TEXT NOT NULL,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(locale_code,translation_key))');
$db->exec("INSERT INTO core_languages(locale_code,name) VALUES('tr','Türkçe'),('en','English')");
$modules=new ModuleManager($db,dirname(__DIR__).'/modules');$modules->install('tourism');$modules->enable('tourism');
$assertions=0;$assert=static function(bool $ok,string $msg)use(&$assertions):void{$assertions++;if(!$ok)throw new RuntimeException('FAIL: '.$msg);};
$assert($modules->status('tourism')['enabled']===true,'tourism module installs and enables');
$assert((int)$db->query("SELECT COUNT(*) FROM core_translations WHERE translation_key='tm.admin.title'")->fetchColumn()===2,'Turkish and English admin labels seeded');
$tm=new TourismManager($db);
$destination=$tm->saveDestination(['name'=>'Dalyan','slug'=>'dalyan','description'=>'Nehir, koylar ve doğa.','status'=>'active']);
$assert($destination>0,'destination created');
$tour=$tm->saveTour(['title'=>'Dalyan Tekne Turu','slug'=>'dalyan-tekne-turu','destination_id'=>$destination,'category'=>'boat','duration_hours'=>'6','price'=>'1200','currency'=>'TRY','min_guests'=>1,'max_guests'=>20,'description'=>'Dalyan kanallarında tekne gezisi.','itinerary'=>"Kanal turu\nİztuzu Plajı",'included'=>'Tekne turu','not_included'=>'Yemek','status'=>'published','featured'=>'1']);
$assert($tour>0,'tour created');
$detail=$tm->findTour('dalyan-tekne-turu');$assert($detail!==null&&$detail['destination_name']==='Dalyan'&&count($detail['itinerary'])===2,'tour details and itinerary');
$assert(count($tm->tours(['status'=>'published','destination_id'=>$destination,'category'=>'boat']))===1,'tour filters');
$departureDate=(new DateTimeImmutable('+2 days'))->format('Y-m-d\TH:i');
$departure=$tm->saveDeparture(['tour_id'=>$tour,'departure_at'=>$departureDate,'seats_total'=>3,'status'=>'open']);
$assert($departure>0&&count($tm->departures($tour,true))===1,'departure schedule created');
$booking=$tm->createBooking(['tour_id'=>$tour,'departure_id'=>$departure,'name'=>'Test Müşteri','email'=>'customer@example.test','adults'=>2,'children'=>1,'message'=>'Bilgi rica ederim.'],'127.0.0.1');
$assert($booking>0,'booking request created');
$assert((int)$db->query('SELECT seats_reserved FROM tm_departures WHERE id='.(int)$departure)->fetchColumn()===3,'booking reserves seats');
$failed=false;try{$tm->createBooking(['tour_id'=>$tour,'departure_id'=>$departure,'name'=>'Başka Müşteri','email'=>'other@example.test','adults'=>1,'children'=>0],'127.0.0.3');}catch(RuntimeException){$failed=true;}
$assert($failed,'overbooking is prevented');
$tm->updateBookingStatus($booking,'cancelled');$assert((int)$db->query('SELECT seats_reserved FROM tm_departures WHERE id='.(int)$departure)->fetchColumn()===0,'cancellation releases capacity');
$spamBooking=$tm->createBooking(['tour_id'=>$tour,'departure_id'=>$departure,'name'=>'Spam Müşteri','email'=>'spam@example.test','adults'=>3,'children'=>0],'127.0.0.4');
$tm->updateBookingStatus($spamBooking,'spam');$assert((int)$db->query('SELECT seats_reserved FROM tm_departures WHERE id='.(int)$departure)->fetchColumn()===0,'spam status releases capacity');
$finalBooking=$tm->createBooking(['tour_id'=>$tour,'departure_id'=>$departure,'name'=>'Son Müşteri','email'=>'final@example.test','adults'=>3,'children'=>0],'127.0.0.5');
$failed=false;try{$tm->updateBookingStatus($booking,'confirmed');}catch(RuntimeException){$failed=true;}
$assert($failed,'reconfirming a cancelled booking respects remaining capacity');
$tm->updateBookingStatus($finalBooking,'cancelled');$tm->updateBookingStatus($booking,'confirmed');
$assert((int)$db->query('SELECT seats_reserved FROM tm_departures WHERE id='.(int)$departure)->fetchColumn()===3,'reconfirmation reserves seats again');
$failed=false;try{$tm->deleteDeparture($departure);}catch(RuntimeException){$failed=true;}
$assert($failed,'departure with bookings cannot be deleted');
$failed=false;try{$tm->deleteTour($tour);}catch(RuntimeException){$failed=true;}
$assert($failed,'tour with bookings cannot be deleted');
$tm->saveTour(['id'=>$tour,'title'=>'Dalyan Tekne Turu Güncel','slug'=>'dalyan-tekne-turu','destination_id'=>$destination,'category'=>'boat','duration_hours'=>6,'price'=>1250,'currency'=>'TRY','min_guests'=>1,'max_guests'=>20,'description'=>'Güncel açıklama','status'=>'published']);
$assert($tm->findTour($tour)['title']==='Dalyan Tekne Turu Güncel','tour updated');
$failed=false;try{$tm->createBooking(['tour_id'=>$tour,'departure_id'=>$departure,'name'=>'Hatalı','email'=>'invalid','adults'=>1],'127.0.0.8');}catch(RuntimeException){$failed=true;}
$assert($failed,'invalid booking details rejected');
$secondDeparture=$tm->saveDeparture(['tour_id'=>$tour,'departure_at'=>(new DateTimeImmutable('+3 days'))->format('Y-m-d\TH:i'),'seats_total'=>500,'status'=>'open']);
for($i=0;$i<5;$i++)$tm->createBooking(['tour_id'=>$tour,'departure_id'=>$secondDeparture,'name'=>'Rate '.$i,'email'=>'rate'.$i.'@example.test','adults'=>1],'127.0.0.9');
$failed=false;try{$tm->createBooking(['tour_id'=>$tour,'departure_id'=>$secondDeparture,'name'=>'Rate blocked','email'=>'blocked@example.test','adults'=>1],'127.0.0.9');}catch(RuntimeException){$failed=true;}
$assert($failed,'booking request rate limit enforced');
$assert($tm->stats()['bookings']>=1,'booking dashboard counts pending requests');
$routes=$modules->discover()['tourism']['routes']??[];$assert(count($routes)===3,'public listing detail and booking routes registered');
echo 'Tourism module checks passed ('.$assertions.').'.PHP_EOL;
