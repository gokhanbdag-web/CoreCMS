<?php
declare(strict_types=1);

namespace CoreCMS\Modules\Tourism;

use PDO;
use RuntimeException;
use Throwable;

final class TourismManager
{
 public function __construct(private PDO $db){}
 private function lines(mixed $v,int $max=30):array{return array_slice(array_values(array_filter(array_map('trim',preg_split('/\r\n|\r|\n/',(string)$v)?:[]))),0,$max);}
 private function jsonLines(mixed $v):array{$d=json_decode((string)$v,true);return is_array($d)?array_values(array_filter($d,'is_string')):[];}
 public function destinations(bool $activeOnly=false):array{return $this->db->query('SELECT * FROM tm_destinations'.($activeOnly?" WHERE status='active'":'').' ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);}
 public function saveDestination(array $d):int{
  $id=(int)($d['id']??0);$name=trim((string)($d['name']??''));$slug=strtolower(trim((string)($d['slug']??'')));
  if($name===''||mb_strlen($name)>160||!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug))throw new RuntimeException('Destinasyon adı ve SEO bağlantısı geçerli olmalıdır.');
  $image=trim((string)($d['cover_image']??''));if($image!==''&&!preg_match('#^(https://|/)(?!/)[^\r\n]*$#i',$image))throw new RuntimeException('Görsel HTTPS veya site içi yol olmalıdır.');
  if($id>0){$s=$this->db->prepare('UPDATE tm_destinations SET name=?,slug=?,description=?,cover_image=?,status=? WHERE id=?');$s->execute([$name,$slug,trim((string)($d['description']??'')),$image?:null,($d['status']??'active')==='inactive'?'inactive':'active',$id]);if(!$s->rowCount()){$q=$this->db->prepare('SELECT 1 FROM tm_destinations WHERE id=?');$q->execute([$id]);if(!$q->fetchColumn())throw new RuntimeException('Destinasyon bulunamadı.');}return $id;}
  $s=$this->db->prepare('INSERT INTO tm_destinations(name,slug,description,cover_image,status) VALUES(?,?,?,?,?)');$s->execute([$name,$slug,trim((string)($d['description']??'')),$image?:null,($d['status']??'active')==='inactive'?'inactive':'active']);return (int)$this->db->lastInsertId();
 }
 public function deleteDestination(int $id):void{$s=$this->db->prepare('DELETE FROM tm_destinations WHERE id=?');$s->execute([$id]);if(!$s->rowCount())throw new RuntimeException('Destinasyon bulunamadı.');}
 public function tours(array $filters=[]):array{
  $where=[];$args=[];$status=(string)($filters['status']??'published');if($status!=='all'){$where[]='t.status=?';$args[]=$status;}
  foreach(['destination_id','category'] as $key){$v=trim((string)($filters[$key]??''));if($v!==''){$where[]='t.'.$key.'=?';$args[]=$v;}}
  $q=trim((string)($filters['q']??''));if($q!==''){$where[]='(t.title LIKE ? OR t.description LIKE ?)';$args[]='%'.$q.'%';$args[]='%'.$q.'%';}
  $sql='SELECT t.*,d.name destination_name FROM tm_tours t LEFT JOIN tm_destinations d ON d.id=t.destination_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY t.featured DESC,t.id DESC LIMIT 100';
  $s=$this->db->prepare($sql);$s->execute($args);return $s->fetchAll(PDO::FETCH_ASSOC);
 }
 public function findTour(int|string $idOrSlug):?array{
  $field=is_int($idOrSlug)||ctype_digit((string)$idOrSlug)?'t.id':'t.slug';
  $s=$this->db->prepare('SELECT t.*,d.name destination_name FROM tm_tours t LEFT JOIN tm_destinations d ON d.id=t.destination_id WHERE '.$field.'=? LIMIT 1');$s->execute([$idOrSlug]);$r=$s->fetch(PDO::FETCH_ASSOC);if(!$r)return null;
  $r['itinerary']=$this->jsonLines($r['itinerary_json']);$r['gallery']=$this->jsonLines($r['gallery_json']);$r['departures']=$this->departures((int)$r['id'],true);return $r;
 }
 public function saveTour(array $d):int{
  $id=(int)($d['id']??0);$title=trim((string)($d['title']??''));$slug=strtolower(trim((string)($d['slug']??'')));
  if($title===''||mb_strlen($title)>255||!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug))throw new RuntimeException('Tur adı ve SEO bağlantısı geçerli olmalıdır.');
  $price=filter_var($d['price']??null,FILTER_VALIDATE_FLOAT);$duration=filter_var($d['duration_hours']??1,FILTER_VALIDATE_FLOAT);
  if($price===false||$price<0||$duration===false||$duration<=0||$duration>720)throw new RuntimeException('Fiyat ve tur süresi geçerli olmalıdır.');
  $min=filter_var($d['min_guests']??1,FILTER_VALIDATE_INT);$max=filter_var($d['max_guests']??20,FILTER_VALIDATE_INT);if($min===false||$max===false||$min<1||$max<$min||$max>500)throw new RuntimeException('Minimum ve maksimum katılımcı sayısı geçersiz.');
  $destination=(int)($d['destination_id']??0);if($destination>0){$q=$this->db->prepare("SELECT 1 FROM tm_destinations WHERE id=? AND status='active'");$q->execute([$destination]);if(!$q->fetchColumn())throw new RuntimeException('Seçilen destinasyon aktif değil.');}else $destination=null;
  $currency=strtoupper((string)($d['currency']??'TRY'));if(!preg_match('/^[A-Z]{3}$/',$currency))throw new RuntimeException('Para birimi geçersiz.');
  $cover=trim((string)($d['cover_image']??''));if($cover!==''&&!preg_match('#^(https://|/)(?!/)[^\r\n]*$#i',$cover))throw new RuntimeException('Kapak görseli HTTPS veya site içi yol olmalıdır.');
  $gallery=$this->lines($d['gallery']??'',20);foreach($gallery as $url)if(!preg_match('#^(https://|/)(?!/)[^\r\n]*$#i',$url))throw new RuntimeException('Galeri görselleri HTTPS veya site içi yollar olmalıdır.');
  $values=[$destination,$title,$slug,trim((string)($d['category']??'activity')),$duration,$price,$currency,$min,$max,trim((string)($d['description']??'')),json_encode($this->lines($d['itinerary']??'',40),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),trim((string)($d['included']??'')),trim((string)($d['not_included']??'')),$cover?:null,json_encode($gallery,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),($d['status']??'draft')==='published'?'published':'draft',isset($d['featured'])?1:0,trim((string)($d['seo_title']??'')),trim((string)($d['seo_description']??''))];
  if($values[9]==='')throw new RuntimeException('Tur açıklaması zorunludur.');
  if($id>0){$check=$this->db->prepare('SELECT 1 FROM tm_tours WHERE id=?');$check->execute([$id]);if(!$check->fetchColumn())throw new RuntimeException('Tur bulunamadı.');$values[]=$id;$s=$this->db->prepare('UPDATE tm_tours SET destination_id=?,title=?,slug=?,category=?,duration_hours=?,price=?,currency=?,min_guests=?,max_guests=?,description=?,itinerary_json=?,included=?,not_included=?,cover_image=?,gallery_json=?,status=?,featured=?,seo_title=?,seo_description=? WHERE id=?');}
  else{$s=$this->db->prepare('INSERT INTO tm_tours(destination_id,title,slug,category,duration_hours,price,currency,min_guests,max_guests,description,itinerary_json,included,not_included,cover_image,gallery_json,status,featured,seo_title,seo_description) VALUES('.implode(',',array_fill(0,19,'?')).')');}
  $s->execute($values);return $id?: (int)$this->db->lastInsertId();
 }
 public function deleteTour(int $id):void{$q=$this->db->prepare('SELECT COUNT(*) FROM tm_bookings WHERE tour_id=?');$q->execute([$id]);if((int)$q->fetchColumn()>0)throw new RuntimeException('Rezervasyon kaydı bulunan tur silinemez; yayından kaldırın.');$s=$this->db->prepare('DELETE FROM tm_tours WHERE id=?');$s->execute([$id]);if(!$s->rowCount())throw new RuntimeException('Tur bulunamadı.');}
 public function departures(int $tourId=0,bool $upcomingOnly=false):array{
  $where=[];$args=[];if($tourId>0){$where[]='d.tour_id=?';$args[]=$tourId;}if($upcomingOnly){$where[]='d.departure_at>=?';$args[]=date('Y-m-d H:i:s');$where[]="d.status='open'";}
  $sql='SELECT d.*,t.title tour_title FROM tm_departures d INNER JOIN tm_tours t ON t.id=d.tour_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY d.departure_at ASC LIMIT 300';$s=$this->db->prepare($sql);$s->execute($args);return $s->fetchAll(PDO::FETCH_ASSOC);
 }
 public function saveDeparture(array $d):int{
  $id=(int)($d['id']??0);$tour=(int)($d['tour_id']??0);$date=trim((string)($d['departure_at']??''));$seats=filter_var($d['seats_total']??20,FILTER_VALIDATE_INT);
  $q=$this->db->prepare("SELECT 1 FROM tm_tours WHERE id=? AND status='published'");$q->execute([$tour]);if(!$q->fetchColumn())throw new RuntimeException('Yayındaki tur seçilmelidir.');
  $dt=\DateTimeImmutable::createFromFormat('Y-m-d\TH:i',$date)?:\DateTimeImmutable::createFromFormat('Y-m-d H:i:s',$date);if(!$dt||$dt<=new \DateTimeImmutable())throw new RuntimeException('Kalkış tarihi gelecekte olmalıdır.');
  if($seats===false||$seats<1||$seats>500)throw new RuntimeException('Kapasite 1 ile 500 arasında olmalıdır.');
  if($id>0){$check=$this->db->prepare('SELECT seats_reserved,tour_id FROM tm_departures WHERE id=?');$check->execute([$id]);$existing=$check->fetch(PDO::FETCH_ASSOC);if(!$existing)throw new RuntimeException('Kalkış kaydı bulunamadı.');if($seats<(int)$existing['seats_reserved'])throw new RuntimeException('Kapasite mevcut rezervasyon sayısından düşük olamaz.');if((int)$existing['tour_id']!==$tour){$bookings=$this->db->prepare('SELECT COUNT(*) FROM tm_bookings WHERE departure_id=?');$bookings->execute([$id]);if((int)$bookings->fetchColumn()>0)throw new RuntimeException('Rezervasyon bulunan kalkış başka bir tura taşınamaz.');}$s=$this->db->prepare('UPDATE tm_departures SET tour_id=?,departure_at=?,seats_total=?,status=? WHERE id=?');$s->execute([$tour,$dt->format('Y-m-d H:i:s'),$seats,($d['status']??'open')==='closed'?'closed':'open',$id]);return $id;}
  $s=$this->db->prepare('INSERT INTO tm_departures(tour_id,departure_at,seats_total,status) VALUES(?,?,?,?)');$s->execute([$tour,$dt->format('Y-m-d H:i:s'),$seats,($d['status']??'open')==='closed'?'closed':'open']);return (int)$this->db->lastInsertId();
 }
 public function deleteDeparture(int $id):void{$q=$this->db->prepare('SELECT COUNT(*) FROM tm_bookings WHERE departure_id=?');$q->execute([$id]);if((int)$q->fetchColumn()>0)throw new RuntimeException('Rezervasyonu bulunan kalkış silinemez; kapatın.');$s=$this->db->prepare('DELETE FROM tm_departures WHERE id=?');$s->execute([$id]);if(!$s->rowCount())throw new RuntimeException('Kalkış kaydı bulunamadı.');}
 public function createBooking(array $d,string $ip=''):int{
  $tour=(int)($d['tour_id']??0);$departure=(int)($d['departure_id']??0);$name=trim((string)($d['name']??''));$email=trim((string)($d['email']??''));$phone=trim((string)($d['phone']??''));$adults=filter_var($d['adults']??1,FILTER_VALIDATE_INT);$children=filter_var($d['children']??0,FILTER_VALIDATE_INT);$message=trim((string)($d['message']??''));
  if($name===''||mb_strlen($name)>160||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190||$adults===false||$children===false||$adults<1||$children<0||$adults+$children>500||mb_strlen($message)>3000)throw new RuntimeException('Rezervasyon bilgilerini kontrol edin.');
  $q=$this->db->prepare("SELECT price,max_guests FROM tm_tours WHERE id=? AND status='published'");$q->execute([$tour]);$t=$q->fetch(PDO::FETCH_ASSOC);if(!$t)throw new RuntimeException('Tur şu anda rezervasyona açık değil.');
  if($adults+$children>(int)$t['max_guests'])throw new RuntimeException('Katılımcı sayısı turun maksimum kapasitesini aşıyor.');
  $ipHash=$ip!==''?hash('sha256',$ip.'|'.(string)($_SERVER['HTTP_USER_AGENT']??'')):null;
  if($ipHash!==null){$sqlite=(string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';$limit=$this->db->prepare($sqlite?"SELECT COUNT(*) FROM tm_bookings WHERE ip_hash=? AND created_at>=datetime('now','-1 hour')":"SELECT COUNT(*) FROM tm_bookings WHERE ip_hash=? AND created_at>=DATE_SUB(NOW(),INTERVAL 1 HOUR)");$limit->execute([$ipHash]);if((int)$limit->fetchColumn()>=5)throw new RuntimeException('Çok fazla rezervasyon talebi gönderildi. Daha sonra tekrar deneyin.');}
  if(!$this->db->beginTransaction())throw new RuntimeException('Rezervasyon başlatılamadı.');
  try{
   $seat=$this->db->prepare("UPDATE tm_departures SET seats_reserved=seats_reserved+? WHERE id=? AND tour_id=? AND status='open' AND departure_at>? AND seats_total-seats_reserved>=?");
   $seat->execute([$adults+$children,$departure,$tour,date('Y-m-d H:i:s'),$adults+$children]);
   if($seat->rowCount()!==1)throw new RuntimeException('Seçilen kalkışta yeterli boş yer bulunmuyor.');
   $total=(float)$t['price']*($adults+$children);
   $s=$this->db->prepare("INSERT INTO tm_bookings(tour_id,departure_id,name,email,phone,adults,children,total_price,message,status,ip_hash) VALUES(?,?,?,?,?,?,?,?,?,'new',?)");
   $s->execute([$tour,$departure,$name,$email,$phone,$adults,$children,$total,$message,$ipHash]);$id=(int)$this->db->lastInsertId();$this->db->commit();return $id;
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
 public function bookings():array{return $this->db->query('SELECT b.*,t.title tour_title,d.departure_at FROM tm_bookings b INNER JOIN tm_tours t ON t.id=b.tour_id LEFT JOIN tm_departures d ON d.id=b.departure_id ORDER BY b.id DESC LIMIT 300')->fetchAll(PDO::FETCH_ASSOC);}
 public function updateBookingStatus(int $id,string $status):void{
  if(!in_array($status,['new','confirmed','cancelled','spam'],true))throw new RuntimeException('Rezervasyon durumu geçersiz.');
  if(!$this->db->beginTransaction())throw new RuntimeException('Durum güncellemesi başlatılamadı.');
  try{
   $q=$this->db->prepare('SELECT departure_id,adults,children,status FROM tm_bookings WHERE id=?');$q->execute([$id]);$b=$q->fetch(PDO::FETCH_ASSOC);if(!$b)throw new RuntimeException('Rezervasyon bulunamadı.');
   $wasReleased=in_array($b['status'],['cancelled','spam'],true);$willRelease=in_array($status,['cancelled','spam'],true);$seats=(int)$b['adults']+(int)$b['children'];$departure=(int)($b['departure_id']??0);
   if($departure>0&&$wasReleased&&!$willRelease){$s=$this->db->prepare("UPDATE tm_departures SET seats_reserved=seats_reserved+? WHERE id=? AND status='open' AND departure_at>? AND seats_total-seats_reserved>=?");$s->execute([$seats,$departure,date('Y-m-d H:i:s'),$seats]);if($s->rowCount()!==1)throw new RuntimeException('Kalkışta tekrar yer ayırtılamıyor.');}
   if($departure>0&&!$wasReleased&&$willRelease){$s=$this->db->prepare('UPDATE tm_departures SET seats_reserved=CASE WHEN seats_reserved>=? THEN seats_reserved-? ELSE 0 END WHERE id=?');$s->execute([$seats,$seats,$departure]);}
   $s=$this->db->prepare('UPDATE tm_bookings SET status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');$s->execute([$status,$id]);$this->db->commit();
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
 public function stats():array{return ['tours'=>(int)$this->db->query('SELECT COUNT(*) FROM tm_tours')->fetchColumn(),'published'=>(int)$this->db->query("SELECT COUNT(*) FROM tm_tours WHERE status='published'")->fetchColumn(),'departures'=>(int)$this->db->query("SELECT COUNT(*) FROM tm_departures WHERE status='open' AND departure_at>=CURRENT_TIMESTAMP")->fetchColumn(),'bookings'=>(int)$this->db->query("SELECT COUNT(*) FROM tm_bookings WHERE status='new'")->fetchColumn()];}
}
