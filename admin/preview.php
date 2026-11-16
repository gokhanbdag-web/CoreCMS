<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
CoreCMS\Core\Auth::startSession();
CoreCMS\Core\Csrf::requireValidPost();
if (!CoreCMS\Core\Auth::check()) {
    http_response_code(403);
    exit('Bu önizleme yalnızca yetkili yönetici oturumunda kullanılabilir.');
}

if (!(new CoreCMS\Core\RoleManager($db))->can((string)($_SESSION['corecms_user_role'] ?? ''), 'pages.manage')) {
    http_response_code(403);
    exit('Sayfa önizleme yetkiniz bulunmuyor.');
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Yalnızca POST isteği kabul edilir.');
}

$title = trim((string)($_POST['title'] ?? ''));
$slug = trim((string)($_POST['slug'] ?? ''));
$body = (string)($_POST['body'] ?? '');
$status = (string)($_POST['status'] ?? 'draft');
if ($title === '' || strlen($title) > 1020 || strlen($body) > 1000000 || !in_array($status, ['draft','published','scheduled'], true)) {
    http_response_code(422);
    exit('Önizleme verileri geçersiz veya izin verilen boyutu aşıyor.');
}
$h = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
require_once dirname(__DIR__) . '/app/Core/MediaLibrary.php';
require_once dirname(__DIR__) . '/app/Core/PageBodyRenderer.php';
$storedBody = htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$rendered = CoreCMS\Core\PageBodyRenderer::render(
    $db,
    $storedBody,
    new CoreCMS\Core\MediaLibrary($db, dirname(__DIR__), CoreCMS\Core\Url::basePath())
);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
?><!doctype html>
<html lang="<?= $h($settings['site_language'] ?? 'tr') ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Önizleme · <?= $h($title) ?></title><link rel="stylesheet" href="<?= $h(CoreCMS\Core\Url::asset('css/site.css')) ?>"></head>
<body><header class="site-header"><strong>CoreCMS · Yönetici önizlemesi</strong><span><?= $h($status === 'draft' ? 'Taslak' : 'Yayın adayı') ?></span></header>
<main class="container"><p class="editor-help">Bu içerik yalnızca giriş yapmış yönetici tarafından görülebilir; kaydedilmez ve herkese açık değildir.</p><article class="content-page"><h1><?= $h($title) ?></h1><div class="content-body"><?= $rendered ?></div></article></main></body></html>