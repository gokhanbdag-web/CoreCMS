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
    ?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CoreCMS Giriş</title><link rel="stylesheet" href="<?= htmlspecialchars(CoreCMS\Core\Url::asset('css/site.css'),ENT_QUOTES,'UTF-8') ?>"></head><body><main class="install-wrap"><section class="install-card"><h1>Yönetim paneli</h1><?php if(!empty($error)): ?><p class="error"><?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?></p><?php endif ?><form method="post"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="login"><label>E-posta<input type="email" name="email" required></label><label>Parola<input type="password" name="password" required></label><button class="button">Giriş yap</button></form></section></main></body></html><?php exit;
}
require_once dirname(__DIR__).'/app/Core/ContentManager.php';
$modules=new CoreCMS\Core\ModuleManager($db,dirname(__DIR__).'/modules');
$content=new CoreCMS\Core\ContentManager($db);
$migrator=new CoreCMS\Core\Migrator($db,dirname(__DIR__).'/database/migrations');
$pending=$migrator->pending(); $notice=$error=null; $action=(string)($_POST['action']??'');
try {
 if(($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
  if($action==='page_save') { $id=(int)($_POST['id']??0); $content->savePage($id?:null,(string)($_POST['title']??''),(string)($_POST['slug']??''),(string)($_POST['body']??''),(string)($_POST['status']??'draft')); header('Location: '.CoreCMS\Core\Url::to('/admin/?section=pages&saved=1')); exit; }
  if($action==='page_delete') { $content->deletePage((int)($_POST['id']??0)); header('Location: '.CoreCMS\Core\Url::to('/admin/?section=pages&deleted=1')); exit; }
  if($action==='menu_save') { $id=(int)($_POST['id']??0); $content->saveMenu($id?:null,(string)($_POST['menu_key']??'main'),(string)($_POST['label']??''),(string)($_POST['url']??''),(int)($_POST['sort_order']??0)); header('Location: '.CoreCMS\Core\Url::to('/admin/?section=menus&saved=1')); exit; }
  if($action==='menu_delete') { $content->deleteMenu((int)($_POST['id']??0)); header('Location: '.CoreCMS\Core\Url::to('/admin/?section=menus&deleted=1')); exit; }
  if($action==='run_core_migrations') { $notice=count($migrator->migrate()).' çekirdek migrasyonu uygulandı.'; $pending=$migrator->pending(); }
  if(isset($_POST['module_id'])) { $id=(string)$_POST['module_id']; if($action==='install'){$modules->install($id);$notice='Modül kuruldu veya güncellendi.';} elseif($action==='enable'){$modules->enable($id);(new CoreCMS\Core\ModuleRuntime($db,dirname(__DIR__).'/modules'))->syncContent($id);$notice='Modül etkinleştirildi ve içeriği eşitlendi.';} elseif($action==='disable'){$modules->disable($id);$notice='Modül devre dışı bırakıldı.';} }
 }
} catch(Throwable $e){$error=$e->getMessage();}
$section=(string)($_GET['section']??'overview'); if(!in_array($section,['overview','pages','menus','modules'],true))$section='overview';
if(isset($_GET['saved']))$notice='Değişiklikler kaydedildi.'; if(isset($_GET['deleted']))$notice='Kayıt silindi.';
$h=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$pageRows=$db->query('SELECT id,title,slug,status,source_module FROM core_pages ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
$menuRows=$db->query('SELECT id,menu_key,label,url,sort_order,source_module FROM core_menus ORDER BY menu_key,sort_order,id')->fetchAll(PDO::FETCH_ASSOC);
$ep=$em=null;
if(isset($_GET['edit_page'])){$q=$db->prepare('SELECT * FROM core_pages WHERE id=?');$q->execute([(int)$_GET['edit_page']]);$ep=$q->fetch(PDO::FETCH_ASSOC)?:null;}
if(isset($_GET['edit_menu'])){$q=$db->prepare('SELECT * FROM core_menus WHERE id=?');$q->execute([(int)$_GET['edit_menu']]);$em=$q->fetch(PDO::FETCH_ASSOC)?:null;}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CoreCMS Yönetim</title><link rel="stylesheet" href="<?= $h(CoreCMS\Core\Url::asset('css/site.css')) ?>"></head><body>
<header class="site-header"><a class="brand" href="<?= $h(CoreCMS\Core\Url::to('/')) ?>">CoreCMS</a><nav><a href="<?= $h(CoreCMS\Core\Url::to('/')) ?>">Siteyi görüntüle</a><form method="post" class="inline-form"><?= CoreCMS\Core\Csrf::field() ?><button name="action" value="logout" class="link-button">Çıkış</button></form></nav></header>
<main class="container admin"><h1>Kontrol paneli</h1><p>İçerik, menüler ve sektör modülleri.</p><nav class="admin-nav"><?php foreach(['overview'=>'Genel bakış','pages'=>'Sayfalar','menus'=>'Menüler','modules'=>'Modüller'] as $k=>$v): ?><a href="<?= $h(CoreCMS\Core\Url::to('/admin/'.($k==='overview'?'':'?section='.$k))) ?>"><?= $h($v) ?></a><?php endforeach ?></nav>
<?php if($notice): ?><p class="success"><?= $h($notice) ?></p><?php endif ?><?php if($error): ?><p class="error"><?= $h($error) ?></p><?php endif ?>
<?php if($section==='overview'||$section==='modules'): ?>
<section class="content-admin"><h2>Çekirdek güncellemeleri</h2><?php if($pending): ?><p><?= count($pending) ?> bekleyen migrasyon.</p><form method="post"><?= CoreCMS\Core\Csrf::field() ?><button class="button" name="action" value="run_core_migrations">Migrasyonları uygula</button></form><?php else: ?><p>Veritabanı güncel.</p><?php endif ?></section>
<section class="module-grid"><h2>Modül yöneticisi</h2><?php foreach($modules->discover() as $mod): $st=$modules->status($mod['id']); ?><article class="module-card"><h3><?= $h($mod['name']) ?></h3><p><?= $h($mod['description']??'') ?></p><small><?= $st['enabled']?'Etkin':($st['installed']?'Kurulu / pasif':'Kurulmamış') ?></small><form method="post"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="module_id" value="<?= $h($mod['id']) ?>"><?php if(!$st['installed']||$st['installed_version']!==$mod['version']): ?><button class="button secondary" name="action" value="install">Kur / Güncelle</button><?php elseif($st['enabled']): ?><button class="button secondary" name="action" value="disable">Devre dışı bırak</button><?php else: ?><button class="button secondary" name="action" value="enable">Etkinleştir</button><?php endif ?></form></article><?php endforeach ?></section>
<?php endif ?>
<?php if($section==='pages'): ?>
<section class="content-admin"><h2><?= $ep?'Sayfayı düzenle':'Yeni sayfa' ?></h2><form method="post" class="editor-form"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="page_save"><input type="hidden" name="id" value="<?= $h($ep['id']??'') ?>"><label>Başlık<input name="title" required maxlength="255" value="<?= $h($ep['title']??'') ?>"></label><label>URL kısa adı<input name="slug" value="<?= $h($ep['slug']??'') ?>" placeholder="hakkimizda"></label><label>İçerik<textarea name="body" rows="10"><?= $h(htmlspecialchars_decode((string)($ep['body']??''),ENT_QUOTES)) ?></textarea></label><label>Durum<select name="status"><option value="draft" <?= ($ep['status']??'draft')==='draft'?'selected':'' ?>>Taslak</option><option value="published" <?= ($ep['status']??'')==='published'?'selected':'' ?>>Yayında</option></select></label><button class="button">Kaydet</button></form></section>
<section class="content-admin"><h2>Sayfalar</h2><div class="table-wrap"><table><thead><tr><th>Başlık</th><th>URL</th><th>Durum</th><th>Kaynak</th><th></th></tr></thead><tbody><?php foreach($pageRows as $p): ?><tr><td><?= $h($p['title']) ?></td><td>/<?= $h($p['slug']) ?></td><td><?= $h($p['status']) ?></td><td><?= $h($p['source_module']?:'Yönetici') ?></td><td><a href="<?= $h(CoreCMS\Core\Url::to('/admin/?section=pages&edit_page='.$p['id'])) ?>">Düzenle</a><?php if(!$p['source_module']): ?><form method="post" class="inline-form"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="page_delete"><input type="hidden" name="id" value="<?= $h($p['id']) ?>"><button class="link-button danger" onclick="return confirm('Sayfa silinsin mi?')">Sil</button></form><?php else: ?><small> Modül kaydı</small><?php endif ?></td></tr><?php endforeach ?></tbody></table></div></section>
<?php endif ?>
<?php if($section==='menus'): ?>
<section class="content-admin"><h2><?= $em?'Menü öğesini düzenle':'Yeni menü öğesi' ?></h2><form method="post" class="editor-form"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="menu_save"><input type="hidden" name="id" value="<?= $h($em['id']??'') ?>"><label>Menü anahtarı<input name="menu_key" required value="<?= $h($em['menu_key']??'main') ?>"></label><label>Etiket<input name="label" required maxlength="190" value="<?= $h($em['label']??'') ?>"></label><label>Bağlantı<input name="url" required value="<?= $h($em['url']??'') ?>" placeholder="/hakkimizda veya https://..."></label><label>Sıra<input type="number" name="sort_order" value="<?= $h($em['sort_order']??0) ?>"></label><button class="button">Kaydet</button></form></section>
<section class="content-admin"><h2>Menü öğeleri</h2><div class="table-wrap"><table><thead><tr><th>Menü</th><th>Etiket</th><th>URL</th><th>Sıra</th><th>Kaynak</th><th></th></tr></thead><tbody><?php foreach($menuRows as $m): ?><tr><td><?= $h($m['menu_key']) ?></td><td><?= $h($m['label']) ?></td><td><?= $h($m['url']) ?></td><td><?= $h($m['sort_order']) ?></td><td><?= $h($m['source_module']?:'Yönetici') ?></td><td><a href="<?= $h(CoreCMS\Core\Url::to('/admin/?section=menus&edit_menu='.$m['id'])) ?>">Düzenle</a><?php if(!$m['source_module']): ?><form method="post" class="inline-form"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="menu_delete"><input type="hidden" name="id" value="<?= $h($m['id']) ?>"><button class="link-button danger" onclick="return confirm('Öğe silinsin mi?')">Sil</button></form><?php else: ?><small> Modül kaydı</small><?php endif ?></td></tr><?php endforeach ?></tbody></table></div></section>
<?php endif ?></main></body></html>