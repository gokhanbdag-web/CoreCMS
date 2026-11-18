<?php
declare(strict_types=1);
namespace CoreCMS\Modules\HairSalon;
use PDO;
use RuntimeException;
use Throwable;
final class HairSalonManager
{
 public function __construct(private PDO $db){}
 private function lines(mixed $v,int $max=30):array{return array_slice(array_values(array_filter(array_map('trim',preg_split('/\r\n|\r|\n/',(string)$v)?:[]))),0,$max);}
 public function services(bool $activeOnly=false):array{return $this->db->query('SELECT * FROM hs_services'.($activeOnly?" WHERE status='active'":'').' ORDER BY category,name')->fetchAll(PDO::FETCH_ASSOC);}
 public function saveService(array $d):int{
  $id=(int)($d['id']??0);$name=trim((string)($d['name']??''));$slug=strtolower(trim((string)($d['slug']??'')));$duration=filter_var($d['duration_minutes']??30,FILTER_VALIDATE_INT);$price=filter_var($d['price']??null,FILTER_VALIDATE_FLOAT);
  if($name===''||mb_strlen($name)>160||!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug))throw new RuntimeException('Hizmet adı ve SEO bağlantısı geçerli olmalıdır.');
  if($duration===false||$duration<10||$duration>600||$price===false||$price<0)throw new RuntimeException('Hizmet süresi veya fiyatı geçersiz.');
  $currency=strtoupper((string)($d['currency']??'TRY'));if(!preg_match('/^[A-Z]{3}$/',$currency))throw new RuntimeException('Para birimi geçersiz.');
  $image=trim((string)($d['image_url']??''));if($image!==''&&!preg_match('#^(https://|/)(?!/)[^\r\n]*$#i',$image))throw new RuntimeException('Görsel HTTPS veya site içi yol olmalıdır.');
  $values=[$name,$slug,trim((string)($d['category']??'hair')),$duration,$price,$currency,trim((string)($d['description']??'')),$image?:null,($d['status']??'active')==='inactive'?'inactive':'active'];
  if($id>0){$q=$this->db->prepare('SELECT 1 FROM hs_services WHERE id=?');$q->execute([$id]);if(!$q->fetchColumn())throw new RuntimeException('Hizmet bulunamadı.');$values[]=$id;$s=$this->db->prepare('UPDATE hs_services SET name=?,slug=?,category=?,duration_minutes=?,price=?,currency=?,description=?,image_url=?,status=? WHERE id=?');}
  else{$s=$this->db->prepare('INSERT INTO hs_services(name,slug,category,duration_minutes,price,currency,description,image_url,status) VALUES(?,?,?,?,?,?,?,?,?)');}
  $s->execute($values);return $id?: (int)$this->db->lastInsertId();
 }
 public function deleteService(int $id):void{$q=$this->db->prepare('SELECT COUNT(*) FROM hs_appointments WHERE service_id=?');$q->execute([$id]);if((int)$q->fetchColumn()>0)throw new RuntimeException('Randevusu bulunan hizmet silinemez; pasife alın.');$s=$this->db->prepare('DELETE FROM hs_services WHERE id=?');$s->execute([$id]);if(!$s->rowCount())throw new RuntimeException('Hizmet bulunamadı.');}
 public function staff(bool $activeOnly=false,int $serviceId=0):array{
  $where=[];$args=[];if($activeOnly)$where[]="s.status='active'";if($serviceId>0){$where[]='EXISTS(SELECT 1 FROM hs_staff_services ss WHERE ss.staff_id=s.id AND ss.service_id=?)';$args[]=$serviceId;}
  $s=$this->db->prepare('SELECT s.* FROM hs_staff s'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY s.status,s.name');$s->execute($args);$rows=$s->fetchAll(PDO::FETCH_ASSOC);
  foreach($rows as &$row){$q=$this->db->prepare('SELECT service_id FROM hs_staff_services WHERE staff_id=?');$q->execute([$row['id']]);$row['service_ids']=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));$row['working_days']=array_map('intval',json_decode((string)$row['working_days_json'],true)?:[]);}unset($row);return $rows;
 }
 public function saveStaff(array $d):int{
  $id=(int)($d['id']??0);$name=trim((string)($d['name']??''));$email=trim((string)($d['email']??''));$phone=trim((string)($d['phone']??''));$photo=trim((string)($d['photo_url']??''));
  if($name===''||mb_strlen($name)>160||($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL)))throw new RuntimeException('Ekip üyesi adı veya e-posta adresi geçersiz.');
  if($photo!==''&&!preg_match('#^(https://|/)(?!/)[^\r\n]*$#i',$photo))throw new RuntimeException('Fotoğraf HTTPS veya site içi yol olmalıdır.');
  $days=array_values(array_unique(array_filter(array_map('intval',(array)($d['working_days']??[])),static fn($v)=>$v>=0&&$v<=6)));sort($days);if(!$days)throw new RuntimeException('En az bir çalışma günü seçilmelidir.');
  $start=(string)($d['work_start']??'09:00');$end=(string)($d['work_end']??'18:00');if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$start)||!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$end)||$start>=$end)throw new RuntimeException('Çalışma saatleri geçersiz.');
  $serviceIds=array_values(array_unique(array_filter(array_map('intval',(array)($d['service_ids']??[])),static fn($v)=>$v>0)));
  foreach($serviceIds as $sid){$q=$this->db->prepare("SELECT 1 FROM hs_services WHERE id=? AND status='active'");$q->execute([$sid]);if(!$q->fetchColumn())throw new RuntimeException('Seçilen hizmetlerden biri aktif değil.');}
  if(!$serviceIds)throw new RuntimeException('Ekip üyesine en az bir hizmet atanmalıdır.');
  if(!$this->db->beginTransaction())throw new RuntimeException('Ekip kaydı başlatılamadı.');
  try{
   $values=[$name,trim((string)($d['title']??'')),trim((string)($d['bio']??'')),$email?:null,$phone?:null,$photo?:null,json_encode($days,JSON_THROW_ON_ERROR),$start,$end,($d['status']??'active')==='inactive'?'inactive':'active'];
   if($id>0){$q=$this->db->prepare('SELECT 1 FROM hs_staff WHERE id=?');$q->execute([$id]);if(!$q->fetchColumn())throw new RuntimeException('Ekip üyesi bulunamadı.');$values[]=$id;$s=$this->db->prepare('UPDATE hs_staff SET name=?,title=?,bio=?,email=?,phone=?,photo_url=?,working_days_json=?,work_start=?,work_end=?,status=? WHERE id=?');}
   else{$s=$this->db->prepare('INSERT INTO hs_staff(name,title,bio,email,phone,photo_url,working_days_json,work_start,work_end,status) VALUES(?,?,?,?,?,?,?,?,?,?)');}
   $s->execute($values);$staffId=$id?: (int)$this->db->lastInsertId();
   $this->db->prepare('DELETE FROM hs_staff_services WHERE staff_id=?')->execute([$staffId]);
   $insert=$this->db->prepare('INSERT INTO hs_staff_services(staff_id,service_id) VALUES(?,?)');foreach($serviceIds as $sid)$insert->execute([$staffId,$sid]);
   $this->db->commit();return $staffId;
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
 public function deleteStaff(int $id):void{$q=$this->db->prepare('SELECT COUNT(*) FROM hs_appointments WHERE staff_id=?');$q->execute([$id]);if((int)$q->fetchColumn()>0)throw new RuntimeException('Randevu kaydı bulunan ekip üyesi silinemez; pasife alın.');$s=$this->db->prepare('DELETE FROM hs_staff WHERE id=?');$s->execute([$id]);if(!$s->rowCount())throw new RuntimeException('Ekip üyesi bulunamadı.');}
 public function availableSlots(int $serviceId,int $staffId,string $date):array{
  $day=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$day||$day->format('Y-m-d')!==$date||$day<new \DateTimeImmutable('today'))return [];
  $q=$this->db->prepare("SELECT v.duration_minutes,v.status service_status,s.work_start,s.work_end,s.working_days_json,s.status staff_status FROM hs_services v INNER JOIN hs_staff_services ss ON ss.service_id=v.id INNER JOIN hs_staff s ON s.id=ss.staff_id WHERE v.id=? AND s.id=?");
  $q->execute([$serviceId,$staffId]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r||$r['service_status']!=='active'||$r['staff_status']!=='active')return [];
  $days=json_decode((string)$r['working_days_json'],true)?:[];if(!in_array((int)$day->format('w'),array_map('intval',$days),true))return [];
  $duration=(int)$r['duration_minutes'];$start=\DateTimeImmutable::createFromFormat('!Y-m-d H:i',$date.' '.$r['work_start']);$end=\DateTimeImmutable::createFromFormat('!Y-m-d H:i',$date.' '.$r['work_end']);if(!$start||!$end)return [];
  $q=$this->db->prepare("SELECT start_at,end_at FROM hs_appointments WHERE staff_id=? AND status NOT IN ('cancelled','spam') AND start_at<? AND end_at>?");$q->execute([$staffId,$date.' 23:59:59',$date.' 00:00:00']);$busy=$q->fetchAll(PDO::FETCH_ASSOC);
  $slots=[];$now=new \DateTimeImmutable();
  for($slot=$start;$slot->modify('+'.$duration.' minutes')<=$end;$slot=$slot->modify('+30 minutes')){
   if($slot<=$now)continue;$slotEnd=$slot->modify('+'.$duration.' minutes');$overlap=false;
   foreach($busy as $b){$busyStart=new \DateTimeImmutable($b['start_at']);$busyEnd=new \DateTimeImmutable($b['end_at']);if($slot<$busyEnd&&$slotEnd>$busyStart){$overlap=true;break;}}
   if(!$overlap)$slots[]=$slot->format('H:i');
  }
  return $slots;
 }
 public function createAppointment(array $d,string $ip=''):int{
  $service=(int)($d['service_id']??0);$staff=(int)($d['staff_id']??0);$date=trim((string)($d['date']??''));$time=trim((string)($d['time']??''));$name=trim((string)($d['customer_name']??''));$email=trim((string)($d['email']??''));$phone=trim((string)($d['phone']??''));$message=trim((string)($d['message']??''));
  if($name===''||mb_strlen($name)>160||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190||mb_strlen($message)>3000||!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$time))throw new RuntimeException('Randevu bilgilerini kontrol edin.');
  $ipHash=$ip!==''?hash('sha256',$ip.'|'.(string)($_SERVER['HTTP_USER_AGENT']??'')):null;
  if($ipHash!==null){$sqlite=(string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';$q=$this->db->prepare($sqlite?"SELECT COUNT(*) FROM hs_appointments WHERE ip_hash=? AND created_at>=datetime('now','-1 hour')":"SELECT COUNT(*) FROM hs_appointments WHERE ip_hash=? AND created_at>=DATE_SUB(NOW(),INTERVAL 1 HOUR)");$q->execute([$ipHash]);if((int)$q->fetchColumn()>=5)throw new RuntimeException('Çok fazla randevu talebi gönderildi. Daha sonra tekrar deneyin.');}
  if(!$this->db->beginTransaction())throw new RuntimeException('Randevu işlemi başlatılamadı.');
  try{
   $lock=$this->db->prepare('UPDATE hs_staff SET updated_at=CURRENT_TIMESTAMP WHERE id=?');$lock->execute([$staff]);
   $slots=$this->availableSlots($service,$staff,$date);if(!in_array($time,$slots,true))throw new RuntimeException('Seçilen gün ve saat artık uygun değil.');
   $s=$this->db->prepare("SELECT price,duration_minutes FROM hs_services WHERE id=? AND status='active'");$s->execute([$service]);$v=$s->fetch(PDO::FETCH_ASSOC);if(!$v)throw new RuntimeException('Seçilen hizmet aktif değil.');
   $start=\DateTimeImmutable::createFromFormat('!Y-m-d H:i',$date.' '.$time);if(!$start)throw new RuntimeException('Randevu tarihi geçersiz.');$end=$start->modify('+'.(int)$v['duration_minutes'].' minutes');
   $insert=$this->db->prepare("INSERT INTO hs_appointments(service_id,staff_id,customer_name,email,phone,start_at,end_at,total_price,message,status,ip_hash) VALUES(?,?,?,?,?,?,?,?,?,'new',?)");
   $insert->execute([$service,$staff,$name,$email,$phone,$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),$v['price'],$message,$ipHash]);
   $id=(int)$this->db->lastInsertId();$this->db->commit();return $id;
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
 public function appointments():array{return $this->db->query('SELECT a.*,v.name service_name,s.name staff_name FROM hs_appointments a INNER JOIN hs_services v ON v.id=a.service_id INNER JOIN hs_staff s ON s.id=a.staff_id ORDER BY a.start_at DESC LIMIT 500')->fetchAll(PDO::FETCH_ASSOC);}
 public function updateAppointmentStatus(int $id,string $status):void{if(!in_array($status,['new','confirmed','cancelled','completed','spam'],true))throw new RuntimeException('Randevu durumu geçersiz.');$q=$this->db->prepare('SELECT 1 FROM hs_appointments WHERE id=?');$q->execute([$id]);if(!$q->fetchColumn())throw new RuntimeException('Randevu bulunamadı.');$this->db->prepare('UPDATE hs_appointments SET status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$status,$id]);}
 public function stats():array{return ['services'=>(int)$this->db->query('SELECT COUNT(*) FROM hs_services')->fetchColumn(),'staff'=>(int)$this->db->query("SELECT COUNT(*) FROM hs_staff WHERE status='active'")->fetchColumn(),'appointments'=>(int)$this->db->query("SELECT COUNT(*) FROM hs_appointments WHERE status='new'")->fetchColumn(),'today'=>(int)$this->db->query("SELECT COUNT(*) FROM hs_appointments WHERE start_at>=CURRENT_DATE AND start_at<CURRENT_DATE+1 AND status NOT IN ('cancelled','spam')")->fetchColumn()];}
}
