<?php
declare(strict_types=1);

namespace CoreCMS\Core;

use PDO;
use RuntimeException;

final class ContentManager
{
    public function __construct(private PDO $db) {}

    private static function charLength(string $value): int
    {
        return preg_match_all('/./us', $value, $matches) ?: 0;
    }

    public static function slug(string $value): string
    {
        $value = strtr($value, ['ç'=>'c','Ç'=>'c','ğ'=>'g','Ğ'=>'g','ı'=>'i','İ'=>'i','ö'=>'o','Ö'=>'o','ş'=>'s','Ş'=>'s','ü'=>'u','Ü'=>'u']);
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }

    private function safeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || preg_match('/[\r\n\x00-\x1F]/', $url)) {
            throw new RuntimeException('Menü bağlantısı geçersiz.');
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) return $url;
        if (preg_match('#^https://[^/\s]+(?:/[^\s]*)?$#i', $url)) return $url;
        throw new RuntimeException('Bağlantı yalnızca site içi /yol veya HTTPS adresi olabilir.');
    }

    public function savePage(?int $id, string $title, string $slug, string $body, string $status, string $seoTitle = '', string $seoDescription = '', string $canonicalUrl = '', ?string $scheduledAt = null, ?int $socialImageMediaId = null): int
    {
        $title = trim($title);
        $slug = self::slug($slug !== '' ? $slug : $title);
        if ($title === '' || self::charLength($title) > 255) throw new RuntimeException('Sayfa başlığı zorunludur (en fazla 255 karakter).');
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) throw new RuntimeException('Sayfa bağlantısı geçersiz.');
        if (!in_array($status, ['draft', 'published', 'scheduled'], true)) throw new RuntimeException('Yayın durumu geçersiz.');
        $seoTitle = trim($seoTitle); $seoDescription = trim($seoDescription); $canonicalUrl = trim($canonicalUrl);
        if (self::charLength($seoTitle) > 255 || self::charLength($seoDescription) > 320 || strlen($canonicalUrl) > 500) throw new RuntimeException('SEO alanlarından biri izin verilen uzunluğu aşıyor.');
        if ($canonicalUrl !== '' && !preg_match('#^(https://[^/\\s]+(?:/[^\\s]*)?|/(?!/)[^\\s]*)$#i', $canonicalUrl)) throw new RuntimeException('Canonical adresi HTTPS veya site içi yol olmalı.');
        if ($status === 'scheduled') { $scheduledAt = $scheduledAt !== null ? str_replace('T', ' ', $scheduledAt) : null; if ($scheduledAt === null || !preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}(?::\\d{2})?$/', $scheduledAt) || strtotime($scheduledAt) === false || strtotime($scheduledAt) <= time()) throw new RuntimeException('Planlı yayın için gelecekte geçerli bir tarih ve saat seçin.'); } else { $scheduledAt = null; }
        if ($socialImageMediaId !== null && $socialImageMediaId > 0) { $mediaCheck = $this->db->prepare('SELECT id FROM core_media WHERE id=?'); $mediaCheck->execute([$socialImageMediaId]); if (!$mediaCheck->fetchColumn()) throw new RuntimeException('Sosyal paylaşım görseli medya kütüphanesinde bulunamadı.'); } else { $socialImageMediaId = null; }
        $body = htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($id !== null) {
            $check = $this->db->prepare('SELECT source_module FROM core_pages WHERE id=?');
            $check->execute([$id]);
            $source = $check->fetchColumn();
            if ($source === false) throw new RuntimeException('Sayfa bulunamadı.');
            $stmt = $this->db->prepare('UPDATE core_pages SET title=?,slug=?,body=?,status=?,seo_title=?,seo_description=?,canonical_url=?,scheduled_at=?,social_image_media_id=? WHERE id=?');
            $stmt->execute([$title, $slug, $body, $status, $seoTitle ?: null, $seoDescription ?: null, $canonicalUrl ?: null, $scheduledAt, $socialImageMediaId, $id]);
            return $id;
        }
        $stmt = $this->db->prepare('INSERT INTO core_pages (title,slug,body,status,source_module,source_demo,seo_title,seo_description,canonical_url,scheduled_at,social_image_media_id) VALUES (?,?,?,?,NULL,NULL,?,?,?,?,?)');
        $stmt->execute([$title, $slug, $body, $status, $seoTitle ?: null, $seoDescription ?: null, $canonicalUrl ?: null, $scheduledAt, $socialImageMediaId]);
        return (int)$this->db->lastInsertId();
    }

    public function pageTranslation(int $pageId, string $locale): ?array
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM core_page_translations WHERE page_id=? AND locale_code=? LIMIT 1');
            $stmt->execute([$pageId, $locale]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (\PDOException) {
            return null;
        }
    }

    public function savePageTranslation(int $pageId, string $locale, string $title, string $slug, string $body, string $seoTitle = '', string $seoDescription = '', string $canonicalUrl = '', ?int $socialImageMediaId = null): void
    {
        $pageCheck = $this->db->prepare('SELECT id FROM core_pages WHERE id=?');
        $pageCheck->execute([$pageId]);
        if (!$pageCheck->fetchColumn()) throw new RuntimeException('Sayfa bulunamadı.');

        $languageCheck = $this->db->prepare('SELECT enabled,is_default FROM core_languages WHERE locale_code=?');
        $languageCheck->execute([$locale]);
        $language = $languageCheck->fetch(PDO::FETCH_ASSOC);
        if (!$language || (int)$language['enabled'] !== 1) throw new RuntimeException('Çeviri dili etkin olmalıdır.');
        if ((int)$language['is_default'] === 1) throw new RuntimeException('Ana dilin içeriği doğrudan düzenlenmelidir.');

        $title = trim($title);
        $slug = self::slug($slug !== '' ? $slug : $title);
        if ($title === '' || self::charLength($title) > 255) throw new RuntimeException('Çeviri sayfa başlığı zorunludur (en fazla 255 karakter).');
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) throw new RuntimeException('Çeviri sayfa bağlantısı geçersiz.');
        $baseSlugCheck = $this->db->prepare('SELECT id FROM core_pages WHERE slug=? AND id<>? LIMIT 1');
        $baseSlugCheck->execute([$slug, $pageId]);
        if ($baseSlugCheck->fetchColumn()) throw new RuntimeException('Çeviri sayfa bağlantısı başka bir sayfa tarafından kullanılıyor.');

        $seoTitle = trim($seoTitle);
        $seoDescription = trim($seoDescription);
        $canonicalUrl = trim($canonicalUrl);
        if (self::charLength($seoTitle) > 255 || self::charLength($seoDescription) > 320 || strlen($canonicalUrl) > 500) {
            throw new RuntimeException('SEO alanlarından biri izin verilen uzunluğu aşıyor.');
        }
        if ($canonicalUrl !== '' && !preg_match('#^(https://[^/\\s]+(?:/[^\\s]*)?|/(?!/)[^\\s]*)$#i', $canonicalUrl)) {
            throw new RuntimeException('Canonical adresi HTTPS veya site içi yol olmalı.');
        }
        if ($socialImageMediaId !== null && $socialImageMediaId > 0) {
            $mediaCheck = $this->db->prepare('SELECT id FROM core_media WHERE id=?');
            $mediaCheck->execute([$socialImageMediaId]);
            if (!$mediaCheck->fetchColumn()) throw new RuntimeException('Sosyal paylaşım görseli medya kütüphanesinde bulunamadı.');
        } else {
            $socialImageMediaId = null;
        }

        $body = htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? 'INSERT INTO core_page_translations(page_id,locale_code,title,slug,body,seo_title,seo_description,canonical_url,social_image_media_id) VALUES(?,?,?,?,?,?,?,?,?) ON CONFLICT(page_id,locale_code) DO UPDATE SET title=excluded.title,slug=excluded.slug,body=excluded.body,seo_title=excluded.seo_title,seo_description=excluded.seo_description,canonical_url=excluded.canonical_url,social_image_media_id=excluded.social_image_media_id,updated_at=CURRENT_TIMESTAMP'
            : 'INSERT INTO core_page_translations(page_id,locale_code,title,slug,body,seo_title,seo_description,canonical_url,social_image_media_id) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),slug=VALUES(slug),body=VALUES(body),seo_title=VALUES(seo_title),seo_description=VALUES(seo_description),canonical_url=VALUES(canonical_url),social_image_media_id=VALUES(social_image_media_id),updated_at=CURRENT_TIMESTAMP';
        $this->db->prepare($sql)->execute([$pageId, $locale, $title, $slug, $body, $seoTitle ?: null, $seoDescription ?: null, $canonicalUrl ?: null, $socialImageMediaId]);
    }

    public function menuTranslation(int $menuId, string $locale): ?array
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM core_menu_translations WHERE menu_id=? AND locale_code=? LIMIT 1');
            $stmt->execute([$menuId, $locale]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (\PDOException) {
            return null;
        }
    }

    public function publishedPage(string $slug, string $locale): ?array
    {
        $stmt = $this->db->prepare("SELECT p.id,p.slug AS base_slug,
            COALESCE(pt.slug,p.slug) AS localized_slug,
            COALESCE(pt.title,p.title) AS title,
            COALESCE(pt.body,p.body) AS body,
            COALESCE(pt.seo_title,p.seo_title) AS seo_title,
            COALESCE(pt.seo_description,p.seo_description) AS seo_description,
            COALESCE(pt.canonical_url,p.canonical_url) AS canonical_url,
            COALESCE(pt.social_image_media_id,p.social_image_media_id) AS social_image_media_id,
            m.file_path AS social_image_path,m.alt_text AS social_image_alt
            FROM core_pages p
            LEFT JOIN core_page_translations pt ON pt.page_id=p.id AND pt.locale_code=?
            LEFT JOIN core_media m ON m.id=COALESCE(pt.social_image_media_id,p.social_image_media_id)
            WHERE p.status='published' AND (p.slug=? OR pt.slug=?) LIMIT 1");
        try {
            $stmt->execute([$locale, $slug, $slug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (\PDOException) {
            $fallback = $this->db->prepare("SELECT p.id,p.slug AS base_slug,p.slug AS localized_slug,p.title,p.body,p.seo_title,p.seo_description,p.canonical_url,p.social_image_media_id,m.file_path AS social_image_path,m.alt_text AS social_image_alt FROM core_pages p LEFT JOIN core_media m ON m.id=p.social_image_media_id WHERE p.slug=? AND p.status='published' LIMIT 1");
            $fallback->execute([$slug]);
            $row = $fallback->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        }
    }

    public function menusForLocale(string $menuKey, string $locale): array
    {
        $stmt = $this->db->prepare('SELECT m.id,COALESCE(mt.label,m.label) AS label,COALESCE(mt.url,m.url) AS url FROM core_menus m LEFT JOIN core_menu_translations mt ON mt.menu_id=m.id AND mt.locale_code=? WHERE m.menu_key=? ORDER BY m.sort_order,m.id');
        try {
            $stmt->execute([$locale, $menuKey]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException) {
            $fallback = $this->db->prepare('SELECT id,label,url FROM core_menus WHERE menu_key=? ORDER BY sort_order,id');
            $fallback->execute([$menuKey]);
            return $fallback->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    public function pageSlugForLocale(int $pageId, string $locale): ?string
    {
        $stmt = $this->db->prepare('SELECT COALESCE((SELECT slug FROM core_page_translations WHERE page_id=? AND locale_code=?),(SELECT slug FROM core_pages WHERE id=?))');
        try {
            $stmt->execute([$pageId, $locale, $pageId]);
            $slug = $stmt->fetchColumn();
        } catch (\PDOException) {
            $fallback = $this->db->prepare('SELECT slug FROM core_pages WHERE id=?');
            $fallback->execute([$pageId]);
            $slug = $fallback->fetchColumn();
        }
        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    public function saveMenuTranslation(int $menuId, string $locale, string $label, string $url): void
    {
        $menuCheck = $this->db->prepare('SELECT id FROM core_menus WHERE id=?');
        $menuCheck->execute([$menuId]);
        if (!$menuCheck->fetchColumn()) throw new RuntimeException('Menü öğesi bulunamadı.');

        $languageCheck = $this->db->prepare('SELECT enabled,is_default FROM core_languages WHERE locale_code=?');
        $languageCheck->execute([$locale]);
        $language = $languageCheck->fetch(PDO::FETCH_ASSOC);
        if (!$language || (int)$language['enabled'] !== 1) throw new RuntimeException('Çeviri dili etkin olmalıdır.');
        if ((int)$language['is_default'] === 1) throw new RuntimeException('Ana dilin içeriği doğrudan düzenlenmelidir.');

        $label = trim($label);
        if ($label === '' || self::charLength($label) > 190) throw new RuntimeException('Menü çeviri etiketi zorunludur (en fazla 190 karakter).');
        $url = $this->safeUrl($url);
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? 'INSERT INTO core_menu_translations(menu_id,locale_code,label,url) VALUES(?,?,?,?) ON CONFLICT(menu_id,locale_code) DO UPDATE SET label=excluded.label,url=excluded.url,updated_at=CURRENT_TIMESTAMP'
            : 'INSERT INTO core_menu_translations(menu_id,locale_code,label,url) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE label=VALUES(label),url=VALUES(url),updated_at=CURRENT_TIMESTAMP';
        $this->db->prepare($sql)->execute([$menuId, $locale, $label, $url]);
    }

    public function detachPage(int $id): void
    {
        $stmt = $this->db->prepare('SELECT source_module FROM core_pages WHERE id=?');
        $stmt->execute([$id]);
        $source = $stmt->fetchColumn();
        if ($source === false) throw new RuntimeException('Sayfa bulunamadı.');
        if ($source === null || $source === '') throw new RuntimeException('Sayfa zaten yöneticiye ait.');
        // Keep the module registry key: this prevents a later module sync from recreating the detached item.
        $this->db->prepare('UPDATE core_pages SET source_module=NULL,source_demo=NULL WHERE id=?')->execute([$id]);
    }

    public function deletePage(int $id): void
    {
        $stmt = $this->db->prepare('SELECT source_module FROM core_pages WHERE id=?');
        $stmt->execute([$id]);
        $source = $stmt->fetchColumn();
        if ($source === false) throw new RuntimeException('Sayfa bulunamadı.');
        if ($source !== null && $source !== '') throw new RuntimeException('Modül tarafından sağlanan sayfalar silinemez; önce modül içeriğinden ayırın.');
        $this->db->prepare('DELETE FROM core_pages WHERE id=?')->execute([$id]);
    }

    public function saveMenu(?int $id, string $menuKey, string $label, string $url, int $sortOrder): int
    {
        $menuKey = trim($menuKey);
        $label = trim($label);
        if (!preg_match('/^[a-zA-Z0-9._-]{1,100}$/', $menuKey)) throw new RuntimeException('Menü anahtarı 1-100 karakter olmalı; harf, rakam, nokta, tire ve alt çizgi kullanılabilir.');
        if ($label === '' || self::charLength($label) > 190) throw new RuntimeException('Menü etiketi zorunludur (en fazla 190 karakter).');
        $url = $this->safeUrl($url);
        if ($id !== null) {
            $check = $this->db->prepare('SELECT source_module FROM core_menus WHERE id=?');
            $check->execute([$id]);
            $source = $check->fetchColumn();
            if ($source === false) throw new RuntimeException('Menü öğesi bulunamadı.');
            $stmt = $this->db->prepare('UPDATE core_menus SET menu_key=?,label=?,url=?,sort_order=? WHERE id=?');
            $stmt->execute([$menuKey, $label, $url, $sortOrder, $id]);
            return $id;
        }
        $stmt = $this->db->prepare('INSERT INTO core_menus (menu_key,label,url,sort_order,source_module,source_demo) VALUES (?,?,?,?,NULL,NULL)');
        $stmt->execute([$menuKey, $label, $url, $sortOrder]);
        return (int)$this->db->lastInsertId();
    }

    public function detachMenu(int $id): void
    {
        $stmt = $this->db->prepare('SELECT source_module FROM core_menus WHERE id=?');
        $stmt->execute([$id]);
        $source = $stmt->fetchColumn();
        if ($source === false) throw new RuntimeException('Menü öğesi bulunamadı.');
        if ($source === null || $source === '') throw new RuntimeException('Menü öğesi zaten yöneticiye ait.');
        // Retain the registry entry so module provisioning remains idempotent after detachment.
        $this->db->prepare('UPDATE core_menus SET source_module=NULL,source_demo=NULL WHERE id=?')->execute([$id]);
    }

    public function deleteMenu(int $id): void
    {
        $stmt = $this->db->prepare('SELECT source_module FROM core_menus WHERE id=?');
        $stmt->execute([$id]);
        $source = $stmt->fetchColumn();
        if ($source === false) throw new RuntimeException('Menü öğesi bulunamadı.');
        if ($source !== null && $source !== '') throw new RuntimeException('Modül tarafından sağlanan menü öğeleri silinemez.');
        $this->db->prepare('DELETE FROM core_menus WHERE id=?')->execute([$id]);
    }
}
