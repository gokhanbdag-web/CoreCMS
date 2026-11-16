<?php
declare(strict_types=1);

namespace CoreCMS\Core;

use PDO;
use RuntimeException;

final class RedirectManager
{
    public function __construct(private PDO $db) {}

    private static function path(string $value, bool $allowRoot = false): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 500 || preg_match('/[\x00-\x20\x7F\\\\]/', $value)
            || !str_starts_with($value, '/') || str_starts_with($value, '//')
            || str_contains($value, '?') || str_contains($value, '#')
            || preg_match('~(?:^|/)\.\.?(/|$)|%2e|%2f|%5c~i', $value)) {
            throw new RuntimeException('Yönlendirme adresi güvenli bir site içi yol olmalı.');
        }
        if (!$allowRoot && $value === '/') throw new RuntimeException('Kaynak adres kök dizin olamaz.');
        if ($value === '/admin' || str_starts_with($value, '/admin/') || $value === '/installer' || str_starts_with($value, '/installer/') || in_array($value, ['/sitemap.xml','/robots.txt'], true)) {
            throw new RuntimeException('Bu sistem adresi yönlendirme kaynağı olarak kullanılamaz.');
        }
        return $value;
    }

    public function save(?int $id, string $source, string $target, int $code = 301): int
    {
        $source = self::path($source);
        $target = self::path($target, true);
        if ($source === $target) throw new RuntimeException('Kaynak ve hedef adres aynı olamaz.');
        if (!in_array($code, [301,302], true)) throw new RuntimeException('Yalnızca 301 veya 302 yönlendirmesi kullanılabilir.');

        $sql = 'SELECT id FROM core_redirects WHERE source_path=?';
        $check = $this->db->prepare($sql);
        $check->execute([$source]);
        $existing = $check->fetchColumn();
        if ($existing !== false && (int)$existing !== (int)$id) throw new RuntimeException('Bu kaynak adres için zaten yönlendirme var.');

        $chain = $this->db->prepare('SELECT source_path FROM core_redirects WHERE source_path=? AND id<>? LIMIT 1');
        $chain->execute([$target, $id ?? 0]);
        if ($chain->fetchColumn() !== false) throw new RuntimeException('Hedef adres başka bir yönlendirmeye gidiyor; yönlendirme zinciri oluşturulamaz.');

        if ($id !== null && $id > 0) {
            $stmt = $this->db->prepare('UPDATE core_redirects SET source_path=?,target_path=?,http_code=? WHERE id=?');
            $stmt->execute([$source,$target,$code,$id]);
            if ($stmt->rowCount() < 1) {
                $exists = $this->db->prepare('SELECT id FROM core_redirects WHERE id=?');
                $exists->execute([$id]);
                if (!$exists->fetchColumn()) throw new RuntimeException('Yönlendirme kaydı bulunamadı.');
            }
            return $id;
        }
        $stmt = $this->db->prepare('INSERT INTO core_redirects(source_path,target_path,http_code) VALUES(?,?,?)');
        $stmt->execute([$source,$target,$code]);
        return (int)$this->db->lastInsertId();
    }

    public function resolve(string $requestPath): ?array
    {
        $stmt = $this->db->prepare('SELECT target_path,http_code FROM core_redirects WHERE source_path=? LIMIT 1');
        $stmt->execute([$requestPath]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function all(): array
    {
        return $this->db->query('SELECT id,source_path,target_path,http_code,created_at,updated_at FROM core_redirects ORDER BY source_path')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id,source_path,target_path,http_code FROM core_redirects WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM core_redirects WHERE id=?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() < 1) throw new RuntimeException('Yönlendirme kaydı bulunamadı.');
    }
}
