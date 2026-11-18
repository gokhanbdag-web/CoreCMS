<?php
declare(strict_types=1);
use CoreCMS\Core\Csrf;
use CoreCMS\Core\Url;
use CoreCMS\Modules\Tourism\TourismManager;
require_once dirname(__DIR__).'/Admin/TourismManager.php';
return static function(PDO $db,array $params,array $request,array $module):string{
 CoreCMS\Core\Auth::startSession();if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){header('Location: '.Url::to('/turlar'));return '';}Csrf::requireValidPost();
 $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$manager=new TourismManager($db);$message='Rezervasyon talebiniz alındı. Ekibimiz en kısa sürede sizinle iletişime geçecek.';$tour=null;
 try{if(trim((string)($_POST['website']??''))==='')$manager->createBooking($_POST,(string)($_SERVER['REMOTE_ADDR']??''));}
 catch(Throwable $error){$message='Rezervasyon talebiniz alınamadı. Lütfen bilgilerinizi ve seçtiğiniz kalkış tarihini kontrol edin.';}
 $tourId=(int)($_POST['tour_id']??0);$tour=$manager->findTour($tourId);$back=$tour&&$tour['status']==='published'?Url::to('/turlar/'.$tour['slug']):Url::to('/turlar');
 return '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="'.Url::to('/modules/tourism/assets/css/tourism.css').'"><title>Rezervasyon talebi</title></head><body class="tm-site"><main class="tm-container"><section class="tm-empty tm-result"><h1>'.$e($message).'</h1><a class="tm-button" href="'.$e($back).'">Tura geri dön</a></section></main></body></html>';
};
