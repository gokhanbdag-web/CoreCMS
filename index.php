<?php
declare(strict_types=1);

$root = __DIR__;
require_once $root . '/app/Core/Url.php';
if (!is_file($root . '/config.php')) {
    header('Location: ' . CoreCMS\Core\Url::to('/installer/'));
    exit;
}
require $root . '/app/bootstrap.php';

$path = CoreCMS\Core\Url::path();
if ($path === '/admin' || str_starts_with($path, '/admin/')) {
    require $root . '/admin/index.php';
    exit;
}

$core = new CoreCMS\Core\ModuleManager($db, $root . '/modules');
$modules = $core->enabledModules();
http_response_code(200);
?><!doctype html>
<html lang="<?= htmlspecialchars($settings['site_language'] ?? 'tr', ENT_QUOTES, 'UTF-8') ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($settings['site_name'] ?? 'CoreCMS', ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="<?= htmlspecialchars(CoreCMS\Core\Url::asset('css/site.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<header class="site-header"><a class="brand" href="<?= htmlspecialchars(CoreCMS\Core\Url::to('/'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($settings['site_name'] ?? 'CoreCMS', ENT_QUOTES, 'UTF-8') ?></a><nav><a href="<?= htmlspecialchars(CoreCMS\Core\Url::to('/'), ENT_QUOTES, 'UTF-8') ?>">Ana Sayfa</a><a href="<?= htmlspecialchars(CoreCMS\Core\Url::to('/admin/'), ENT_QUOTES, 'UTF-8') ?>">Yönetim</a></nav></header>
<main class="container">
<section class="hero"><span class="eyebrow">MODÜLER İÇERİK YÖNETİMİ</span><h1>Tek çekirdek.<br><em>Sınırsız sektör.</em></h1><p>CoreCMS kurulumu tamamlandı. Sektör modülleri kendi sayfalarını, iş akışlarını ve tasarım katmanlarını bağımsız olarak ekler.</p><a class="button" href="<?= htmlspecialchars(CoreCMS\Core\Url::to('/admin/'), ENT_QUOTES, 'UTF-8') ?>">Yönetim panelini aç</a></section>
<section class="module-grid"><h2>Etkin modüller</h2>
<?php if (!$modules): ?><p>Henüz etkin sektör modülü bulunmuyor. Yönetim panelinden modülleri inceleyebilirsiniz.</p><?php endif; ?>
<?php foreach ($modules as $module): ?><article class="module-card"><h3><?= htmlspecialchars($module['name'], ENT_QUOTES, 'UTF-8') ?></h3><p><?= htmlspecialchars($module['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></p><small>v<?= htmlspecialchars($module['version'], ENT_QUOTES, 'UTF-8') ?></small></article><?php endforeach; ?>
</section>
</main><footer>CoreCMS · <?= date('Y') ?></footer></body></html>
