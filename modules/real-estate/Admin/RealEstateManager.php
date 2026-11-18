<?php
declare(strict_types=1);

namespace CoreCMS\Modules\RealEstate;

use PDO;
use RuntimeException;

final class RealEstateManager
{
 public function __construct(private PDO $db){}
 private function jsonList(mixed $value): array { $v=json_decode((string)$value,true);return is_array($v)?array_values(array_filter($v,'is_string')):[]; }
 public function listings(array $filters=[]): array {
  $where=[];$args=[];
  $status=(string)($filters['status']??'published');
  if($status!=='all'){$where[]='l.status=?';$args[]=$status;}
  foreach(['listing_type','property_type','city','district'] as $key){$v=trim((string)($filters[$key]??''));if($v!==''){$where[]='l.'.$key.'=?';$args[]=$v;}}
  $q=trim((string)($filters['q']??''));if($q!==''){$where[]='(l.title LIKE ? OR l.description LIKE ? OR l.neighborhood LIKE ?)';array_push($args,'%'.$q.'%','%'.$q.'%','%'.$q.'%');}
  $min=filter_var($filters['min_price']??null,FILTER_VALIDATE_FLOAT);if($min!==false&&$min!==null&&$min>=0){$where[]='l.price>=?';$args[]=$min;}
  $max=filter_var($filters['max_price']??null,FILTER_VALIDATE_FLOAT);if($max!==false&&$max!==null&&$max>0){$where[]='l.price<=?';$args[]=$max;}
  $sql='SELECT l.*,a.name agent_name FROM re_listings l LEFT JOIN re_agents a ON a.id=l.agent_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY l.featured DESC,l.published_at DESC,l.id DESC LIMIT 100';
  $s=$this->db->prepare($sql);$s->execute($args);return $s->fetchAll(PDO::FETCH_ASSOC);
 }
 public function findListing(int|string $idOrSlug): ?array {
  $field=is_int($idOrSlug)||ctype_digit((string)$idOrSlug)?'l.id':'l.slug';
  $s=$this->db->prepare('SELECT l.*,a.name agent_name,a.phone agent_phone,a.email agent_email,a.title agent_title,a.photo_url agent_photo FROM re_listings l LEFT JOIN re_agents a ON a.id=l.agent_id WHERE '.$field.'=? LIMIT 1');
  $s->execute([$idOrSlug]);$r=$s->fetch(PDO::FETCH_ASSOC);if(!$r)return null;$r['features']=$this->jsonList($r['features_json']);$r['gallery']=$this->jsonList($r['gallery_json']);return $r;
 }
 public function saveListing(array $d): int {
  $id=(int)($d['id']??0);$title=trim((string)($d['title']??''));$slug=strtolower(trim((string)($d['slug']??'')));
  if($title===''||mb_strlen($title)>255)throw new RuntimeException('İlan başlığı zorunludur.');
  if(!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug))throw new RuntimeException('SEO bağlantısı geçerli olmalıdır.');
  $type=(string)($d['listing_type']??'sale');$property=(string)($d['property_type']??'apartment');
  if(!in_array($type,['sale','rent'],true)||!in_array($property,['apartment','villa','house','land','commercial','office','other'],true))throw new RuntimeException('İlan türü veya gayrimenkul tipi geçersiz.');
  $price=filter_var($d['price']??null,FILTER_VALIDATE_FLOAT);if($price===false||$price<0)throw new RuntimeException('Geçerli bir fiyat girin.');
  $city=trim((string)($d['city']??''));$district=trim((string)($d['district']??''));$description=trim((string)($d['description']??''));
  if($city===''||$district===''||$description==='')throw new RuntimeException('Şehir, ilçe ve açıklama zorunludur.');
  $agent=(int)($d['agent_id']??0);if($agent>0){$a=$this->db->prepare("SELECT 1 FROM re_agents WHERE id=? AND status='active'");$a->execute([$agent]);if(!$a->fetchColumn())throw new RuntimeException('Seçilen danışman aktif değil.');}else $agent=null;
  $currency=strtoupper((string)($d['currency']??'TRY'));if(!preg_match('/^[A-Z]{3}$/',$currency))throw new RuntimeException('Para birimi geçersiz.');
  $cover=trim((string)($d['cover_image']??''));if($cover!==''&&!preg_match('#^(https://|/)(?!/)[^\r\n]*$#i',$cover))throw new RuntimeException('Kapak görseli HTTPS veya site içi bir yol olmalıdır.');
  $features=array_slice(array_values(array_filter(array_map('trim',preg_split('/\r\n|\r|\n/',(string)($d['features']??''))?:[]))),0,40);
  $gallery=array_slice(array_values(array_filter(array_map('trim',preg_split('/\r\n|\r|\n/',(string)($d['gallery']??''))?:[]))),0,20);
  foreach($gallery as $image)if(!preg_match('#^(https://|/)(?!/)[^\r\n]*$#i',$image))throw new RuntimeException('Galeri görselleri HTTPS veya site içi yollar olmalıdır.');
  $values=[$agent,$title,$slug,$type,$property,$price,$currency,$city,$district,trim((string)($d['neighborhood']??'')),trim((string)($d['address']??'')),$this->nullableInt($d['bedrooms']??null),$this->nullableInt($d['bathrooms']??null),$this->nullableInt($d['area_gross']??null),$this->nullableInt($d['area_net']??null),$this->nullableInt($d['building_age']??null),$this->nullableInt($d['floor_number']??null),$this->nullableInt($d['total_floors']??null),trim((string)($d['heating']??'')),$description,json_encode($features,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$cover,json_encode($gallery,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),(string)($d['status']??'draft')==='published'?'published':'draft',isset($d['featured'])?1:0,trim((string)($d['seo_title']??'')),trim((string)($d['seo_description']??''))];
  if(!in_array($values[23],['published','draft'],true))$values[23]='draft';
  if($id>0){$values[]=$id;$sql='UPDATE re_listings SET agent_id=?,title=?,slug=?,listing_type=?,property_type=?,price=?,currency=?,city=?,district=?,neighborhood=?,address=?,bedrooms=?,bathrooms=?,area_gross=?,area_net=?,building_age=?,floor_number=?,total_floors=?,heating=?,description=?,features_json=?,cover_image=?,gallery_json=?,status=?,featured=?,seo_title=?,seo_description=?,published_at=CASE WHEN ?='published' THEN COALESCE(published_at,CURRENT_TIMESTAMP) ELSE NULL END WHERE id=?';array_splice($values,27,0,[$values[23]]);}
  else{$sql='INSERT INTO re_listings (agent_id,title,slug,listing_type,property_type,price,currency,city,district,neighborhood,address,bedrooms,bathrooms,area_gross,area_net,building_age,floor_number,total_floors,heating,description,features_json,cover_image,gallery_json,status,featured,seo_title,seo_description,published_at) VALUES ('.implode(',',array_fill(0,27,'?')).',CASE WHEN ?='published' THEN CURRENT_TIMESTAMP ELSE NULL END)';$values[]=$values[23];}
  $s=$this->db->prepare($sql);$s->execute($values);return $id>0?$id:(int)$this->db->lastInsertId();
 }
 private function nullableInt(mixed $v): ?int { if($v===null||$v==='')return null;$n=filter_var($v,FILTER_VALIDATE_INT);if($n===false||$n<0||$n>100000)throw new RuntimeException('Sayısal özellik alanlarından biri geçersiz.');return $n; }
 public function deleteListing(int $id): void {$s=$this->db->prepare('DELETE FROM re_listings WHERE id=?');$s->execute([$id]);if(!$s->rowCount())throw new RuntimeException('İlan bulunamadı.');}
 public function agents(): array {return $this->db->query('SELECT * FROM re_agents ORDER BY status,name')->fetchAll(PDO::FETCH_ASSOC);}
 public function saveAgent(array $d): int {
  $id=(int)($d['id']??0);$name=trim((string)($d['name']??''));if($name===''||mb_strlen($name)>160)throw new RuntimeException('Danışman adı zorunludur.');
  $email=trim((string)($d['email']??''));if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Danışman e-posta adresi geçersiz.');
  $photo=trim((string)($d['photo_url']??''));if($photo!==''&&!preg_match('#^(https://|/)(?!/)[^\r\n]*$#i',$photo))throw new RuntimeException('Fotoğraf HTTPS veya site içi yol olmalıdır.');
  $values=[$name,$email?:null,trim((string)($d['phone']??'')),trim((string)($d['title']??'')),trim((string)($d['bio']??'')),$photo?:null,($d['status']??'active')==='inactive'?'inactive':'active'];
  if($id>0){$values[]=$id;$s=$this->db->prepare('UPDATE re_agents SET name=?,email=?,phone=?,title=?,bio=?,photo_url=?,status=? WHERE id=?');}
  else{$s=$this->db->prepare('INSERT INTO re_agents (name,email,phone,title,bio,photo_url,status) VALUES (?,?,?,?,?,?,?)');}
  $s->execute($values);return $id?: (int)$this->db->lastInsertId();
 }
 public function deleteAgent(int $id): void {$s=$this->db->prepare('DELETE FROM re_agents WHERE id=?');$s->execute([$id]);if(!$s->rowCount())throw new RuntimeException('Danışman bulunamadı.');}
 public function createLead(array $d,string $ip=''): int {
  $name=trim((string)($d['name']??''));$email=trim((string)($d['email']??''));$phone=trim((string)($d['phone']??''));$message=trim((string)($d['message']??''));$listing=(int)($d['listing_id']??0);
  if($name===''||mb_strlen($name)>160||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190||$message===''||mb_strlen($message)>5000)throw new RuntimeException('Lütfen ad, geçerli e-posta ve mesaj alanlarını doldurun.');
  if($listing>0){$s=$this->db->prepare("SELECT 1 FROM re_listings WHERE id=? AND status='published'");$s->execute([$listing]);if(!$s->fetchColumn())$listing=null;}else $listing=null;
  $hash=$ip!==''?hash('sha256',$ip.'|'.(string)($_SERVER['HTTP_USER_AGENT']??'')):null;
  $s=$this->db->prepare("INSERT INTO re_leads (listing_id,name,email,phone,message,source,status,ip_hash) VALUES (?,?,?,?,?,'website','new',?)");
  $s->execute([$listing,$name,$email,$phone,$message,$hash]);return (int)$this->db->lastInsertId();
 }
 public function leads(): array {return $this->db->query('SELECT l.*,p.title listing_title FROM re_leads l LEFT JOIN re_listings p ON p.id=l.listing_id ORDER BY l.id DESC LIMIT 300')->fetchAll(PDO::FETCH_ASSOC);}
 public function updateLeadStatus(int $id,string $status): void {if(!in_array($status,['new','contacted','closed','spam'],true))throw new RuntimeException('Talep durumu geçersiz.');$s=$this->db->prepare('UPDATE re_leads SET status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');$s->execute([$status,$id]);if(!$s->rowCount())throw new RuntimeException('Talep bulunamadı veya değişiklik yapılmadı.');}
 public function stats(): array {return ['listings'=>(int)$this->db->query('SELECT COUNT(*) FROM re_listings')->fetchColumn(),'published'=>(int)$this->db->query("SELECT COUNT(*) FROM re_listings WHERE status='published'")->fetchColumn(),'agents'=>(int)$this->db->query("SELECT COUNT(*) FROM re_agents WHERE status='active'")->fetchColumn(),'leads'=>(int)$this->db->query("SELECT COUNT(*) FROM re_leads WHERE status='new'")->fetchColumn()];}
}
