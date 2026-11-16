<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
CoreCMS\Core\\Auth::startSession();
CoreCMS\Core\\Csrf::requireValidPost();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    CoreCMS\Core\\Auth::logout();
    header('Location: ' . CoreCMS\Core\\Url::to('/admin/'));
    exit;
}
if (!CoreCMS\Core\\Auth::check()) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'login') {
        if (CoreCMS\Core\\Auth::attempt($db, (string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''))) {
            header('Location: ' . CoreCMS\Core\\Url::to('/admin/')); exit;
        }
        $error = 'E-posta veya parola hatalı.';
    }
    ?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CoreCMS Giriş</title><link rel="stylesheet" href="<?= htmlspecialchars(CoreCMS\Core\\Url::asset('css/site.css'),ENT_QUOTES,'UTF-8') ?>"></head><body><main class="install-wrap"><section class="install-card"><h1>Yönetim paneli</h1><?php if (!empty($error)): ?><p class="error"><?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?></p><?php endif; ?><form method="post"><?= CoreCMS\Core\\Csrf::field() ?><input type="hidden" name="action" value="login"><label>E-posta<input type="email" name="email" required autocomplete="username"></label><label>Parola<input type="password" name="password" required autocomplete="current-password"></label><button class="button">Giriş yap</button></form></section></main></body></html><?php
    exit;
}

$manager = new CoreCMS\Core\\ModuleManager($db, dirname(__DIR__) . '/modules');
$coreMigrator = new CoreCMS\Core\\Migrator($db, dirname(__DIR__) . '/database/migrations');
$pendingCoreMigrations = $coreMigrator->pending();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'run_core_migrations') {
    try {
        $applied = $coreMigrator->migrate();
        $notice = count($applied) . ' çekirdek migrasyonu uygulandı.';
        $pendingCoreMigrations = $coreMigrator->pending();
    } catch (Throwable $e) { $error = 'Migrasyon başarısız: ' . $e->getMessage(); }
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['module_id'], $_POST['action'])) {
    $id = (string)$_POST['module_id'];
    try {
        if ($_POST['action'] === 'install') {
            $manager->install($id);
            $notice = 'Modül kuruldu veya güncellendi.';
        } elseif ($_POST['action'] === 'enable') {
            $manager->enable($id);
            $runtime = new CoreCMS\Core\\ModuleRuntime($db, dirname(__DIR__) . '/modules');
            $runtime->syncContent($id);
            $notice = 'Modül etkinleştirildi; modül sayfaları ve menüleri eşitlendi.';
        } elseif ($_POST['action'] === 'disable') $manager->disable($id);
        else throw new RuntimeException('Geçersiz işlem.');
        if (!isset($notice)) $notice = 'Modül durumu güncellendi.';
    } catch (Throwable $e) { $error = $e->getMessage(); }
}
$modules = $manager->discover();
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CoreCMS Yönetim</title><link rel="stylesheet" href="<?= htmlspecialchars(CoreCMS\Core\\Url::asset('css/site.css'),ENT_QUOTES,'UTF-8') ?>"></head><body><header class="site-header"><a class="brand" href="<?= htmlspecialchars(CoreCMS\Core\\Url::to('/'),ENT_QUOTES,'UTF-8') ?>">CoreCMS</a><nav><span>Merhaba, <?= htmlspecialchars($_SESSION['corecms_user_name'],ENT_QUOTES,'UTF-8') ?></span><a href="<?= htmlspecialchars(CoreCMS\Core\\Url::to('/'),ENT_QUOTES,'UTF-8') ?>">Siteyi görüntüle</a><form method="post" class="inline-form"><?= CoreCMS\Core\\Csrf::field() ?><button name="action" value="logout" class="link-button">Çıkış</button></form></nav></header><main class="container admin"><h1>Kontrol paneli</h1><p>Çekirdek yönetimi ve bağımsız sektör modülleri.</p>
<?php if (!empty($notice)): ?><p class="success"><?= htmlspecialchars($notice,ENT_QUOTES,'UTF-8') ?></p><?php endif; ?><?php if (!empty($error)): ?><p class="error"><?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?></p><?php endif; ?>
<section class="module-grid"><h2>Çekirdek güncellemeleri</h2><?php if ($pendingCoreMigrations): ?><p><?= count($pendingCoreMigrations) ?> bekleyen veritabanı güncellemesi var.</p><ul><?php foreach ($pendingCoreMigrations as $file): ?><li><?= htmlspecialchars(basename($file),ENT_QUOTES,'UTF-8') ?></li><?php endforeach; ?></ul><form method="post"><?= CoreCMS\Core\\Csrf::field() ?><button class="button" name="action" value="run_core_migrations">Yedek aldıktan sonra güncellemeleri uygula</button></form><?php else: ?><p>Çekirdek veritabanı güncel.</p><?php endif; ?></section>
<section class="module-grid"><h2>Modül yöneticisi</h2>
<?php foreach ($modules as $module): $status = $manager->status($module['id']); ?><article class="module-card"><div class="module-top"><span class="eyebrow"><?= htmlspecialchars($module['category'] ?? 'Sektör',ENT_QUOTES,'UTF-8') ?></span><span><?= $status['enabled'] ? 'Etkin' : ($status['installed'] ? 'Kurulu / pasif' : 'Kurulmamış') ?></span></div><h3><?= htmlspecialchars($module['name'],ENT_QUOTES,'UTF-8') ?></h3><p><?= htmlspecialchars($module['description'] ?? '',ENT_QUOTES,'UTF-8') ?></p><small>Manifest: v<?= htmlspecialchars($module['version'],ENT_QUOTES,'UTF-8') ?><?php if ($status['installed']): ?> · Kurulu: v<?= htmlspecialchars((string)$status['installed_version'],ENT_QUOTES,'UTF-8') ?><?php endif; ?></small><form method="post"><?= CoreCMS\Core\\Csrf::field() ?><input type="hidden" name="module_id" value="<?= htmlspecialchars($module['id'],ENT_QUOTES,'UTF-8') ?>"><?php if (!$status['installed'] || $status['installed_version'] !== $module['version']): ?><button class="button secondary" name="action" value="install">Kur / Güncelle</button><?php elseif ($status['enabled']): ?><button class="button secondary" name="action" value="disable">Devre dışı bırak</button><?php else: ?><button class="button secondary" name="action" value="enable">Etkinleştir</button><?php endif; ?></form></article><?php endforeach; ?></section></main></body></html>
