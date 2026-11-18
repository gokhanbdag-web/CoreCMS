<?php
declare(strict_types=1);

use CoreCMS\Core\Csrf;
use CoreCMS\Core\Url;
use CoreCMS\Modules\RealEstate\RealEstateManager;
use CoreCMS\Modules\RealEstate\RealEstateSiteShell;

require_once dirname(__DIR__).'/Admin/RealEstateManager.php';
require_once __DIR__.'/RealEstateSiteShell.php';

return static function (PDO $db,array $params,array $request,array $module): string {
 $manager=new RealEstateManager($db);
 $base=Url::to('/emlak');
 $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
 $money=static fn($v,$currency)=>number_format((float)$v,0,',','.').' '.htmlspecialchars((string)$currency,ENT_QUOTES,'UTF-8');
 $shell=new RealEstateSiteShell($db);
 $header=static fn(string $title,string $description='')=>$shell->header($title,$description,$base);
 $footer=static fn()=>$shell->footer();
 $listingType=static fn($v)=>$v==='rent'?'Kiralık':'Satılık';
 $propertyType=static fn($v)=>['apartment'=>'Daire','villa'=>'Villa','house'=>'Müstakil ev','land'=>'Arsa','commercial'=>'Ticari','office'=>'Ofis','other'=>'Diğer'][$v]??'Gayrimenkul';
 if(isset($params['slug'])){
  $listing=$manager->findListing((string)$params['slug']);
  if(!$listing||$listing['status']!=='published'){http_response_code(404);return $header('İlan bulunamadı').'<section class="re-empty"><h1>İlan bulunamadı</h1><a href="'.$base.'">Portföye dön</a></section>'.$footer;}
  $images=$listing['gallery'];if($listing['cover_image'])array_unshift($images,$listing['cover_image']);
  $html=$header($listing['seo_title']?:$listing['title'],$listing['seo_description']?:substr(strip_tags($listing['description']),0,155));
  $html.='<nav class="re-breadcrumb"><a href="'.$base.'">Portföy</a> / '.$e($listing['title']).'</nav><article class="re-detail">';
  if($images){$html.='<div class="re-gallery">';foreach(array_slice(array_unique($images),0,8) as $image)$html.='<img src="'.$e($image).'" alt="'.$e($listing['title']).'" loading="lazy">';$html.='</div>';}
  $html.='<div class="re-detail-grid"><section><span class="re-tag">'.$listingType($listing['listing_type']).' · '.$propertyType($listing['property_type']).'</span><h1>'.$e($listing['title']).'</h1><p class="re-location">'.$e($listing['district']).' / '.$e($listing['city']).($listing['neighborhood']?' · '.$e($listing['neighborhood']):'').'</p><strong class="re-price">'.$money($listing['price'],$listing['currency']).'</strong><div class="re-specs">';
  foreach(['bedrooms'=>'Oda','bathrooms'=>'Banyo','area_gross'=>'Brüt m²','area_net'=>'Net m²','building_age'=>'Bina yaşı','floor_number'=>'Kat'] as $key=>$label)if($listing[$key]!==null&&$listing[$key]!=='')$html.='<span><small>'.$label.'</small><b>'.$e($listing[$key]).'</b></span>';
  $html.='</div><div class="re-description">'.nl2br($e($listing['description'])).'</div>';
  if($listing['features']){$html.='<h2>Öne çıkan özellikler</h2><ul class="re-features">';foreach($listing['features'] as $feature)$html.='<li>'.$e($feature).'</li>';$html.='</ul>';}
  $html.='</section><aside class="re-contact" id="iletisim"><h2>Bu ilanla ilgileniyor musunuz?</h2>';
  if($listing['agent_name'])$html.='<p><b>'.$e($listing['agent_name']).'</b><br>'.$e($listing['agent_title']).'</p>';
  $html.='<form method="post" action="'.$base.'/talep"><input type="hidden" name="listing_id" value="'.(int)$listing['id'].'"><label>Adınız<input name="name" maxlength="160" required></label><label>E-posta<input type="email" name="email" maxlength="190" required></label><label>Telefon<input name="phone" maxlength="60"></label><label>Mesajınız<textarea name="message" rows="4" maxlength="5000" required>Bu ilan hakkında bilgi almak istiyorum.</textarea></label><label class="re-honey">Web sitesi<input name="website" tabindex="-1" autocomplete="off"></label>'.Csrf::field().'<button class="re-button">Bilgi talep et</button></form></aside></div></article>';
  return $html.$footer();
 }
 $filters=['status'=>'published','listing_type'=>(string)($_GET['listing_type']??''),'property_type'=>(string)($_GET['property_type']??''),'city'=>(string)($_GET['city']??''),'district'=>(string)($_GET['district']??''),'q'=>(string)($_GET['q']??''),'min_price'=>(string)($_GET['min_price']??''),'max_price'=>(string)($_GET['max_price']??'')];
 $listings=$manager->listings($filters);$agents=array_values(array_filter($manager->agents(),static fn($a)=>$a['status']==='active'));
 $html=$header('Gayrimenkul Portföyü','Satılık ve kiralık gayrimenkul ilanları');
 $html.='<section class="re-hero"><span>DOĞRU YATIRIM, DOĞRU ADRES</span><h1>Hayalinizdeki yaşam<br><em>bir ilan uzağınızda.</em></h1><p>Satılık ve kiralık gayrimenkul portföyümüzü keşfedin.</p><form class="re-search" method="get"><input name="q" value="'.$e($filters['q']).'" placeholder="İlan, konum veya özellik ara"><select name="listing_type"><option value="">Satılık / Kiralık</option><option value="sale"'.($filters['listing_type']==='sale'?' selected':'').'>Satılık</option><option value="rent"'.($filters['listing_type']==='rent'?' selected':'').'>Kiralık</option></select><select name="property_type"><option value="">Gayrimenkul tipi</option>';
  foreach(['apartment'=>'Daire','villa'=>'Villa','house'=>'Müstakil ev','land'=>'Arsa','commercial'=>'Ticari','office'=>'Ofis','other'=>'Diğer'] as $v=>$label)$html.='<option value="'.$v.'"'.($filters['property_type']===$v?' selected':'').'>'.$label.'</option>';
 $html.='</select><input name="city" value="'.$e($filters['city']).'" placeholder="Şehir"><input name="district" value="'.$e($filters['district']).'" placeholder="İlçe"><input type="number" min="0" step="1" name="min_price" value="'.$e($filters['min_price']).'" placeholder="En az fiyat" aria-label="En az fiyat"><input type="number" min="0" step="1" name="max_price" value="'.$e($filters['max_price']).'" placeholder="En fazla fiyat" aria-label="En fazla fiyat"><button class="re-button">İlanları bul</button></form></section><section class="re-listings"><div class="re-section-head"><div><span>GÜNCEL PORTFÖY</span><h2>Öne çıkan gayrimenkuller</h2></div><small>'.count($listings).' ilan listeleniyor</small></div><div class="re-grid">';
 foreach($listings as $l){$image=$l['cover_image']?:'';$html.='<article class="re-card"><a class="re-card-image" href="'.$base.'/ilan/'.$e($l['slug']).'">'.($image?'<img src="'.$e($image).'" alt="'.$e($l['title']).'" loading="lazy">':'<span>Gayrimenkul</span>').'<i>'.$listingType($l['listing_type']).'</i></a><div class="re-card-body"><small>'.$e($l['district']).' / '.$e($l['city']).'</small><h3><a href="'.$base.'/ilan/'.$e($l['slug']).'">'.$e($l['title']).'</a></h3><div class="re-card-foot"><strong>'.$money($l['price'],$l['currency']).'</strong><span>'.$propertyType($l['property_type']).'</span></div><div class="re-card-spec">'.($l['bedrooms']!==null?$e($l['bedrooms']).' oda · ':'').($l['area_net']!==null?$e($l['area_net']).' m²':'').'</div></div></article>';}
 $html.='</div>';if(!$listings)$html.='<div class="re-empty"><h3>Aramanıza uygun ilan bulunamadı</h3><p>Filtreleri değiştirerek yeniden deneyebilirsiniz.</p></div>';$html.='</section>';
 if($agents){$html.='<section class="re-agents" id="danismanlar"><span>UZMAN EKİBİMİZ</span><h2>Size yardımcı olmaya hazırız</h2><div class="re-agent-grid">';foreach($agents as $a)$html.='<article>'.($a['photo_url']?'<img src="'.$e($a['photo_url']).'" alt="'.$e($a['name']).'">':'<div class="re-avatar">'.mb_strtoupper(mb_substr($a['name'],0,1)).'</div>').'<h3>'.$e($a['name']).'</h3><p>'.$e($a['title']).'</p>'.($a['phone']?'<a href="tel:'.$e(preg_replace('/[^0-9+]/','',$a['phone'])).'">'.$e($a['phone']).'</a>':'').'</article>';$html.='</div></section>';}
 return $html.$footer;
};
