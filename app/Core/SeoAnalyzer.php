<?php
declare(strict_types=1);

namespace CoreCMS\Core;

use PDO;

final class SeoAnalyzer
{
    public static function analyze(array $page, array $mediaAltById = []): array
    {
        $title = trim((string)(($page['seo_title'] ?? '') ?: ($page['title'] ?? '')));
        $description = trim((string)($page['seo_description'] ?? ''));
        $body = html_entity_decode((string)($page['body'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags(preg_replace('/\[\[media:[^\]]+\]\]/', ' ', $body) ?? $body) ?? ''));
        $words = $plain === '' ? 0 : count(preg_split('/\s+/u', $plain) ?: []);
        preg_match_all('/^#{1,3}\s+.+$/mu', $body, $headings);
        preg_match_all('/\[\[media:([1-9][0-9]*)/', $body, $images);
        preg_match_all('/\[([^\]]+)\]\((\/[^)\s]*)\)/u', $body, $links);
        $issues = [];
        $score = 0;
        $titleLength = preg_match_all('/./us', $title) ?: 0;
        if ($titleLength >= 30 && $titleLength <= 60) $score += 20;
        elseif ($title !== '') { $score += 10; $issues[] = 'SEO başlığı 30–60 karakter aralığında olmalı.'; }
        else $issues[] = 'SEO başlığı veya sayfa başlığı eksik.';
        $descriptionLength = preg_match_all('/./us', $description) ?: 0;
        if ($descriptionLength >= 70 && $descriptionLength <= 160) $score += 20;
        elseif ($description !== '') { $score += 10; $issues[] = 'Meta açıklama 70–160 karakter aralığında olmalı.'; }
        else $issues[] = 'Meta açıklama eksik.';
        if (trim((string)($page['slug'] ?? '')) !== '') $score += 5;
        if (trim((string)($page['canonical_url'] ?? '')) !== '') $score += 10;
        else $issues[] = 'Canonical adresi tanımlanmamış.';
        if ($words >= 300) $score += 15;
        elseif ($words >= 100) $score += 8;
        else $issues[] = 'İçerik kısa; en az 300 kelime hedefleyin.';
        if (count($headings[0] ?? []) >= 1) $score += 10;
        else $issues[] = 'İçeriğe en az bir ara başlık ekleyin.';
        $imageIds = array_values(array_unique($images[1] ?? []));
        if (!$imageIds) $score += 10;
        else {
            $allHaveAlt = true;
            foreach ($imageIds as $id) if (trim((string)($mediaAltById[(int)$id] ?? '')) === '') $allHaveAlt = false;
            if ($allHaveAlt) $score += 10;
            else $issues[] = 'İçerikte alt metni eksik görseller var.';
        }
        if (count($links[2] ?? []) >= 1) $score += 10;
        else $issues[] = 'İlgili bir iç sayfaya bağlantı ekleyin.';
        return ['score'=>min(100,$score),'issues'=>$issues,'word_count'=>$words,'image_count'=>count($imageIds),'internal_link_count'=>count($links[2] ?? [])];
    }

    public static function audit(PDO $db): array
    {
        $pages = $db->query("SELECT id,title,slug,body,status,seo_title,seo_description,canonical_url FROM core_pages WHERE status='published' ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);
        $known = [];
        foreach ($pages as $page) $known[trim((string)$page['slug'],'/')] = true;
        $broken = [];
        foreach ($pages as $page) {
            $body = html_entity_decode((string)($page['body'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            preg_match_all('/\[[^\]]+\]\((\/[^)\s]*)\)/u', $body, $matches);
            foreach ($matches[1] ?? [] as $target) {
                $path = (string)(parse_url($target, PHP_URL_PATH) ?? '');
                $slug = trim($path,'/');
                if ($slug === '' || isset($known[$slug])) continue;
                $broken[] = ['page_title'=>(string)$page['title'],'source_slug'=>(string)$page['slug'],'target'=>$target];
            }
        }
        return ['pages'=>$pages,'broken_links'=>$broken];
    }
}
