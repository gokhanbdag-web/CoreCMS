<?php
declare(strict_types=1);

use CoreCMS\Core\Url;
use CoreCMS\Modules\RealEstate\RealEstateManager;
use CoreCMS\Modules\RealEstate\RealEstateSiteShell;

require_once dirname(__DIR__).'/Admin/RealEstateManager.php';
require_once __DIR__.'/RealEstateSiteShell.php';

return static function (PDO $db,array $params,array $request,array $module): string {
 require_once dirname(__DIR__,3).'/app/Core/Csrf.php';
 CoreCMS\Core\Auth::startSession();
 $manager=new RealEstateManager($db);
 $listing=$manager->findListing((string)($params['slug']??''));
 $shell=new RealEstateSiteShell($db);
 $base=Url::to('/emlak');
 if(!$listing||$listing['status']!=='published'){
  http_response_code(404);
  return $shell->header('İlan bulunamadı','Aradığınız ilan yayında değil veya kaldırılmış olabilir.',$base).'<section class="re-empty"><h1>İlan bulunamadı</h1><p>Aradığınız ilan yayında değil veya kaldırılmış olabilir.</p><a class="re-button" href="'.$base.'">Portföye dön</a></section>'.$shell->footer();
 }
 $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
 $money=number_format((float)$listing['price'],0,',','.').' '.$e($listing['currency']);
 $listingType=$listing['listing_type']==='rent'?'Kiralık':'Satılık';
 $propertyType=['apartment'=>'Daire','villa'=>'Villa','house'=>'Müstakil ev','land'=>'Arsa','commercial'=>'Ticari','office'=>'Ofis','other'=>'Gayrimenkul'][$listing['property_type']]??'Gayrimenkul';
 $images=$listing['gallery'];if($listing['cover_image'])array_unshift($images,$listing['cover_image']);
 $html=$shell->header($listing['seo_title']?:$listing['title'],$listing['seo_description']?:substr(strip_tags($listing['description']),0,155),$base);
 $html.='<nav class="re-breadcrumb"><a href="'.$base.'">Portföy</a> / '.$e($listing['title']).'</nav><article class="re-detail">';
 if($images){
  $html.='<div class="re-gallery">';
  foreach(array_slice(array_unique($images),0,8) as $index=>$image)$html.='<img src="'.$e($image).'" alt="'.$e($listing['title']).' - Fotoğraf '.($index+1).'" loading="lazy">';
  $html.='</div>';
 }
 $html.='<div class="re-detail-grid"><section><span class="re-tag">'.$listingType.' · '.$propertyType.'</span><h1>'.$e($listing['title']).'</h1><p class="re-location">'.$e($listing['district']).' / '.$e($listing['city']).($listing['neighborhood']?' · '.$e($listing['neighborhood']):'').'</p><strong class="re-price">'.$money.'</strong><div class="re-specs">';
 foreach(['bedrooms'=>'Oda','bathrooms'=>'Banyo','area_gross'=>'Brüt m²','area_net'=>'Net m²','building_age'=>'Bina yaşı','floor_number'=>'Bulunduğu kat','total_floors'=>'Toplam kat'] as $key=>$label){
  if($listing[$key]!==null&&$listing[$key]!=='')$html.='<span><small>'.$label.'</small><b>'.$e($listing[$key]).'</b></span>';
 }
 if(!empty($listing['heating']))$html.='<span><small>Isıtma</small><b>'.$e($listing['heating']).'</b></span>';
 $html.='</div><div class="re-description">'.nl2br($e($listing['description'])).'</div>';
 if(!empty($listing['features'])){
  $html.='<h2>Öne çıkan özellikler</h2><ul class="re-features">';
  foreach($listing['features'] as $feature)$html.='<li>'.$e($feature).'</li>';
  $html.='</ul>';
 }
 if(!empty($listing['address']))$html.='<h2>Konum</h2><p class="re-location">'.$e($listing['address']).'</p>';
 $html.='</section><aside class="re-contact" id="iletisim"><h2>Bu ilanla ilgileniyor musunuz?</h2>';
 if(!empty($listing['agent_name'])){
  $html.='<div class="re-detail-agent">';
  if(!empty($listing['agent_photo']))$html.='<img src="'.$e($listing['agent_photo']).'" alt="'.$e($listing['agent_name']).'">';
  $html.='<p><b>'.$e($listing['agent_name']).'</b><br>'.$e($listing['agent_title']??'Gayrimenkul Danışmanı').'</p>';
  if(!empty($listing['agent_phone']))$html.='<a href="tel:'.$e(preg_replace('/[^0-9+]/','',$listing['agent_phone'])).'">'.$e($listing['agent_phone']).'</a>';
  $html.='</div>';
 }
 $html.='<form method="post" action="'.Url::to('/emlak/talep').'"><input type="hidden" name="listing_id" value="'.(int)$listing['id'].'"><label>Adınız<input name="name" maxlength="160" required></label><label>E-posta<input name="email" type="email" maxlength="190" required></label><label>Telefon<input name="phone" maxlength="60"></label><label>Mesajınız<textarea name="message" rows="4" maxlength="5000" required>Bu ilan hakkında bilgi almak istiyorum.</textarea></label><label class="re-honey">Web sitesi<input name="website" tabindex="-1" autocomplete="off"></label>'.CoreCMS\Core\Csrf::field().'<button class="re-button">Bilgi talep et</button></form></aside></div></article>';
 return $html.$shell->footer();
};
