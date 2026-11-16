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
