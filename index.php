<?php
declare(strict_types=1);

$root = __DIR__;
require_once $root . '/app/Core/Url.php';
if (!is_file($root . '/config.php')) {
    header('Location: ' . CoreCMS\Core\Url::to('/installer/'));
    exit;
}
require $root . '/app/bootstrap.php';
// Scheduled pages are promoted on the next public/admin request; no server cron is required.
try { $publishDue = $db->prepare("UPDATE core_pages SET status='published',scheduled_at=NULL WHERE status='scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= ?"); $publishDue->execute([date('Y-m-d H:i:s')]); }
catch (Throwable $e) { error_log('[CoreCMS] Scheduled publishing check failed: '.$e->getMessage()); }

$path = CoreCMS\Core\Url::path();
if ($path === '/admin' || str_starts_with($path, '/admin/')) {
    require $root . '/admin/index.php';
    exit;
}

$runtime = new CoreCMS\Core\ModuleRuntime($db, $root . '/modules');
$manager = new CoreCMS\Core\ModuleManager($db, $root . '/modules');
$modules = $manager->enabledModules();
foreach ($modules as $module) {
    try { $runtime->syncContent($module['id']); }
    catch (Throwable $e) { error_log('[CoreCMS] Content provisioning failed: ' . $module['id'] . ' ' . $e->getMessage()); }
}
if ($runtime->dispatch($path, (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'))) exit;

$slug = trim($path, '/');
$page = null;
if ($slug !== '') {
    $stmt = $db->prepare("SELECT title,body,seo_title,seo_description,canonical_url FROM core_pages WHERE slug=? AND status='published' LIMIT 1");
    $stmt->execute([$slug]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
$menus = [];
try {
    $stmt = $db->prepare('SELECT label,url FROM core_menus WHERE menu_key=? ORDER BY sort_order,id');
    $stmt->execute(['main']);
    $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { error_log('[CoreCMS] Menu load failed: ' . $e->getMessage()); }

$siteName = (string)($settings['site_name'] ?? 'CoreCMS');
$homeEyebrow = (string)($settings['home_eyebrow'] ?? 'MODÜLER İÇERİK YÖNETİMİ');
$homeTitle = (string)($settings['home_title'] ?? 'Tek çekirdek.');
$homeAccentTitle = (string)($settings['home_accent_title'] ?? 'Sınırsız sektör.');
$homeDescription = (string)($settings['home_description'] ?? 'CoreCMS kurulumu tamamlandı. Sektör modülleri kendi sayfalarını, iş akışlarını ve tasarım katmanlarını bağımsız olarak ekler.');
$pageTitle = $page ? (string)(($page['seo_title'] ?? '') !== '' ? $page['seo_title'] : $page['title']) : $siteName;
$metaDescription = $page ? (string)($page['seo_description'] ?? '') : $homeDescription;
$canonicalUrl = $page ? (string)($page['canonical_url'] ?? '') : '';

require_once $root . '/app/Core/MediaLibrary.php';
require_once $root . '/app/Core/PageBodyRenderer.php';
$pageBodyHtml = $page ? CoreCMS\Core\PageBodyRenderer::render($db, (string)$page['body'], new CoreCMS\Core\MediaLibrary($db, $root, CoreCMS\Core\Url::basePath())) : '';
ob_start();
if ($page) {
    echo '<article class="content-page"><h1>' . htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') . '</h1>';
    echo '<div class="content-body">' . $pageBodyHtml . '</div></article>';
} elseif ($path === '/') {
    echo '<section class="hero"><span class="eyebrow">' . htmlspecialchars($homeEyebrow, ENT_QUOTES, 'UTF-8') . '</span><h1>' . htmlspecialchars($homeTitle, ENT_QUOTES, 'UTF-8') . '<br><em>' . htmlspecialchars($homeAccentTitle, ENT_QUOTES, 'UTF-8') . '</em></h1><p>' . htmlspecialchars($homeDescription, ENT_QUOTES, 'UTF-8') . '</p><a class="button" href="' . htmlspecialchars(CoreCMS\Core\Url::to('/admin/'), ENT_QUOTES, 'UTF-8') . '">Yönetim panelini aç</a></section><section class="module-grid"><h2>Etkin modüller</h2>';
    if (!$modules) echo '<p>Henüz etkin sektör modülü bulunmuyor.</p>';
    foreach ($modules as $module) echo '<article class="module-card"><h3>' . htmlspecialchars($module['name'], ENT_QUOTES, 'UTF-8') . '</h3><p>' . htmlspecialchars($module['description'] ?? '', ENT_QUOTES, 'UTF-8') . '</p><small>v' . htmlspecialchars($module['version'], ENT_QUOTES, 'UTF-8') . '</small></article>';
    echo '</section>';
} else {
    http_response_code(404);
    echo '<section class="content-page"><h1>Sayfa bulunamadı</h1><p>Aradığınız içerik mevcut değil.</p></section>';
}
$contentHtml = (string)ob_get_clean();
$theme = $runtime->theme();
$layout = $theme ? $theme['path'] . '/layout.php' : '';
if ($layout !== '' && is_file($layout)) {
    require $layout;
    exit;
}
?><!doctype html>
<html lang="<?= htmlspecialchars($settings['site_language'] ?? 'tr', ENT_QUOTES, 'UTF-8') ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title><?php if ($metaDescription !== ''): ?><meta name="description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?>"><?php endif ?><?php if ($canonicalUrl !== ''): ?><link rel="canonical" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>"><?php endif ?><link rel="stylesheet" href="<?= htmlspecialchars(CoreCMS\Core\Url::asset('css/site.css'), ENT_QUOTES, 'UTF-8') ?>"></head>
<body><header class="site-header"><a class="brand" href="<?= htmlspecialchars(CoreCMS\Core\Url::to('/'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></a><nav>
<?php foreach ($menus as $menu): ?><a href="<?= htmlspecialchars($menu['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($menu['label'], ENT_QUOTES, 'UTF-8') ?></a><?php endforeach; ?>
<a href="<?= htmlspecialchars(CoreCMS\Core\Url::to('/admin/'), ENT_QUOTES, 'UTF-8') ?>">Yönetim</a></nav></header>
<main class="container"><?= $contentHtml ?></main><footer><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?> · <?= date('Y') ?></footer></body></html>
