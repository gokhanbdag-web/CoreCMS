<?php
declare(strict_types=1);

namespace CoreCMS\Modules\RealEstate;

use PDO;

final class StarterPortfolio
{
    private const MODULE = 'real-estate';
    private const PREFIX = 'demo-starter-';

    private static function data(): array
    {
        return [
            'agents'=>[
                'agent-one'=>['name'=>'Deniz Yılmaz','title'=>'Gayrimenkul Danışmanı','bio'=>'Konut alım, satım ve kiralama süreçlerinde danışmanlık.','status'=>'active'],
                'agent-two'=>['name'=>'Ece Demir','title'=>'Portföy Danışmanı','bio'=>'Yatırım ve yaşam alanı seçiminde portföy danışmanlığı.','status'=>'active'],
            ],
            'listings'=>[
                'apartment'=>['title'=>'Çankaya’da Ferah 3+1 Daire','slug'=>'ornek-cankaya-ferah-daire','listing_type'=>'sale','property_type'=>'apartment','price'=>4750000,'city'=>'Ankara','district'=>'Çankaya','neighborhood'=>'Ayrancı','bedrooms'=>3,'bathrooms'=>2,'area_gross'=>145,'area_net'=>128,'description'=>'Aydınlık yaşam alanları, balkon ve merkezi konum. Fotoğraf, fiyat ve diğer bilgiler örnek amaçlıdır.','features_json'=>'["Balkon","Asansör","Merkezi konum"]','featured'=>1,'agent'=>'agent-one'],
                'villa'=>['title'=>'Urla’da Bahçeli Müstakil Villa','slug'=>'ornek-urla-bahceli-villa','listing_type'=>'sale','property_type'=>'villa','price'=>12800000,'city'=>'İzmir','district'=>'Urla','neighborhood'=>'İskele','bedrooms'=>4,'bathrooms'=>3,'area_gross'=>260,'area_net'=>215,'description'=>'Bahçe kullanımı ve geniş yaşam alanlarıyla örnek villa portföyü. Bu ilan gerçek bir satış teklifi değildir.','features_json'=>'["Bahçe","Teras","Otopark"]','featured'=>1,'agent'=>'agent-two'],
                'rental'=>['title'=>'Kadıköy’de Kiralık 2+1 Daire','slug'=>'ornek-kadikoy-kiralik-daire','listing_type'=>'rent','property_type'=>'apartment','price'=>39000,'city'=>'İstanbul','district'=>'Kadıköy','neighborhood'=>'Moda','bedrooms'=>2,'bathrooms'=>1,'area_gross'=>105,'area_net'=>90,'description'=>'Ulaşım ve sosyal alanlara yakın örnek kiralık daire. Bilgiler temsili olup gerçek ilan değildir.','features_json'=>'["Toplu taşımaya yakın","Aydınlık odalar"]','featured'=>0,'agent'=>'agent-one'],
            ],
        ];
    }

    private static function hash(array $row, string $type): string
    {
        $fields=$type==='agent'
            ? ['name','email','phone','title','bio','photo_url','status']
            : ['agent_id','title','slug','listing_type','property_type','price','currency','city','district','neighborhood','address','bedrooms','bathrooms','area_gross','area_net','building_age','floor_number','total_floors','heating','description','features_json','cover_image','gallery_json','status','featured','seo_title','seo_description','published_at'];
        $values=[];
        foreach($fields as $field) $values[$field]=$row[$field]===null?null:(string)$row[$field];
        return hash('sha256',json_encode($values,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    }

    private static function registry(PDO $db,string $type,string $key): ?array
    {
        $q=$db->prepare('SELECT content_id,managed_hash FROM core_module_content_registry WHERE module_id=? AND content_type=? AND content_key=?');
        $q->execute([self::MODULE,$type,self::PREFIX.$key]);
        return $q->fetch(PDO::FETCH_ASSOC)?:null;
    }

    private static function row(PDO $db,string $type,int $id): ?array
    {
        $table=$type==='agent'?'re_agents':'re_listings';
        $q=$db->prepare('SELECT * FROM '.$table.' WHERE id=?');
        $q->execute([$id]);
        return $q->fetch(PDO::FETCH_ASSOC)?:null;
    }

    private static function register(PDO $db,string $type,string $key,int $id,string $hash): void
    {
        $q=$db->prepare('INSERT INTO core_module_content_registry(module_id,content_type,content_key,content_id,managed_hash) VALUES (?,?,?,?,?)');
        $q->execute([self::MODULE,$type,self::PREFIX.$key,$id,$hash]);
    }

    public static function install(PDO $db): void
    {
        $data=self::data();$ids=[];
        foreach($data['agents'] as $key=>$agent){
            $existing=self::registry($db,'agent',$key);
            if($existing && self::row($db,'agent',(int)$existing['content_id'])){$ids[$key]=(int)$existing['content_id'];continue;}
            if($existing) self::forget($db,'agent',$key);
            $q=$db->prepare('INSERT INTO re_agents(name,title,bio,status) VALUES (?,?,?,?)');
            $q->execute([$agent['name'],$agent['title'],$agent['bio'],$agent['status']]);
            $id=(int)$db->lastInsertId();$ids[$key]=$id;
            self::register($db,'agent',$key,$id,self::hash(self::row($db,'agent',$id),'agent'));
        }
        foreach($data['listings'] as $key=>$listing){
            $existing=self::registry($db,'listing',$key);
            if($existing && self::row($db,'listing',(int)$existing['content_id']))continue;
            if($existing)self::forget($db,'listing',$key);
            $slugCheck=$db->prepare('SELECT id FROM re_listings WHERE slug=?');
            $slugCheck->execute([$listing['slug']]);
            if($slugCheck->fetchColumn())continue;
            $q=$db->prepare('INSERT INTO re_listings(agent_id,title,slug,listing_type,property_type,price,currency,city,district,neighborhood,bedrooms,bathrooms,area_gross,area_net,description,features_json,gallery_json,status,featured,published_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)');
            $q->execute([$ids[$listing['agent']],$listing['title'],$listing['slug'],$listing['listing_type'],$listing['property_type'],$listing['price'],'TRY',$listing['city'],$listing['district'],$listing['neighborhood'],$listing['bedrooms'],$listing['bathrooms'],$listing['area_gross'],$listing['area_net'],$listing['description'],$listing['features_json'],'[]','published',$listing['featured']]);
            $id=(int)$db->lastInsertId();
            self::register($db,'listing',$key,$id,self::hash(self::row($db,'listing',$id),'listing'));
        }
    }

    private static function forget(PDO $db,string $type,string $key): void
    {
        $q=$db->prepare('DELETE FROM core_module_content_registry WHERE module_id=? AND content_type=? AND content_key=?');
        $q->execute([self::MODULE,$type,self::PREFIX.$key]);
    }

    public static function remove(PDO $db): void
    {
        $data=self::data();
        foreach(['listing'=>array_keys($data['listings']),'agent'=>array_keys($data['agents'])] as $type=>$keys){
            foreach($keys as $key){
                $entry=self::registry($db,$type,$key);
                if(!$entry)continue;
                $id=(int)$entry['content_id'];$row=self::row($db,$type,$id);
                if(!$row){self::forget($db,$type,$key);continue;}
                $untouched=is_string($entry['managed_hash']) && $entry['managed_hash']!=='' && hash_equals($entry['managed_hash'],self::hash($row,$type));
                if($type==='listing'){
                    $q=$db->prepare('SELECT COUNT(*) FROM re_leads WHERE listing_id=?');$q->execute([$id]);
                    if((int)$q->fetchColumn()>0)$untouched=false;
                }else{
                    $q=$db->prepare('SELECT COUNT(*) FROM re_listings WHERE agent_id=?');$q->execute([$id]);
                    if((int)$q->fetchColumn()>0)$untouched=false;
                }
                if($untouched){
                    $table=$type==='agent'?'re_agents':'re_listings';
                    $q=$db->prepare('DELETE FROM '.$table.' WHERE id=?');$q->execute([$id]);
                    self::forget($db,$type,$key);
                }else{
                    $q=$db->prepare('UPDATE core_module_content_registry SET managed_hash=NULL WHERE module_id=? AND content_type=? AND content_key=?');
                    $q->execute([self::MODULE,$type,self::PREFIX.$key]);
                }
            }
        }
    }
}
