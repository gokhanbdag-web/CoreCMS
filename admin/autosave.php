<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
CoreCMS\Core\Auth::startSession();
CoreCMS\Core\Auth::refreshSession($db);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

if (!CoreCMS\Core\Auth::check()) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'message'=>'Yönetici oturumu gerekli.']);
    exit;
}

if (!(new CoreCMS\Core\RoleManager($db))->can((string)($_SESSION['corecms_user_role'] ?? ''), 'pages.manage')) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'message'=>'Sayfa düzenleme yetkiniz bulunmuyor.']);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok'=>false,'message'=>'Yalnızca POST kabul edilir.']);
    exit;
}
if (!CoreCMS\Core\Csrf::verify(isset($_POST['_csrf']) ? (string)$_POST['_csrf'] : null)) {
    http_response_code(419);
    echo json_encode(['ok'=>false,'message'=>'Oturum doğrulaması başarısız.']);
    exit;
}

require_once dirname(__DIR__).'/app/Core/ContentManager.php';
$pageId = (int)($_POST['page_id'] ?? 0);
$userId = (int)($_SESSION['corecms_user_id'] ?? 0);
$title = trim((string)($_POST['title'] ?? ''));
$slug = CoreCMS\Core\ContentManager::slug((string)($_POST['slug'] ?? $title));
$body = (string)($_POST['body'] ?? '');
$seoTitle = trim((string)($_POST['seo_title'] ?? ''));
$seoDescription = trim((string)($_POST['seo_description'] ?? ''));
$canonical = trim((string)($_POST['canonical_url'] ?? ''));
$socialImageId = (int)($_POST['social_image_media_id'] ?? 0);

if ($pageId < 1 || $userId < 1 || $title === '' || (preg_match_all('/./us', $title) ?: 0) > 255 || strlen($body) > 1000000 || (preg_match_all('/./us', $seoTitle) ?: 0) > 255 || (preg_match_all('/./us', $seoDescription) ?: 0) > 320 || strlen($canonical) > 500 || $socialImageId < 0) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'message'=>'Taslak verileri geçersiz veya çok uzun.']);
    exit;
}
if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) { http_response_code(422); echo json_encode(['ok'=>false,'message'=>'Sayfa bağlantısı geçersiz.']); exit; }
if ($canonical !== '' && !preg_match('#^(https://[^/\s]+(?:/[^\s]*)?|/(?!/)[^\s]*)$#i', $canonical)) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'message'=>'Canonical adresi HTTPS veya site içi yol olmalı.']);
    exit;
}
try {
    $exists = $db->prepare('SELECT id FROM core_pages WHERE id=?');
    $exists->execute([$pageId]);
    if (!$exists->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['ok'=>false,'message'=>'Sayfa bulunamadı.']);
        exit;
    }
    $stmt = $db->prepare('INSERT INTO core_page_autosaves (page_id,user_id,title,slug,body,seo_title,seo_description,canonical_url,social_image_media_id,saved_at) VALUES (?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE title=VALUES(title),slug=VALUES(slug),body=VALUES(body),seo_title=VALUES(seo_title),seo_description=VALUES(seo_description),canonical_url=VALUES(canonical_url),social_image_media_id=VALUES(social_image_media_id),saved_at=CURRENT_TIMESTAMP');
    if ((string)$db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $stmt = $db->prepare('INSERT INTO core_page_autosaves (page_id,user_id,title,slug,body,seo_title,seo_description,canonical_url,social_image_media_id,saved_at) VALUES (?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP) ON CONFLICT(page_id,user_id) DO UPDATE SET title=excluded.title,slug=excluded.slug,body=excluded.body,seo_title=excluded.seo_title,seo_description=excluded.seo_description,canonical_url=excluded.canonical_url,social_image_media_id=excluded.social_image_media_id,saved_at=CURRENT_TIMESTAMP');
    }
    if ($socialImageId > 0) { $media = $db->prepare('SELECT id FROM core_media WHERE id=?'); $media->execute([$socialImageId]); if (!$media->fetchColumn()) { http_response_code(422); echo json_encode(['ok'=>false,'message'=>'Sosyal görsel bulunamadı.']); exit; } } else { $socialImageId = null; }
    $stmt->execute([$pageId,$userId,$title,$slug,$body,$seoTitle,$seoDescription,$canonical,$socialImageId]);
    echo json_encode(['ok'=>true,'saved_at'=>date(DATE_ATOM)]);
} catch (Throwable $e) {
    error_log('[CoreCMS] Page autosave failed: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'Otomatik kayıt tamamlanamadı.']);
}