<?php
declare(strict_types=1);

namespace CoreCMS\\Core;

use PDO;
use RuntimeException;

final class ContentManager
{
    public function __construct(private PDO $db) {}

    public static function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }

    private function safeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || preg_match('/[\\r\\n\\x00-\\x1F]/', $url)) {
            throw new RuntimeException('Menü bağlantısı geçersiz.');
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) return $url;
        if (preg_match('#^https://[^/\\s]+(?:/[^\\s]*)?$#i', $url)) return $url;
        throw new RuntimeException('Bağlantı yalnızca site içi /yol veya HTTPS adresi olabilir.');
    }

    public function savePage(?int $id, string $title, string $slug, string $body, string $status): int
    {
        $title = trim($title);
        $slug = self::slug($slug !== '' ? $slug : $title);
        if ($title === '' || mb_strlen($title) > 255) throw new RuntimeException('Sayfa başlığı zorunludur (en fazla 255 karakter).');
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) throw new RuntimeException('Sayfa bağlantısı geçersiz.');
        if (!in_array($status, ['draft', 'published'], true)) throw new RuntimeException('Yayın durumu geçersiz.');
        $body = htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($id !== null) {
            $check = $this->db->prepare('SELECT source_module FROM core_pages WHERE id=?');
            $check->execute([$id]);
            $source = $check->fetchColumn();
            if ($source === false) throw new RuntimeException('Sayfa bulunamadı.');
            $stmt = $this->db->prepare('UPDATE core_pages SET title=?,slug=?,body=?,status=? WHERE id=?');
            $stmt->execute([$title, $slug, $body, $status, $id]);
            return $id;
        }
        $stmt = $this->db->prepare('INSERT INTO core_pages (title,slug,body,status,source_module,source_demo) VALUES (?,?,?,?,NULL,NULL)');
        $stmt->execute([$title, $slug, $body, $status]);
        return (int)$this->db->lastInsertId();
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
        if ($label === '' || mb_strlen($label) > 190) throw new RuntimeException('Menü etiketi zorunludur (en fazla 190 karakter).');
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
