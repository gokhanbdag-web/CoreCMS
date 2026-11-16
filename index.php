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
require_once $root . '/app/Core/TechnicalSeo.php';
require_once $root . '/app/Core/MediaLibrary.php';
if ($path === '/sitemap.xml') {
    try {
        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        echo CoreCMS\Core\TechnicalSeo::sitemap($db, $settings);
    } catch (Throwable $e) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Sitemap temporarily unavailable.';
        error_log('[CoreCMS] Sitemap generation failed: ' . $e->getMessage());
    }
    exit;
}
if ($path === '/robots.txt') {
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    echo CoreCMS\Core\TechnicalSeo::robots($settings);
    exit;
}
require_once $root . '/app/Core/RedirectManager.php';
try {
    $redirect = (new CoreCMS\Core\RedirectManager($db))->resolve($path);
    if ($redirect) {
        header('Location: ' . CoreCMS\Core\Url::to((string)$redirect['target_path']), true, (int)$redirect['http_code']);
        exit;
    }
} catch (Throwable $e) {
    error_log('[CoreCMS] Redirect lookup failed: ' . $e->getMessage());
}
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
    $stmt = $db->prepare("SELECT p.title,p.body,p.seo_title,p.seo_description,p.canonical_url,p.social_image_media_id,m.file_path AS social_image_path,m.alt_text AS social_image_alt FROM core_pages p LEFT JOIN core_media m ON m.id=p.social_image_media_id WHERE p.slug=? AND p.status='published' LIMIT 1");
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
if ($page && !empty($page['social_image_path'])) {
    $socialMedia = new CoreCMS\Core\MediaLibrary($db, $root, CoreCMS\Core\Url::basePath());
    $pageSocialUrl = $socialMedia->url((string)$page['social_image_path']);
    if ($pageSocialUrl !== '') { $settings['seo_social_image'] = $pageSocialUrl; $settings['seo_social_image_alt'] = (string)($page['social_image_alt'] ?? ''); }
}

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
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title><?= CoreCMS\Core\TechnicalSeo::head($settings, $pageTitle, $metaDescription, $canonicalUrl, $path === '/') ?><link rel="stylesheet" href="<?= htmlspecialchars(CoreCMS\Core\Url::asset('css/site.css'), ENT_QUOTES, 'UTF-8') ?>"></head>
<body><header class="site-header"><a class="brand" href="<?= htmlspecialchars(CoreCMS\Core\Url::to('/'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></a><nav>
<?php foreach ($menus as $menu): ?><a href="<?= htmlspecialchars($menu['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($menu['label'], ENT_QUOTES, 'UTF-8') ?></a><?php endforeach; ?>
<a href="<?= htmlspecialchars(CoreCMS\Core\Url::to('/admin/'), ENT_QUOTES, 'UTF-8') ?>">Yönetim</a></nav></header>
<main class="container"><?= $contentHtml ?></main><footer><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?> · <?= date('Y') ?></footer></body></html>
