<?php
declare(strict_types=1);

use CoreCMS\Core\Csrf;
use CoreCMS\Core\Url;
use CoreCMS\Modules\RealEstate\RealEstateManager;
use CoreCMS\Modules\RealEstate\RealEstateSiteShell;

require_once dirname(__DIR__).'/Admin/RealEstateManager.php';
require_once __DIR__.'/RealEstateSiteShell.php';

return static function (PDO $db,array $params,array $request,array $module): string {
 CoreCMS\Core\Auth::startSession();
 $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
 $base=Url::to('/emlak');
 if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){header('Location: '.$base);return '';}
 Csrf::requireValidPost();
 $manager=new RealEstateManager($db);
 try{
  if(trim((string)($_POST['website']??''))==='')$manager->createLead($_POST,(string)($_SERVER['REMOTE_ADDR']??''));
  $_SESSION['re_lead_feedback']='Talebiniz alındı. En kısa sürede sizinle iletişime geçeceğiz.';
 }catch(Throwable $e){$_SESSION['re_lead_feedback']='Talebiniz şu anda alınamadı. Lütfen bilgilerinizi kontrol edip tekrar deneyin.';}
 $listingId=(int)($_POST['listing_id']??0);$listing=$listingId>0?$manager->findListing($listingId):null;
 $back=$listing&&$listing['status']==='published'?$base.'/ilan/'.rawurlencode($listing['slug']):$base;
 $message=(string)$_SESSION['re_lead_feedback'];unset($_SESSION['re_lead_feedback']);
 $shell=new RealEstateSiteShell($db);
 return $shell->header('Talebiniz alındı',$message,$base).'<section class="re-empty"><h1>'.$e($message).'</h1><a class="re-button" href="'.$e($back).'">Portföye dön</a></section>'.$shell->footer();
};
