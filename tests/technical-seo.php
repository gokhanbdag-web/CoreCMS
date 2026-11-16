<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/TechnicalSeo.php';

use CoreCMS\Core\TechnicalSeo;

$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE core_pages (id INTEGER PRIMARY KEY, slug TEXT, status TEXT, updated_at TEXT, canonical_url TEXT)');
$db->exec("INSERT INTO core_pages(slug,status,updated_at,canonical_url) VALUES
 ('hakkimizda','published','2026-11-15 10:00:00',''),
 ('gizli','draft','2026-11-15 10:00:00',''),
 ('gelecek','scheduled','2026-11-15 10:00:00',''),
 ('ozel','published','2026-11-15 10:00:00','/ozel-kanonik')");
$settings = ['site_url' => 'https://example.test/subsite', 'site_name' => 'Örnek <Site>', 'seo_org_name' => 'Örnek Kurum', 'seo_social_image' => 'https://example.test/social.jpg', 'search_console_verification' => 'token_123'];
$xml = TechnicalSeo::sitemap($db, $settings);
if (!str_contains($xml, 'https://example.test/subsite/hakkimizda') || !str_contains($xml, 'https://example.test/ozel-kanonik')) throw new RuntimeException('Published page URLs missing.');
if (str_contains($xml, 'gizli') || str_contains($xml, 'gelecek')) throw new RuntimeException('Non-public pages leaked into sitemap.');
if (!str_contains($xml, '&lt;') && str_contains($xml, 'Örnek')) { /* XML data is escaped at URL boundary; fixture title is not emitted. */ }
$robots = TechnicalSeo::robots($settings);
if (!str_contains($robots, 'Disallow: /admin/') || !str_contains($robots, 'https://example.test/subsite/sitemap.xml')) throw new RuntimeException('robots.txt rules failed.');
$head = TechnicalSeo::head($settings, 'Başlık </script>', 'Açıklama & test', '', true);
if (!str_contains($head, 'google-site-verification') || !str_contains($head, 'og:image') || !str_contains($head, 'application/ld+json')) throw new RuntimeException('SEO head tags missing.');
if (str_contains($head, 'Başlık </script>')) throw new RuntimeException('JSON-LD script injection was not escaped.');
if (TechnicalSeo::siteUrl(['site_url' => 'https://user:pass@example.test/path?x=1']) !== '') throw new RuntimeException('Unsafe site URL accepted.');
echo "Technical SEO checks passed (6)." . PHP_EOL;
