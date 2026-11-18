<?php
declare(strict_types=1);

use CoreCMS\Core\Url;
use CoreCMS\Modules\RealEstate\RealEstateManager;

require_once dirname(__DIR__).'/Admin/RealEstateManager.php';

return static function (PDO $db,array $params,array $request,array $module): string {
 require_once dirname(__DIR__,3).'/app/Core/Csrf.php';
 CoreCMS\Core\Auth::startSession();
 $manager=new RealEstateManager($db);
 $listing=$manager->findListing((string)($params['slug']??''));
 if(!$listing||$listing['status']!=='published'){http_response_code(404);return '<h1>İlan bulunamadı</h1>';}
 $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
 $images=$listing['gallery'];if($listing['cover_image'])array_unshift($images,$listing['cover_image']);
 $html='<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$e($listing['seo_title']?:$listing['title']).'</title><meta name="description" content="'.$e($listing['seo_description']?:substr(strip_tags($listing['description']),0,155)).'"><link rel="stylesheet" href="'.Url::to('/modules/real-estate/assets/css/real-estate.css').'"></head><body class="re-site"><header class="re-header"><a class="re-brand" href="'.Url::to('/emlak').'">Gayrimenkul <span>Portföyü</span></a><a href="'.Url::to('/emlak').'">← Portföye dön</a></header><main class="re-container"><article class="re-detail">';
 if($images){$html.='<div class="re-gallery">';foreach(array_slice(array_unique($images),0,8) as $image)$html.='<img src="'.$e($image).'" alt="'.$e($listing['title']).'">';$html.='</div>';}
 $html.='<h1>'.$e($listing['title']).'</h1><p>'.$e($listing['district']).' / '.$e($listing['city']).'</p><strong class="re-price">'.number_format((float)$listing['price'],0,',','.').' '.$e($listing['currency']).'</strong><div class="re-description">'.nl2br($e($listing['description'])).'</div><aside class="re-contact"><h2>İlan hakkında bilgi alın</h2><form method="post" action="'.Url::to('/emlak/talep').'"><input type="hidden" name="listing_id" value="'.(int)$listing['id'].'"><label>Adınız<input name="name" required maxlength="160"></label><label>E-posta<input name="email" type="email" required maxlength="190"></label><label>Telefon<input name="phone" maxlength="60"></label><label>Mesaj<textarea name="message" required maxlength="5000"></textarea></label><label class="re-honey">Web sitesi<input name="website" tabindex="-1" autocomplete="off"></label>'.CoreCMS\Core\Csrf::field().'<button class="re-button">Bilgi talep et</button></form></aside></article></main></body></html>';
 return $html;
};
