<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
CoreCMS\Core\Auth::startSession();
CoreCMS\Core\Csrf::requireValidPost();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    CoreCMS\Core\Auth::logout();
    header('Location: ' . CoreCMS\Core\Url::to('/admin/'));
    exit;
}

if (!CoreCMS\Core\Auth::check()) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'login') {
        if (CoreCMS\Core\Auth::attempt($db, (string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''))) {
            header('Location: ' . CoreCMS\Core\Url::to('/admin/'));
            exit;
        }
        $error = 'E-posta veya parola hatalı.';
    }
    ?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CoreCMS Giriş</title><link rel="stylesheet" href="<?= htmlspecialchars(CoreCMS\Core\Url::asset('css/site.css'),ENT_QUOTES,'UTF-8') ?>"></head><body><main class="install-wrap"><section class="install-card"><h1>Yönetim paneli</h1><?php if (!empty($error)): ?><p class="error"><?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?></p><?php endif; ?><form method="post"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="login"><label>E-posta<input type="email" name="email" required autocomplete="username"></label><label>Parola<input type="password" name="password" required autocomplete="current-password"></label><button class="button">Giriş yap</button></form></section></main></body></html><?php
    exit;
}

$manager = new CoreCMS\Core\ModuleManager($db, dirname(__DIR__) . '/modules');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['module_id'], $_POST['action'])) {
    $id = (string)$_POST['module_id'];
    try {
        if ($_POST['action'] === 'enable') $manager->enable($id);
        elseif ($_POST['action'] === 'disable') $manager->disable($id);
        else throw new RuntimeException('Geçersiz işlem.');
        $notice = 'Modül durumu güncellendi.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
$modules = $manager->discover();
$enabled = array_column($manager->enabledModules(), 'id');
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CoreCMS Yönetim</title><link rel="stylesheet" href="<?= htmlspecialchars(CoreCMS\Core\Url::asset('css/site.css'),ENT_QUOTES,'UTF-8') ?>"></head><body><header class="site-header"><a class="brand" href="<?= htmlspecialchars(CoreCMS\Core\Url::to('/'),ENT_QUOTES,'UTF-8') ?>">CoreCMS</a><nav><span>Merhaba, <?= htmlspecialchars($_SESSION['corecms_user_name'],ENT_QUOTES,'UTF-8') ?></span><a href="<?= htmlspecialchars(CoreCMS\Core\Url::to('/'),ENT_QUOTES,'UTF-8') ?>">Siteyi görüntüle</a><form method="post" class="inline-form"><?= CoreCMS\Core\Csrf::field() ?><button name="action" value="logout" class="link-button">Çıkış</button></form></nav></header><main class="container admin"><h1>Kontrol paneli</h1><p>Çekirdek yönetimi ve bağımsız sektör modülleri.</p><?php if (!empty($notice)): ?><p class="success"><?= htmlspecialchars($notice,ENT_QUOTES,'UTF-8') ?></p><?php endif; ?><?php if (!empty($error)): ?><p class="error"><?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?></p><?php endif; ?><section class="module-grid"><h2>Modül yöneticisi</h2><?php foreach ($modules as $module): $isEnabled = in_array($module['id'], $enabled, true); ?><article class="module-card"><div class="module-top"><span class="eyebrow"><?= htmlspecialchars($module['category'] ?? 'Sektör',ENT_QUOTES,'UTF-8') ?></span><span><?= $isEnabled ? 'Etkin' : 'Pasif' ?></span></div><h3><?= htmlspecialchars($module['name'],ENT_QUOTES,'UTF-8') ?></h3><p><?= htmlspecialchars($module['description'] ?? '',ENT_QUOTES,'UTF-8') ?></p><small>v<?= htmlspecialchars($module['version'],ENT_QUOTES,'UTF-8') ?></small><form method="post"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="module_id" value="<?= htmlspecialchars($module['id'],ENT_QUOTES,'UTF-8') ?>"><button class="button secondary" name="action" value="<?= $isEnabled ? 'disable' : 'enable' ?>"><?= $isEnabled ? 'Devre dışı bırak' : 'Etkinleştir' ?></button></form></article><?php endforeach; ?></section></main></body></html>
