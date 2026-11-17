<?php
declare(strict_types=1);

$root = __DIR__;
require_once $root . '/app/Core/Url.php';
if (!is_file($root . '/config.php')) {
    header('Location: ' . CoreCMS\Core\Url::to('/installer/'));
    exit;
}
require $root . '/app/bootstrap.php';
$frontText = static fn(string $key, string $fallback): string => htmlspecialchars($translate($key, $fallback), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
// Scheduled pages are promoted on the next public/admin request; no server cron is required.
try { $publishDue = $db->prepare("UPDATE core_pages SET status='published',scheduled_at=NULL WHERE status='scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= ?"); $publishDue->execute([date('Y-m-d H:i:s')]); }
catch (Throwable $e) { error_log('[CoreCMS] Scheduled publishing check failed: '.$e->getMessage()); }


$path = CoreCMS\Core\Url::path();
require_once $root . '/app/Core/ContentManager.php';
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

$contentRepository = new CoreCMS\Core\ContentManager($db);
$slug = trim($path, '/');
$page = $slug !== '' ? $contentRepository->publishedPage($slug, $currentLocale) : null;
$menus = $contentRepository->menusForLocale('main', $currentLocale);
$languagePaths = [];
foreach ($availableLocales as $language) {
    $code = (string)$language['locale_code'];
    $languagePaths[$code] = $page
        ? '/' . ($contentRepository->pageSlugForLocale((int)$page['id'], $code) ?? (string)$page['base_slug'])
        : $path;
}

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
require_once $root . '/app/Core/FormManager.php';
require_once $root . '/app/Core/FormRenderer.php';
require_once $root . '/app/Core/FormSubmissionService.php';
require_once $root . '/app/Core/EmailTemplateManager.php';
require_once $root . '/app/Core/MailQueue.php';
require_once $root . '/app/Core/FormNotificationService.php';
$formRenderer=null;
if($page && preg_match('/\[\[form:[a-z][a-z0-9_-]{1,99}\]\]/',(string)$page['body'])){
    CoreCMS\Core\Auth::startSession();
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST' && isset($_POST['_corecms_form'])){
        CoreCMS\Core\Csrf::requireValidPost();
        $formKey=(string)$_POST['_corecms_form'];
        if(!str_contains((string)$page['body'],'[[form:'.$formKey.']]')){http_response_code(400);exit('Invalid form target.');}
        try{
            $formManager=new CoreCMS\Core\FormManager($db);
            $form=$formManager->findByKey($formKey,true);
            if(!$form)throw new RuntimeException('This form is not available.');
            $service=new CoreCMS\Core\FormSubmissionService($db,(string)($config['database']['password']??''));
            $submissionId=$service->submit($formKey,$currentLocale,$_POST,(string)($_SERVER['REMOTE_ADDR']??''));
            if($submissionId!==null && !empty($form['recipient_email'])){
                try{
                    $mailQueue=new CoreCMS\Core\MailQueue($db);
                    $notifications=new CoreCMS\Core\FormNotificationService($db,$mailQueue,new CoreCMS\Core\EmailTemplateManager($db));
                    $notifications->queueSubmission($form,$submissionId,$_POST,$currentLocale,(string)($settings['site_name']??'CoreCMS'));
                    $mailQueue->process($settings,1);
                }catch(Throwable $mailError){error_log('[CoreCMS] Form notification queue failed: '.$mailError->getMessage());}
            }
            $_SESSION['corecms_form_feedback'][$formKey]=['success'=>true,'message'=>(string)($form['success_message']?:$translate('frontend.forms.success','Your message has been received.'))];
        }catch(PDOException $e){
            error_log('[CoreCMS] Public form database error: '.$e->getMessage());
            $_SESSION['corecms_form_feedback'][$formKey]=['error'=>true,'message'=>$translate('frontend.forms.error','The form could not be submitted. Please try again.')];
        }catch(Throwable $e){
            $_SESSION['corecms_form_feedback'][$formKey]=['error'=>true,'message'=>$e->getMessage()];
        }
        header('Location: '.CoreCMS\Core\Url::to($path).'#form-'.rawurlencode($formKey),true,303);exit;
    }
    $formRenderer=new CoreCMS\Core\FormRenderer($db,$currentLocale,CoreCMS\Core\Csrf::token(),CoreCMS\Core\Url::to($path),$translate('frontend.forms.submit','Gönder'));
}
$pageBodyHtml = $page ? CoreCMS\Core\PageBodyRenderer::render($db, (string)$page['body'], new CoreCMS\Core\MediaLibrary($db, $root, CoreCMS\Core\Url::basePath()),$formRenderer) : '';
ob_start();
if ($page) {
    echo '<article class="content-page"><h1>' . htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') . '</h1>';
    echo '<div class="content-body">' . $pageBodyHtml . '</div></article>';
} elseif ($path === '/') {
    echo '<section class="hero"><span class="eyebrow">' . htmlspecialchars($homeEyebrow, ENT_QUOTES, 'UTF-8') . '</span><h1>' . htmlspecialchars($homeTitle, ENT_QUOTES, 'UTF-8') . '<br><em>' . htmlspecialchars($homeAccentTitle, ENT_QUOTES, 'UTF-8') . '</em></h1><p>' . htmlspecialchars($homeDescription, ENT_QUOTES, 'UTF-8') . '</p><a class="button" href="' . htmlspecialchars(CoreCMS\Core\Url::to('/admin/'), ENT_QUOTES, 'UTF-8') . '">' . $frontText('frontend.home.admin_button', 'Open administration') . '</a></section><section class="module-grid"><h2>' . $frontText('frontend.home.enabled_modules', 'Enabled modules') . '</h2>';
    if (!$modules) echo '<p>' . $frontText('frontend.home.no_modules', 'No sector modules are enabled yet.') . '</p>';
    foreach ($modules as $module) echo '<article class="module-card"><h3>' . htmlspecialchars($module['name'], ENT_QUOTES, 'UTF-8') . '</h3><p>' . htmlspecialchars($module['description'] ?? '', ENT_QUOTES, 'UTF-8') . '</p><small>v' . htmlspecialchars($module['version'], ENT_QUOTES, 'UTF-8') . '</small></article>';
    echo '</section>';
} else {
    http_response_code(404);
    echo '<section class="content-page"><h1>' . $frontText('frontend.404.title', 'Page not found') . '</h1><p>' . $frontText('frontend.404.description', 'The content you are looking for is unavailable.') . '</p></section>';
}
$contentHtml = (string)ob_get_clean();
$theme = $runtime->theme();
if ($theme) {
    try {
        $themeManager = new CoreCMS\Core\ThemeManager($db, $root . '/modules');
        $themeHtml = $themeManager->renderTemplate($theme['module_id'], 'layout', [
            'contentHtml'=>$contentHtml,
            'settings'=>$settings,
            'pageTitle'=>$pageTitle,
            'metaDescription'=>$metaDescription,
            'canonicalUrl'=>$canonicalUrl,
            'menus'=>$menus,
            'path'=>$path,
            'siteName'=>$siteName,
            'currentLocale'=>$currentLocale,
            'availableLocales'=>$availableLocales,
            'translate'=>$translate,
        ]);
        $themeCss = '';
        $themeJs = '';
        foreach (($theme['assets'] ?? []) as $themeAsset) {
            try {
                $extension = strtolower(pathinfo((string)$themeAsset, PATHINFO_EXTENSION));
                $assetUrl = $themeManager->assetUrl($theme['module_id'], (string)$themeAsset);
                if ($extension === 'css') $themeCss .= '<link rel="stylesheet" href="' . htmlspecialchars($assetUrl, ENT_QUOTES, 'UTF-8') . '">';
                elseif ($extension === 'js') $themeJs .= '<script src="' . htmlspecialchars($assetUrl, ENT_QUOTES, 'UTF-8') . '" defer></script>';
            } catch (Throwable $assetError) {
                error_log('[CoreCMS] Theme asset skipped: ' . $theme['module_id'] . ' ' . $assetError->getMessage());
            }
        }
        $themeCss .= '<link rel="stylesheet" href="' . htmlspecialchars(CoreCMS\Core\Url::asset('css/responsive-nav.css'), ENT_QUOTES, 'UTF-8') . '?v=3">';
        $themeJs .= '<script src="' . htmlspecialchars(CoreCMS\Core\Url::asset('js/responsive-nav.js'), ENT_QUOTES, 'UTF-8') . '?v=3" defer></script>';
        if (stripos($themeHtml, '</head>') !== false) $themeHtml = str_ireplace('</head>', $themeCss . '</head>', $themeHtml);
        else $themeHtml = $themeCss . $themeHtml;
        if (stripos($themeHtml, '</body>') !== false) $themeHtml = str_ireplace('</body>', $themeJs . '</body>', $themeHtml);
        else $themeHtml .= $themeJs;
        echo $themeHtml;
        exit;
    } catch (Throwable $e) {
        error_log('[CoreCMS] Theme rendering failed: ' . $theme['module_id'] . ' ' . $e->getMessage());
    }
}
?><!doctype html>
<html lang="<?= htmlspecialchars($currentLocale, ENT_QUOTES, 'UTF-8') ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title><?= CoreCMS\Core\TechnicalSeo::head($settings, $pageTitle, $metaDescription, $canonicalUrl, $path === '/') ?><link rel="stylesheet" href="<?= htmlspecialchars(CoreCMS\Core\Url::asset('css/site.css'), ENT_QUOTES, 'UTF-8') ?>"></head>
<body><header class="site-header"><a class="brand" href="<?= htmlspecialchars(CoreCMS\Core\Url::to('/'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></a><nav>
<?php foreach ($menus as $menu): ?><a href="<?= htmlspecialchars($menu['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($menu['label'], ENT_QUOTES, 'UTF-8') ?></a><?php endforeach; ?>
<a href="<?= htmlspecialchars(CoreCMS\Core\Url::to('/admin/'), ENT_QUOTES, 'UTF-8') ?>"><?= $frontText('frontend.nav.admin', 'Administration') ?></a></nav><div class="language-switcher" aria-label="<?= $frontText('frontend.language_switcher', 'Select language') ?>"><?php foreach ($availableLocales as $language): $languageQuery = $_GET; $languageQuery['lang'] = $language['locale_code']; ?><a href="<?= htmlspecialchars(CoreCMS\Core\Url::to($languagePaths[$language['locale_code']] ?? $path) . '?' . http_build_query($languageQuery), ENT_QUOTES, 'UTF-8') ?>" lang="<?= htmlspecialchars($language['locale_code'], ENT_QUOTES, 'UTF-8') ?>" hreflang="<?= htmlspecialchars($language['locale_code'], ENT_QUOTES, 'UTF-8') ?>"<?= $language['locale_code'] === $currentLocale ? ' aria-current="true"' : '' ?>><?= htmlspecialchars($language['name'], ENT_QUOTES, 'UTF-8') ?></a><?php endforeach; ?></div></header>
<main class="container"><?= $contentHtml ?></main><footer><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?> · <?= date('Y') ?></footer><script src="<?= htmlspecialchars(CoreCMS\Core\Url::asset('js/responsive-nav.js'), ENT_QUOTES, 'UTF-8') ?>?v=3" defer></script></body></html>
