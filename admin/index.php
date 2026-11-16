<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
CoreCMS\Core\Auth::startSession();
CoreCMS\Core\Csrf::requireValidPost();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    CoreCMS\Core\Auth::logout(); header('Location: ' . CoreCMS\Core\Url::to('/admin/')); exit;
}
if (!CoreCMS\Core\Auth::check()) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'login') {
        if (CoreCMS\Core\Auth::attempt($db,(string)($_POST['email']??''),(string)($_POST['password']??''))) { header('Location: '.CoreCMS\Core\Url::to('/admin/')); exit; }
        $error='E-posta veya parola hatalı.';
    }
    ?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CoreCMS Giriş</title><link rel="stylesheet" href="<?= htmlspecialchars(CoreCMS\Core\Url::asset('css/site.css'),ENT_QUOTES,'UTF-8') ?>"></head><body><main class="install-wrap"><section class="install-card"><h1>Yönetim paneli</h1><?php if(!empty($error)): ?><p class="error"><?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?></p><?php endif ?><form method="post"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="login"><label>E-posta<input type="email" name="email" required></label><label>Parola<input type="password" name="password" required></label><button class="button">Giriş yap</button></form></section></main><script src="<?= htmlspecialchars(CoreCMS\Core\Url::asset('js/responsive-nav.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script></body></html><?php exit;
}
require_once dirname(__DIR__).'/app/Core/ContentManager.php';
require_once dirname(__DIR__).'/app/Core/ThemeManager.php';
require_once dirname(__DIR__).'/app/Core/SiteSettings.php';
require_once dirname(__DIR__).'/app/Core/MediaLibrary.php';
require_once dirname(__DIR__).'/app/Core/PageRevisionManager.php';
require_once dirname(__DIR__).'/app/Core/PageAutosaveManager.php';
require_once dirname(__DIR__).'/app/Core/RedirectManager.php';
require_once dirname(__DIR__).'/app/Core/SeoAnalyzer.php';
require_once dirname(__DIR__).'/app/Core/SeoBulkEditor.php';
$modules=new CoreCMS\Core\ModuleManager($db,dirname(__DIR__).'/modules');
$content=new CoreCMS\Core\ContentManager($db);
$themeManager=new CoreCMS\Core\ThemeManager($db,dirname(__DIR__).'/modules');
$siteSettings=new CoreCMS\Core\SiteSettings($db);
$mediaLibrary=new CoreCMS\Core\MediaLibrary($db,dirname(__DIR__),CoreCMS\Core\Url::basePath());
$revisions=new CoreCMS\Core\PageRevisionManager($db);
$autosaves=new CoreCMS\Core\PageAutosaveManager($db);
$redirects=new CoreCMS\Core\RedirectManager($db);
$migrator=new CoreCMS\Core\Migrator($db,dirname(__DIR__).'/database/migrations');
$pending=$migrator->pending(); $notice=$error=null; $action=(string)($_POST['action']??'');
$roleManager=new CoreCMS\\Core\\RoleManager($db);
$role=(string)($_SESSION['corecms_user_role']??'');
$actionPermissions=['media_upload'=>'media.manage','media_delete'=>'media.manage','site_settings_save'=>'settings.manage','theme_save'=>'appearance.manage','page_save'=>'pages.manage','page_revision_restore'=>'pages.manage','page_detach'=>'pages.manage','page_delete'=>'pages.manage','seo_bulk_update'=>'seo.manage','redirect_save'=>'seo.manage','redirect_delete'=>'seo.manage','menu_save'=>'menus.manage','menu_detach'=>'menus.manage','menu_delete'=>'menus.manage','install'=>'modules.manage','enable'=>'modules.manage','disable'=>'modules.manage'];
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST' && isset($actionPermissions[$action]) && !$roleManager->can($role,$actionPermissions[$action])) { http_response_code(403); exit('Bu işlem için yetkiniz bulunmuyor.'); }
if($action==='run_core_migrations' && !in_array($role,['admin','super_admin'],true)) { http_response_code(403); exit('Veritabanı güncellemelerini yalnızca yöneticiler uygulayabilir.'); }
try {
 if(($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
  if($action==='media_upload') { if($pending) throw new RuntimeException('Önce bekleyen veritabanı migrasyonlarını uygulayın.'); $mediaLibrary->upload($_FILES['media_file']??[],(int)($_SESSION['corecms_user_id']??0),(string)($_POST['alt_text']??'')); header('Location: '.CoreCMS\Core\Url::to('/admin/?section=media&uploaded=1')); exit; }
  if($action==='media_delete') { if($pending) throw new RuntimeException('Önce bekleyen veritabanı migrasyonlarını uygulayın.'); $mediaLibrary->delete((int)($_POST['media_id']??0)); header('Location: '.CoreCMS\Core\Url::to('/admin/?section=media&deleted=1')); exit; }
  if($action==='site_settings_save') { $siteSettings->save($_POST); header('Location: '.CoreCMS\Core\Url::to('/admin/?section=settings&saved=1')); exit; }
  if($action==='theme_save') { $themeManager->activate((string)($_POST['theme_module']??'')); header('Location: '.CoreCMS\Core\Url::to('/admin/?section=appearance&saved=1')); exit; }
  if($action==='page_save') { $id=(int)($_POST['id']??0); if($pending) throw new RuntimeException('Sayfa revizyonları için bekleyen veritabanı migrasyonlarını önce uygulayın.'); if($id>0) $revisions->snapshot($id,(int)($_SESSION['corecms_user_id']??0)); $content->savePage($id?:null,(string)($_POST['title']??''),(string)($_POST['slug']??''),(string)($_POST['body']??''),(string)($_POST['status']??'draft'),(string)($_POST['seo_title']??''),(string)($_POST['seo_description']??''),(string)($_POST['canonical_url']??''),isset($_POST['scheduled_at'])?(string)$_POST['scheduled_at']:null,((int)($_POST['social_image_media_id']??0)>0?(int)$_POST['social_image_media_id']:null)); if($id>0)$autosaves->clear($id,(int)($_SESSION['corecms_user_id']??0)); header('Location: '.CoreCMS\Core\Url::to('/admin/?section=pages&saved=1')); exit; }
  if($action==='page_revision_restore') { if($pending) throw new RuntimeException('Önce bekleyen veritabanı migrasyonlarını uygulayın.'); $restoredId=$revisions->restore((int)($_POST['revision_id']??0),(int)($_SESSION['corecms_user_id']??0)); $autosaves->clear($restoredId,(int)($_SESSION['corecms_user_id']??0)); header('Location: '.CoreCMS\Core\Url::to('/admin/?section=pages&edit_page='.$restoredId.'&restored=1')); exit; }
  if($action==='page_detach') { $content->detachPage((int)($_POST['id']??0)); header('Location: '.CoreCMS\Core\Url::to('/admin/?section=pages&detached=1')); exit; }
  if($action==='page_delete') { $content->deletePage((int)($_POST['id']??0)); header('Location: '.CoreCMS\Core\Url::to('/admin/?section=pages&deleted=1')); exit; }
  if($action==='seo_bulk_update') { if($pending) throw new RuntimeException('Önce bekleyen veritabanı migrasyonlarını uygulayın.'); $bulkEditor=new CoreCMS\Core\SeoBulkEditor($db,$revisions,$autosaves); $updated=$bulkEditor->apply((array)($_POST['page_ids']??[]),(string)($_POST['title_pattern']??''),(string)($_POST['description_pattern']??''),(string)($_POST['mode']??'fill_missing'),(string)($settings['site_name']??''),(int)($_SESSION['corecms_user_id']??0)); header('Location: '.CoreCMS\Core\Url::to('/admin/?section=seo&bulk_updated='.$updated)); exit; }
  if($action==='redirect_save') { if($pending) throw new RuntimeException('Önce bekleyen veritabanı migrasyonlarını uygulayın.'); $redirects->save(((int)($_POST['id']??0)>0?(int)$_POST['id']:null),(string)($_POST['source_path']??''),(string)($_POST['target_path']??''),(int)($_POST['http_code']??301)); header('Location: '.CoreCMS\Core\Url::to('/admin/?section=redirects&saved=1')); exit; }
  if($action==='redirect_delete') { if($pending) throw new RuntimeException('Önce bekleyen veritabanı migrasyonlarını uygulayın.'); $redirects->delete((int)($_POST['id']??0)); header('Location: '.CoreCMS\Core\Url::to('/admin/?section=redirects&deleted=1')); exit; }
  if($action==='menu_save') { $id=(int)($_POST['id']??0); $content->saveMenu($id?:null,(string)($_POST['menu_key']??'main'),(string)($_POST['label']??''),(string)($_POST['url']??''),(int)($_POST['sort_order']??0)); header('Location: '.CoreCMS\Core\Url::to('/admin/?section=menus&saved=1')); exit; }
  if($action==='menu_detach') { $content->detachMenu((int)($_POST['id']??0)); header('Location: '.CoreCMS\Core\Url::to('/admin/?section=menus&detached=1')); exit; }
  if($action==='menu_delete') { $content->deleteMenu((int)($_POST['id']??0)); header('Location: '.CoreCMS\Core\Url::to('/admin/?section=menus&deleted=1')); exit; }
  if($action==='run_core_migrations') { $notice=count($migrator->migrate()).' çekirdek migrasyonu uygulandı.'; $pending=$migrator->pending(); }
  if(isset($_POST['module_id'])) { $id=(string)$_POST['module_id']; if($action==='install'){$modules->install($id);$notice='Modül kuruldu veya güncellendi.';} elseif($action==='enable'){$modules->enable($id);(new CoreCMS\Core\ModuleRuntime($db,dirname(__DIR__).'/modules'))->syncContent($id);$notice='Modül etkinleştirildi ve içeriği eşitlendi.';} elseif($action==='disable'){$modules->disable($id);$notice='Modül devre dışı bırakıldı.';} }
 }
} catch(Throwable $e){$error=$e->getMessage();}
$section=(string)($_GET['section']??'overview'); if(!in_array($section,['overview','pages','menus','media','modules','appearance','settings','seo','redirects'],true))$section='overview';
$sectionPermissions=['overview'=>'dashboard.view','pages'=>'pages.manage','menus'=>'menus.manage','media'=>'media.manage','modules'=>'modules.manage','appearance'=>'appearance.manage','settings'=>'settings.manage','seo'=>'seo.manage','redirects'=>'seo.manage'];
if(!$roleManager->can($role,$sectionPermissions[$section]??'dashboard.view')) { http_response_code(403); exit('Bu bölümü görüntülemek için yetkiniz bulunmuyor.'); }
if(isset($_GET['restored']))$notice='Sayfa revizyonu geri yüklendi; önceki sürüm de geçmişe kaydedildi.'; if(isset($_GET['uploaded']))$notice='Görsel kütüphaneye eklendi.'; if(isset($_GET['saved']))$notice='Değişiklikler kaydedildi.'; if(isset($_GET['deleted']))$notice='Kayıt silindi.'; if(isset($_GET['detached']))$notice='İçerik modülden ayrıldı ve artık bağımsız yönetiliyor.';
$h=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$pageRows=$db->query('SELECT id,title,slug,status,source_module FROM core_pages ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
$redirectRows=$pending?[]:$redirects->all();
$redirectEdit=isset($_GET['edit_redirect'])&&!$pending?$redirects->find((int)$_GET['edit_redirect']):null;
$seoAudit=$pending?['pages'=>[],'broken_links'=>[]]:CoreCMS\Core\SeoAnalyzer::audit($db);
$seoMediaAlt=[]; if(!$pending){foreach($db->query('SELECT id,alt_text FROM core_media')->fetchAll(PDO::FETCH_ASSOC) as $seoMedia)$seoMediaAlt[(int)$seoMedia['id']]=(string)$seoMedia['alt_text'];}
$seoScores=[]; foreach($seoAudit['pages'] as $seoPage)$seoScores[(int)$seoPage['id']]=CoreCMS\Core\SeoAnalyzer::analyze($seoPage,$seoMediaAlt);
$menuRows=$db->query('SELECT id,menu_key,label,url,sort_order,source_module FROM core_menus ORDER BY menu_key,sort_order,id')->fetchAll(PDO::FETCH_ASSOC);
$mediaRows=$pending?[]:$mediaLibrary->list((string)($_GET['q']??''));
$pageMediaRows=$pending?[]:$mediaLibrary->list('',100);
$ep=$em=null; $pageAutosave=null;
if(isset($_GET['edit_page'])){$q=$db->prepare('SELECT * FROM core_pages WHERE id=?');$q->execute([(int)$_GET['edit_page']]);$ep=$q->fetch(PDO::FETCH_ASSOC)?:null; if($ep && !$pending)$pageAutosave=$autosaves->get((int)$ep['id'],(int)($_SESSION['corecms_user_id']??0));}
if(isset($_GET['edit_menu'])){$q=$db->prepare('SELECT * FROM core_menus WHERE id=?');$q->execute([(int)$_GET['edit_menu']]);$em=$q->fetch(PDO::FETCH_ASSOC)?:null;}
$pageRevisions=[]; $compareRevision=null;
if($ep && !$pending){$pageRevisions=$revisions->history((int)$ep['id']);$compareId=(int)($_GET['compare_revision']??0);if($compareId>0)$compareRevision=$revisions->find($compareId,(int)$ep['id']);}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CoreCMS Yönetim</title><link rel="stylesheet" href="<?= $h(CoreCMS\Core\Url::asset('css/site.css')) ?>"><link rel="stylesheet" href="<?= $h(CoreCMS\Core\Url::asset('css/responsive-nav.css')) ?>?v=3"></head><body class="admin-body">
<header class="site-header"><a class="brand" href="<?= $h(CoreCMS\Core\Url::to('/')) ?>">CoreCMS</a><nav><a href="<?= $h(CoreCMS\Core\Url::to('/')) ?>">Siteyi görüntüle</a><form method="post" class="inline-form"><?= CoreCMS\Core\Csrf::field() ?><button name="action" value="logout" class="link-button">Çıkış</button></form></nav></header>
<main class="container admin"><h1>Kontrol paneli</h1><p>İçerik, menüler ve sektör modülleri.</p><nav class="admin-nav"><?php foreach(['overview'=>'Genel bakış','pages'=>'Sayfalar','menus'=>'Menüler','media'=>'Medya kütüphanesi','seo'=>'SEO analizi','redirects'=>'Yönlendirmeler','modules'=>'Modüller','appearance'=>'Görünüm','settings'=>'Site ayarları'] as $k=>$v): ?><a href="<?= $h(CoreCMS\Core\Url::to('/admin/'.($k==='overview'?'':'?section='.$k))) ?>"><?= $h($v) ?></a><?php endforeach ?></nav>
<?php if($roleManager->can($role,'users.manage')): ?><p><a href="<?= $h(CoreCMS\\Core\\Url::to('/admin/roles.php')) ?>">Kullanıcılar ve roller</a></p><?php endif ?>
<?php if($notice): ?><p class="success"><?= $h($notice) ?></p><?php endif ?><?php if($error): ?><p class="error"><?= $h($error) ?></p><?php endif ?>
<?php if($section==='overview'||$section==='modules'): ?>
<section class="content-admin"><h2>Çekirdek güncellemeleri</h2><?php if($pending): ?><p><?= count($pending) ?> bekleyen migrasyon.</p><form method="post"><?= CoreCMS\Core\Csrf::field() ?><button class="button" name="action" value="run_core_migrations">Migrasyonları uygula</button></form><?php else: ?><p>Veritabanı güncel.</p><?php endif ?></section>
<section class="module-grid"><h2>Modül yöneticisi</h2><?php foreach($modules->discover() as $mod): $st=$modules->status($mod['id']); ?><article class="module-card"><h3><?= $h($mod['name']) ?></h3><p><?= $h($mod['description']??'') ?></p><small><?= $st['enabled']?'Etkin':($st['installed']?'Kurulu / pasif':'Kurulmamış') ?></small><form method="post"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="module_id" value="<?= $h($mod['id']) ?>"><?php if(!$st['installed']||$st['installed_version']!==$mod['version']): ?><button class="button secondary" name="action" value="install">Kur / Güncelle</button><?php elseif($st['enabled']): ?><button class="button secondary" name="action" value="disable">Devre dışı bırak</button><?php else: ?><button class="button secondary" name="action" value="enable">Etkinleştir</button><?php endif ?></form></article><?php endforeach ?></section>
<?php endif ?>

<?php if($section==='seo'): ?>
<section class="content-admin"><h2>Sayfa SEO denetimi</h2><p>Başlık, açıklama, içerik uzunluğu, ara başlıklar, görsel alt metinleri ve site içi Markdown bağlantıları analiz edilir. Harici bağlantıların canlı durumu sorgulanmaz.</p>
<?php if(isset($_GET['bulk_updated'])): ?><p class="success"><?= (int)$_GET['bulk_updated'] ?> sayfanın SEO alanları güncellendi.</p><?php endif ?>
<form method="post" class="editor-form"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="seo_bulk_update">
<h3>Toplu SEO düzenleme</h3><p>Örnek yer tutucular: <code>{title}</code>, <code>{slug}</code>, <code>{site_name}</code>. Boş şablon alanı değiştirilmez.</p>
<label>SEO başlığı şablonu<input name="title_pattern" maxlength="255" placeholder="{title} | {site_name}"></label>
<label>Meta açıklama şablonu<textarea name="description_pattern" rows="2" maxlength="320" placeholder="{title} hakkında detaylı bilgi. {site_name}"></textarea></label>
<label>Uygulama şekli<select name="mode"><option value="fill_missing">Yalnızca boş SEO alanlarını doldur (güvenli)</option><option value="overwrite">Seçilen sayfalardaki SEO alanlarını değiştir</option></select></label>
<div class="table-wrap"><table><thead><tr><th>Seç</th><th>Sayfa</th><th>SEO puanı</th><th>Kelime</th><th>Görsel</th><th>İyileştirme önerileri</th></tr></thead><tbody><?php foreach($seoAudit['pages'] as $seoPage): $seoResult=$seoScores[(int)$seoPage['id']]; ?><tr><td><input type="checkbox" name="page_ids[]" value="<?= (int)$seoPage['id'] ?>" aria-label="<?= $h($seoPage['title']) ?> sayfasını seç"></td><td><?= $h($seoPage['title']) ?> · <a href="<?= $h(CoreCMS\Core\Url::to('/admin/?section=pages&edit_page='.(int)$seoPage['id'])) ?>">Düzenle</a></td><td><strong><?= (int)$seoResult['score'] ?>/100</strong></td><td><?= (int)$seoResult['word_count'] ?></td><td><?= (int)$seoResult['image_count'] ?></td><td><?php if($seoResult['issues']): ?><ul><?php foreach($seoResult['issues'] as $issue): ?><li><?= $h($issue) ?></li><?php endforeach ?></ul><?php else: ?>Temel kontroller tamam<?php endif ?></td></tr><?php endforeach ?></tbody></table></div>
<?php if(!$seoAudit['pages']): ?><p>Henüz yayınlanmış sayfa yok.</p><?php endif ?><button class="button">Seçilen sayfalara uygula</button></form></section>
<section class="content-admin"><h2>Bozuk site içi bağlantılar</h2><?php if($seoAudit['broken_links']): ?><div class="table-wrap"><table><thead><tr><th>Kaynak sayfa</th><th>Hedef</th></tr></thead><tbody><?php foreach($seoAudit['broken_links'] as $broken): ?><tr><td><?= $h($broken['page_title']) ?></td><td><code><?= $h($broken['target']) ?></code></td></tr><?php endforeach ?></tbody></table></div><?php else: ?><p>Kontrol edilen sayfalarda bozuk site içi Markdown bağlantısı bulunamadı.</p><?php endif ?></section>
<?php endif ?>
<?php if($section==='redirects'): ?>
<section class="content-admin"><h2><?= $redirectEdit?'Yönlendirmeyi düzenle':'Yeni yönlendirme' ?></h2><p>Yalnızca site içi yollar kabul edilir. Yönlendirme zincirleri ve döngüler engellenir.</p><form method="post" class="editor-form"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="redirect_save"><input type="hidden" name="id" value="<?= $h($redirectEdit['id']??'') ?>"><label>Eski adres<input name="source_path" required maxlength="500" value="<?= $h($redirectEdit['source_path']??'') ?>" placeholder="/eski-sayfa"></label><label>Yeni hedef adres<input name="target_path" required maxlength="500" value="<?= $h($redirectEdit['target_path']??'') ?>" placeholder="/yeni-sayfa"></label><label>HTTP kodu<select name="http_code"><option value="301" <?= (int)($redirectEdit['http_code']??301)===301?'selected':'' ?>>301 · Kalıcı</option><option value="302" <?= (int)($redirectEdit['http_code']??301)===302?'selected':'' ?>>302 · Geçici</option></select></label><button class="button">Yönlendirmeyi kaydet</button></form></section>
<section class="content-admin"><h2>Yönlendirme kayıtları</h2><?php if($redirectRows): ?><div class="table-wrap"><table><thead><tr><th>Eski adres</th><th>Yeni adres</th><th>Kod</th><th>İşlem</th></tr></thead><tbody><?php foreach($redirectRows as $redirect): ?><tr><td><code><?= $h($redirect['source_path']) ?></code></td><td><code><?= $h($redirect['target_path']) ?></code></td><td><?= (int)$redirect['http_code'] ?></td><td><a href="<?= $h(CoreCMS\Core\Url::to('/admin/?section=redirects&edit_redirect='.(int)$redirect['id'])) ?>">Düzenle</a> <form method="post" class="inline-form" onsubmit="return confirm('Yönlendirme silinsin mi?')"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="redirect_delete"><input type="hidden" name="id" value="<?= $h($redirect['id']) ?>"><button class="link-button danger">Sil</button></form></td></tr><?php endforeach ?></tbody></table></div><?php else: ?><p>Henüz yönlendirme kaydı yok.</p><?php endif ?></section>
<?php endif ?>

<?php if($section==='settings'): $currentSettings=$siteSettings->all(); ?>
<section class="content-admin"><h2>Genel site ayarları</h2><form method="post" class="editor-form"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="site_settings_save"><label>Site adı<input name="site_name" maxlength="120" required value="<?= $h($currentSettings['site_name']) ?>"></label><label>Site dili<input name="site_language" maxlength="12" required value="<?= $h($currentSettings['site_language']) ?>" placeholder="tr"></label><label>Saat dilimi<input name="timezone" maxlength="64" required value="<?= $h($currentSettings['timezone']) ?>" placeholder="Europe/Istanbul"></label><h2>Ana sayfa hero alanı</h2><label>Üst küçük başlık<input name="home_eyebrow" maxlength="100" value="<?= $h($currentSettings['home_eyebrow']) ?>"></label><label>Ana başlık<input name="home_title" maxlength="120" required value="<?= $h($currentSettings['home_title']) ?>"></label><label>Vurgulu ikinci başlık<input name="home_accent_title" maxlength="120" value="<?= $h($currentSettings['home_accent_title']) ?>"></label><label>Açıklama<textarea name="home_description" rows="5" maxlength="600"><?= $h($currentSettings['home_description']) ?></textarea></label><h2>Teknik SEO ve arama motorları</h2><label>Site URL (alan adınız ve varsa alt dizin)<input type="url" name="site_url" maxlength="500" value="<?= $h($currentSettings['site_url']) ?>" placeholder="https://example.com"></label><small>Sitemap ve canonical adresleri bu güvenilir URL üzerinden oluşturulur; HTTP Host başlığı kullanılmaz.</small><label>Kuruluş / marka adı<input name="seo_org_name" maxlength="160" value="<?= $h($currentSettings['seo_org_name']) ?>"></label><label>Kuruluş logosu (mutlak URL)<input type="url" name="seo_logo_url" maxlength="1000" value="<?= $h($currentSettings['seo_logo_url']) ?>" placeholder="https://example.com/logo.png"></label><label>Sosyal paylaşım varsayılan görseli (mutlak URL)<input type="url" name="seo_social_image" maxlength="1000" value="<?= $h($currentSettings['seo_social_image']) ?>" placeholder="https://example.com/uploads/social.jpg"></label><label>Google Search Console doğrulama kodu<input name="search_console_verification" maxlength="200" value="<?= $h($currentSettings['search_console_verification']) ?>" placeholder="Meta etiketindeki content değeri"></label><p><a href="<?= $h(CoreCMS\Core\Url::to('/sitemap.xml')) ?>" target="_blank" rel="noopener">XML sitemap</a> · <a href="<?= $h(CoreCMS\Core\Url::to('/robots.txt')) ?>" target="_blank" rel="noopener">robots.txt</a></p><button class="button">Ayarları kaydet</button></form></section>
<?php endif ?>
<?php if($section==='appearance'): $availableThemes=$themeManager->available(); $activeThemeId=$themeManager->selectedId(); ?>
<section class="content-admin"><h2>Aktif tema</h2><p>Yalnızca etkin modüllerde bulunan ve güvenli dizin içinde geçerli bir <code>layout.php</code> dosyası olan temalar seçilebilir.</p><form method="post" class="editor-form"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="theme_save"><label>Tema seçimi<select name="theme_module"><option value="" <?= $activeThemeId===''?'selected':'' ?>>Otomatik (ilk uygun etkin tema)</option><?php foreach($availableThemes as $theme): ?><option value="<?= $h($theme['module_id']) ?>" <?= $activeThemeId===$theme['module_id']?'selected':'' ?>><?= $h($theme['name']) ?> · <?= $h($theme['module_id']) ?></option><?php endforeach ?></select></label><button class="button">Temayı kaydet</button></form><?php if(!$availableThemes): ?><p>Şu anda seçilebilir tema yok. Tema sağlayan bir modülü kurup etkinleştirin.</p><?php endif ?></section>
<?php endif ?>
<?php if($section==='media' && $pending): ?>
<section class="content-admin"><h2>Medya kütüphanesi için veritabanı güncellemesi gerekli</h2><p><?= count($pending) ?> bekleyen migrasyon var. Mevcut veriler korunarak güncellemeyi uygulayın.</p><form method="post"><?= CoreCMS\Core\Csrf::field() ?><button class="button" name="action" value="run_core_migrations">Migrasyonları uygula</button></form></section>
<?php elseif($section==='media'): ?>
<section class="content-admin"><h2>Görsel yükle</h2><p>JPEG, PNG, GIF veya WebP · En fazla 8 MB. Dosyalar rastgele adlandırılır ve çalıştırılabilir dosyalar kabul edilmez.</p><form method="post" enctype="multipart/form-data" class="editor-form"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="media_upload"><label>Görsel dosyası<input type="file" name="media_file" accept="image/jpeg,image/png,image/gif,image/webp" required></label><label>Alternatif metin (erişilebilirlik)<input name="alt_text" maxlength="255" placeholder="Görselin kısa açıklaması"></label><button class="button">Yükle</button></form></section>
<section class="content-admin"><h2>Medya kütüphanesi</h2><form method="get" class="inline-form"><input type="hidden" name="section" value="media"><input name="q" value="<?= $h($_GET['q']??'') ?>" placeholder="Dosya veya alt metin ara"><button class="button secondary">Ara</button></form><div class="module-grid"><?php foreach($mediaRows as $mediaItem): $mediaUrl=$mediaLibrary->url((string)$mediaItem['file_path']); ?><article class="module-card"><a href="<?= $h($mediaUrl) ?>" target="_blank" rel="noopener"><img src="<?= $h($mediaUrl) ?>" alt="<?= $h($mediaItem['alt_text']) ?>" loading="lazy" style="width:100%;height:170px;object-fit:cover;border-radius:8px"></a><h3><?= $h($mediaItem['file_name']) ?></h3><p><?= $h($mediaItem['width'].' × '.$mediaItem['height']) ?> · <?= $h(number_format(((int)$mediaItem['file_size'])/1024,1,',','.')) ?> KB</p><label>Görsel URL<input readonly value="<?= $h($mediaUrl) ?>" onclick="this.select()"></label><form method="post" onsubmit="return confirm('Bu görsel ve dosyası silinsin mi?')"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="media_delete"><input type="hidden" name="media_id" value="<?= $h($mediaItem['id']) ?>"><button class="link-button danger">Görseli sil</button></form></article><?php endforeach ?></div><?php if(!$mediaRows): ?><p>Henüz görsel yok. İlk görseli yukarıdan yükleyebilirsiniz.</p><?php endif ?></section>
<?php endif ?>
<?php if($section==='pages'): ?>
<section class="content-admin"><h2><?= $ep?'Sayfayı düzenle':'Yeni sayfa' ?></h2><form method="post" class="editor-form"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="page_save"><input type="hidden" name="id" value="<?= $h($ep['id']??'') ?>"><label>Başlık<input name="title" required maxlength="255" value="<?= $h($ep['title']??'') ?>"></label><label>URL kısa adı<input name="slug" value="<?= $h($ep['slug']??'') ?>" placeholder="hakkimizda"></label><label>İçerik</label><div class="rich-toolbar" role="toolbar" aria-label="Metin biçimlendirme"><button type="button" data-prefix="**" data-suffix="**">Kalın</button><button type="button" data-prefix="*" data-suffix="*">İtalik</button><button type="button" data-prefix="## " data-suffix="" data-line="1">Başlık</button><button type="button" data-prefix="- " data-suffix="" data-line="1">Liste öğesi</button><button type="button" data-prefix="> " data-suffix="" data-line="1">Alıntı</button><button type="button" data-prefix="&#96;" data-suffix="&#96;">Kod</button><button type="button" id="insert-page-link">Bağlantı</button></div><textarea id="page-body" name="body" rows="14" aria-label="Sayfa içeriği"><?= $h(htmlspecialchars_decode((string)($ep['body']??''),ENT_QUOTES)) ?></textarea><small class="editor-help">Biçimlendirme işaretleri ön yüzde güvenli HTML olarak gösterilir. Boş satır yeni paragraf oluşturur.</small><?php if($pageMediaRows): ?><div class="media-picker"><label>Medya kütüphanesinden görsel seç<select id="page-media-select"><option value="">Görsel seçin</option><?php foreach($pageMediaRows as $pickerItem): ?><option value="<?= $h($pickerItem['id']) ?>"><?= $h($pickerItem['alt_text']?:$pickerItem['file_name']) ?></option><?php endforeach ?></select></label><div class="media-options"><label>Genişlik<select id="page-media-width"><option value="100">Tam genişlik</option><option value="75">%75</option><option value="50">%50</option><option value="25">%25</option></select></label><label>Hizalama<select id="page-media-align"><option value="center">Ortala</option><option value="left">Sola</option><option value="right">Sağa</option></select></label></div><button type="button" class="button secondary" id="insert-page-media">Seçili görseli ekle</button><small>Görselleri aşağıdaki kartlardan içerik alanına sürükleyebilir veya seçip ekleyebilirsiniz.</small><div class="media-drag-grid"><?php foreach($pageMediaRows as $dragMedia): $dragUrl=$mediaLibrary->url((string)$dragMedia['file_path']); ?><button type="button" class="media-drag-item" draggable="true" data-media-id="<?= $h($dragMedia['id']) ?>" aria-label="<?= $h(($dragMedia['alt_text']?:$dragMedia['file_name']).' görselini editöre ekle') ?>"><img src="<?= $h($dragUrl) ?>" alt="" loading="lazy"><span><?= $h($dragMedia['alt_text']?:$dragMedia['file_name']) ?></span><small>Sürükle veya dokun</small></button><?php endforeach ?></div></div><?php endif ?><label>Durum<select name="status"><option value="draft" <?= ($ep['status']??'draft')==='draft'?'selected':'' ?>>Taslak</option><option value="published" <?= ($ep['status']??'')==='published'?'selected':'' ?>>Yayında</option><option value="scheduled" <?= ($ep['status']??'')==='scheduled'?'selected':'' ?>>Planlandı</option></select></label><label>Yayın tarihi ve saati<input type="datetime-local" name="scheduled_at" value="<?= $h(!empty($ep['scheduled_at'])?date('Y-m-d\\TH:i',strtotime($ep['scheduled_at'])):'') ?>"><small>Planlandı seçildiğinde bu tarih esas alınır.</small></label><h3>SEO ayarları</h3><label>SEO başlığı<input name="seo_title" maxlength="255" value="<?= $h($ep['seo_title']??'') ?>" placeholder="Boşsa sayfa başlığı kullanılır"></label><label>Meta açıklama<textarea name="seo_description" rows="3" maxlength="320"><?= $h($ep['seo_description']??'') ?></textarea></label><label>Canonical URL<input name="canonical_url" maxlength="500" value="<?= $h($ep['canonical_url']??'') ?>" placeholder="https://... veya /sayfa"></label><label>Sayfaya özel sosyal paylaşım görseli<select name="social_image_media_id"><option value="0">Varsayılan site görselini kullan</option><?php foreach($pageMediaRows as $socialMedia): ?><option value="<?= $h($socialMedia['id']) ?>" <?= (int)($ep['social_image_media_id']??0)===(int)$socialMedia['id']?'selected':'' ?>><?= $h($socialMedia['alt_text']?:$socialMedia['file_name']) ?></option><?php endforeach ?></select><small>Seçilen görsel sosyal medya önizlemelerinde kullanılır.</small></label><div class="media-options"><button class="button" type="submit">Kaydet</button><button class="button secondary" type="button" id="page-preview-button">Önizlemeyi aç</button><small id="autosave-status" aria-live="polite"></small></div></form><?php if($pageAutosave): ?><section class="content-admin"><p>Kaydedilmemiş otomatik taslak bulundu: <?= $h($pageAutosave['saved_at']) ?></p><button type="button" class="button secondary" id="restore-autosave">Otomatik taslağı geri yükle</button></section><?php endif ?></section><?php if($ep && !$pending): ?><section class="content-admin"><h2>Revizyon geçmişi</h2><?php if($compareRevision): ?><h3>Revizyon karşılaştırması</h3><div class="media-options"><div><strong>Seçilen eski sürüm · <?= $h($compareRevision['created_at']) ?></strong><pre class="revision-preview"><?= $h(html_entity_decode((string)($compareRevision['body']??''),ENT_QUOTES|ENT_HTML5,'UTF-8')) ?></pre></div><div><strong>Mevcut kayıtlı sürüm</strong><pre class="revision-preview"><?= $h(html_entity_decode((string)($ep['body']??''),ENT_QUOTES|ENT_HTML5,'UTF-8')) ?></pre></div></div><?php endif ?><?php if(!$pageRevisions): ?><p>Henüz kayıtlı revizyon yok. İlk değişiklikten sonra önceki sürümler burada listelenir.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Tarih</th><th>Başlık</th><th>Durum</th><th>İşlem</th></tr></thead><tbody><?php foreach($pageRevisions as $revision): ?><tr><td><?= $h($revision['created_at']) ?></td><td><?= $h($revision['title']) ?></td><td><?= $h($revision['status']) ?></td><td><a href="<?= $h(CoreCMS\Core\Url::to('/admin/?section=pages&edit_page='.(int)$ep['id'].'&compare_revision='.(int)$revision['id'])) ?>">Karşılaştır</a> · <form method="post" class="inline-form" onsubmit="return confirm('Bu sürüm geri yüklensin mi? Mevcut içerik de geçmişe kaydedilecek.')"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="page_revision_restore"><input type="hidden" name="revision_id" value="<?= $h($revision['id']) ?>"><button class="link-button">Bu sürümü geri yükle</button></form></td></tr><?php endforeach ?></tbody></table></div><?php endif ?></section><?php endif ?>
<section class="content-admin"><h2>Sayfalar</h2><div class="table-wrap"><table><thead><tr><th>Başlık</th><th>URL</th><th>Durum</th><th>Kaynak</th><th></th></tr></thead><tbody><?php foreach($pageRows as $p): ?><tr><td><?= $h($p['title']) ?></td><td>/<?= $h($p['slug']) ?></td><td><?= $h($p['status']) ?></td><td><?= $h($p['source_module']?:'Yönetici') ?></td><td><a href="<?= $h(CoreCMS\Core\Url::to('/admin/?section=pages&edit_page='.$p['id'])) ?>">Düzenle</a><?php if(!$p['source_module']): ?><form method="post" class="inline-form"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="page_delete"><input type="hidden" name="id" value="<?= $h($p['id']) ?>"><button class="link-button danger" onclick="return confirm('Sayfa silinsin mi?')">Sil</button></form><?php else: ?><form method="post" class="inline-form"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="page_detach"><input type="hidden" name="id" value="<?= $h($p['id']) ?>"><button class="link-button" onclick="return confirm('Sayfa modülden ayrılıp bağımsız hale getirilsin mi?')">Modülden ayır</button></form><?php endif ?></td></tr><?php endforeach ?></tbody></table></div></section>
<?php endif ?>
<?php if($section==='menus'): ?>
<section class="content-admin"><h2><?= $em?'Menü öğesini düzenle':'Yeni menü öğesi' ?></h2><form method="post" class="editor-form"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="menu_save"><input type="hidden" name="id" value="<?= $h($em['id']??'') ?>"><label>Menü anahtarı<input name="menu_key" required value="<?= $h($em['menu_key']??'main') ?>"></label><label>Etiket<input name="label" required maxlength="190" value="<?= $h($em['label']??'') ?>"></label><label>Bağlantı<input name="url" required value="<?= $h($em['url']??'') ?>" placeholder="/hakkimizda veya https://..."></label><label>Sıra<input type="number" name="sort_order" value="<?= $h($em['sort_order']??0) ?>"></label><button class="button">Kaydet</button></form></section>
<section class="content-admin"><h2>Menü öğeleri</h2><div class="table-wrap"><table><thead><tr><th>Menü</th><th>Etiket</th><th>URL</th><th>Sıra</th><th>Kaynak</th><th></th></tr></thead><tbody><?php foreach($menuRows as $m): ?><tr><td><?= $h($m['menu_key']) ?></td><td><?= $h($m['label']) ?></td><td><?= $h($m['url']) ?></td><td><?= $h($m['sort_order']) ?></td><td><?= $h($m['source_module']?:'Yönetici') ?></td><td><a href="<?= $h(CoreCMS\Core\Url::to('/admin/?section=menus&edit_menu='.$m['id'])) ?>">Düzenle</a><?php if(!$m['source_module']): ?><form method="post" class="inline-form"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="menu_delete"><input type="hidden" name="id" value="<?= $h($m['id']) ?>"><button class="link-button danger" onclick="return confirm('Öğe silinsin mi?')">Sil</button></form><?php else: ?><form method="post" class="inline-form"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="menu_detach"><input type="hidden" name="id" value="<?= $h($m['id']) ?>"><button class="link-button" onclick="return confirm('Menü öğesi modülden ayrılıp bağımsız hale getirilsin mi?')">Modülden ayır</button></form><?php endif ?></td></tr><?php endforeach ?></tbody></table></div></section>
<?php endif ?></main><?php if($section==='pages' && $pageMediaRows): ?><script>
document.getElementById('insert-page-media')?.addEventListener('click', function () {
    const field = document.getElementById('page-body');
    const picker = document.getElementById('page-media-select');
    if (!field || !picker || !picker.value) return;
    const width = document.getElementById('page-media-width')?.value || '100';
    const align = document.getElementById('page-media-align')?.value || 'center';
    field.setRangeText("\n[[media:" + picker.value + "|" + width + "|" + align + "]]\n", field.selectionStart, field.selectionEnd, 'end');
    field.focus();
    picker.value = '';
});
</script><?php endif ?><?php if($section==='pages'): ?><script>
const editor=document.getElementById('page-body');
if(editor){
 const insertMedia=function(id){
  const width=document.getElementById('page-media-width')?.value||'100';
  const align=document.getElementById('page-media-align')?.value||'center';
  editor.setRangeText("\n[[media:"+id+"|"+width+"|"+align+"]]\n",editor.selectionStart,editor.selectionEnd,'end');
  editor.focus();
 };
 document.querySelectorAll('.media-drag-item').forEach(function(item){
  item.addEventListener('dragstart',function(event){
   const id=item.dataset.mediaId||'';if(!/^[1-9][0-9]*$/.test(id))return;
   event.dataTransfer?.setData('text/plain','CORECMS_MEDIA:'+id);
   if(event.dataTransfer)event.dataTransfer.effectAllowed='copy';
   item.classList.add('is-dragging');
  });
  item.addEventListener('dragend',function(){item.classList.remove('is-dragging');});
  item.addEventListener('click',function(){insertMedia(item.dataset.mediaId||'');});
 });
 editor.addEventListener('dragover',function(event){if(event.dataTransfer?.types.includes('text/plain')){event.preventDefault();if(event.dataTransfer)event.dataTransfer.dropEffect='copy';}});
 editor.addEventListener('drop',function(event){
  const value=event.dataTransfer?.getData('text/plain')||'';
  const match=value.match(/^CORECMS_MEDIA:([1-9][0-9]*)$/);if(!match)return;
  event.preventDefault();insertMedia(match[1]);
 });
}
document.querySelectorAll('.rich-toolbar button').forEach(function(button){
 button.addEventListener('click',function(){
  const field=document.getElementById('page-body');if(!field)return;
  const start=field.selectionStart,end=field.selectionEnd;
  let selected=field.value.slice(start,end),prefix=button.dataset.prefix||'',suffix=button.dataset.suffix||'';
  if(button.dataset.line==='1'){
   const lineStart=field.value.lastIndexOf("\n",Math.max(0,start-1))+1;
   field.setRangeText(prefix+selected, lineStart, end, 'end');
  }else{
   if(!selected)selected='Metin';
   field.setRangeText(prefix+selected+suffix,start,end,'select');
   field.setSelectionRange(start+prefix.length,start+prefix.length+selected.length);
  }
  field.focus();
 });
});

document.getElementById('insert-page-link')?.addEventListener('click',function(){
 const field=document.getElementById('page-body');if(!field)return;
 const start=field.selectionStart,end=field.selectionEnd;
 const label=field.value.slice(start,end)||'Bağlantı metni';
 const url=window.prompt('HTTPS, mailto veya site içi /yol adresi:');if(!url)return;
 if(!/^(https:\/\/|mailto:|\/(?!\/))\S+$/i.test(url)){window.alert('Geçerli ve güvenli bir bağlantı girin.');return;}
 field.setRangeText('['+label+']('+url+')',start,end,'end');field.focus();
});
</script><script>
document.getElementById('page-preview-button')?.addEventListener('click',function(){
 const source=this.closest('form'); if(!source)return;
 const preview=document.createElement('form'); preview.method='post'; preview.action='<?= $h(CoreCMS\Core\Url::to('/admin/preview.php')) ?>'; preview.target='_blank';
 ['_csrf','title','slug','body','status'].forEach(function(name){
  const original=source.querySelector('[name="'+name+'"]'); if(!original)return;
  const input=document.createElement('input'); input.type='hidden'; input.name=name; input.value=original.value; preview.appendChild(input);
 });
 document.body.appendChild(preview); preview.submit(); preview.remove();
});
</script><script>
(function(){
 const form=document.querySelector('.editor-form'); if(!form)return;
 const status=document.getElementById('autosave-status');
 const pageId=form.querySelector('[name="id"]')?.value||'0';
 const fields=['title','slug','body','seo_title','seo_description','canonical_url','social_image_media_id'];
 let timer=null,working=false;
 function say(message){if(status)status.textContent=message;}
 function save(){
  if(pageId==='0'){say('Otomatik kayıt için önce sayfayı bir kez kaydedin.');return;}
  if(working)return;working=true;say('Otomatik kaydediliyor…');
  const data=new URLSearchParams();
  data.set('_csrf',form.querySelector('[name="_csrf"]')?.value||'');data.set('page_id',pageId);
  fields.forEach(function(name){const field=form.querySelector('[name="'+name+'"]');data.set(name,field?.value||'');});
  fetch('<?= $h(CoreCMS\Core\Url::to('/admin/autosave.php')) ?>',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:data.toString()})
   .then(function(response){return response.json().then(function(json){if(!response.ok||!json.ok)throw new Error(json.message||'Kayıt başarısız');return json;});})
   .then(function(){say('Otomatik kaydedildi · '+new Date().toLocaleTimeString());})
   .catch(function(error){say(error.message||'Otomatik kayıt yapılamadı.');})
   .finally(function(){working=false;});
 }
 fields.forEach(function(name){form.querySelector('[name="'+name+'"]')?.addEventListener('input',function(){clearTimeout(timer);say('Değişiklikler bekliyor…');timer=setTimeout(save,1200);});});
 document.getElementById('restore-autosave')?.addEventListener('click',function(){
  const snapshot=<?= json_encode($pageAutosave ?: null, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
  if(!snapshot)return;
  fields.forEach(function(name){const field=form.querySelector('[name="'+name+'"]');if(field&&snapshot[name]!==undefined)field.value=snapshot[name];});
  say('Otomatik taslak geri yüklendi; kaydetmeyi unutmayın.');
 });
})();
</script><?php endif ?><script src="<?= $h(CoreCMS\Core\Url::asset('js/responsive-nav.js')) ?>?v=3" defer></script></body></html>