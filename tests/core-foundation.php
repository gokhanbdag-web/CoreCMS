<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/Url.php';
require dirname(__DIR__) . '/app/Core/Csrf.php';

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
};

$_SERVER['SCRIPT_NAME'] = '/corecms/index.php';
$_SERVER['REQUEST_URI'] = '/corecms/about?preview=1';
$assert(CoreCMS\Core\Url::basePath() === '/corecms', 'subdirectory base path');
$assert(CoreCMS\Core\Url::path() === '/about', 'subdirectory request path');
$assert(CoreCMS\Core\Url::to('/admin/') === '/corecms/admin/', 'subdirectory URL generation');
$assert(CoreCMS\Core\Url::asset('css/site.css') === '/corecms/assets/css/site.css', 'subdirectory asset URL');

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/about';
$assert(CoreCMS\Core\Url::basePath() === '', 'domain root base path');
$assert(CoreCMS\Core\Url::path() === '/about', 'domain root request path');

$_SERVER['SCRIPT_NAME'] = '/shop/admin/index.php';
$_SERVER['REQUEST_URI'] = '/shop/admin/';
$assert(CoreCMS\Core\Url::basePath() === '/shop', 'admin subdirectory base path');

$_SERVER['SCRIPT_NAME'] = '/shop/admin/roles.php';
$_SERVER['REQUEST_URI'] = '/shop/admin/roles.php';
$assert(CoreCMS\Core\Url::basePath() === '/shop', 'admin subpage base path');
$assert(CoreCMS\Core\Url::asset('css/site.css') === '/shop/assets/css/site.css', 'admin subpage assets resolve from installation root');

$_SERVER['SCRIPT_NAME'] = '/shop/installer/index.php';
$assert(CoreCMS\Core\Url::basePath() === '/shop', 'installer subdirectory base path');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_id('corecms-foundation-test');
    session_start();
}
$token = CoreCMS\Core\Csrf::token();
$assert(strlen($token) === 64, 'CSRF token entropy representation');
$assert(CoreCMS\Core\Csrf::verify($token), 'CSRF valid token');
$assert(!CoreCMS\Core\Csrf::verify('invalid-token'), 'CSRF rejects invalid token');

echo 'Core foundation checks passed (' . $checks . ').' . PHP_EOL;
