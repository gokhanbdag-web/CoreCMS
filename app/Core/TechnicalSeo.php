<?php
declare(strict_types=1);

namespace CoreCMS\Core;

use PDO;
use RuntimeException;

final class TechnicalSeo
{
    public static function siteUrl(array $settings): string
    {
        $value = trim((string)($settings['site_url'] ?? ''));
        $parts = parse_url($value);
        if (!is_array($parts) || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])) {
            return '';
        }
        $host = strtolower((string)$parts['host']);
        if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?|\[[0-9a-f:]+\])$/i', $host)) return '';
        $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
        $path = rtrim((string)($parts['path'] ?? ''), '/');
        return strtolower((string)$parts['scheme']) . '://' . $host . $port . $path;
    }

    private static function absolute(string $url, string $base): string
    {
        $url = trim($url);
        if ($url === '') return '';
        if (preg_match('~^https?://~i', $url)) {
            $parts = parse_url($url);
            if (!is_array($parts) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) return '';
            return $url;
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) return $base . $url;
        return '';
    }

    public static function sitemap(PDO $db, array $settings): string
    {
        $base = self::siteUrl($settings);
        if ($base === '') throw new RuntimeException('SEO için önce geçerli Site URL adresini kaydedin.');
        $items = [['loc' => $base . '/', 'lastmod' => date('Y-m-d')]];
        $stmt = $db->query("SELECT slug,updated_at,canonical_url FROM core_pages WHERE status='published' ORDER BY id ASC");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $page) {
            $path = '/' . ltrim((string)$page['slug'], '/');
            $loc = self::absolute((string)($page['canonical_url'] ?? ''), $base);
            if ($loc === '') $loc = $base . $path;
            $item = ['loc' => $loc];
            if (!empty($page['updated_at'])) {
                $time = strtotime((string)$page['updated_at']);
                if ($time !== false) $item['lastmod'] = date('Y-m-d', $time);
            }
            $items[] = $item;
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($items as $item) {
            $xml .= '<url><loc>' . htmlspecialchars($item['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>';
            if (!empty($item['lastmod'])) $xml .= '<lastmod>' . $item['lastmod'] . '</lastmod>';
            $xml .= '</url>';
        }
        return $xml . '</urlset>';
    }

    public static function robots(array $settings): string
    {
        $base = self::siteUrl($settings);
        $lines = ["User-agent: *", "Disallow: /admin/", "Disallow: /installer/"];
        if ($base !== '') $lines[] = 'Sitemap: ' . $base . '/sitemap.xml';
        return implode("\n", $lines) . "\n";
    }

    public static function head(array $settings, string $title, string $description, string $canonical, bool $isHome = false): string
    {
        $base = self::siteUrl($settings);
        $canonical = self::absolute($canonical, $base);
        if ($canonical === '' && $base !== '') $canonical = $isHome ? $base . '/' : '';
        $siteName = (string)($settings['site_name'] ?? '');
        $orgName = trim((string)($settings['seo_org_name'] ?? '')) ?: $siteName;
        $image = self::absolute((string)($settings['seo_social_image'] ?? ''), $base);
        $verification = trim((string)($settings['search_console_verification'] ?? ''));
        $html = '';
        if ($canonical !== '') $html .= '<link rel="canonical" href="' . htmlspecialchars($canonical, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . "\n";
        if ($description !== '') {
            $html .= '<meta name="description" content="' . htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . "\n";
            $html .= '<meta property="og:description" content="' . htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . "\n";
            $html .= '<meta name="twitter:description" content="' . htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . "\n";
        }
        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html .= '<meta property="og:type" content="' . ($isHome ? 'website' : 'article') . '">' . "\n";
        $html .= '<meta property="og:title" content="' . $safeTitle . '">' . "\n";
        $html .= '<meta property="og:site_name" content="' . htmlspecialchars($siteName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . "\n";
        $html .= '<meta name="twitter:card" content="' . ($image !== '' ? 'summary_large_image' : 'summary') . '">' . "\n";
        $html .= '<meta name="twitter:title" content="' . $safeTitle . '">' . "\n";
        if ($image !== '') {
            $safeImage = htmlspecialchars($image, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html .= '<meta property="og:image" content="' . $safeImage . '">' . "\n";
            $html .= '<meta name="twitter:image" content="' . $safeImage . '">' . "\n";
        }
        if ($verification !== '' && preg_match('/^[A-Za-z0-9_-]{1,200}$/', $verification)) {
            $html .= '<meta name="google-site-verification" content="' . htmlspecialchars($verification, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $orgName,
        ];
        if ($base !== '') $schema['url'] = $base . '/';
        $graph = [
            '@context' => 'https://schema.org',
            '@graph' => [
                $schema,
                ['@type' => 'WebSite', 'name' => $siteName, 'url' => $base !== '' ? $base . '/' : ''],
                ['@type' => $isHome ? 'WebPage' : 'WebPage', 'name' => $title, 'description' => $description, 'url' => $canonical],
            ],
        ];
        $json = json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        if (is_string($json)) $html .= '<script type="application/ld+json">' . $json . '</script>' . "\n";
        return $html;
    }
}
