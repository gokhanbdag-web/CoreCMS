<?php
declare(strict_types=1);

namespace CoreCMS\Core;

use PDO;
use RuntimeException;
use finfo;

final class MediaLibrary
{
    private const MAX_BYTES = 8388608;
    private const TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private PDO $db,
        private string $projectRoot,
        private string $publicBase = ''
    ) {
        $this->projectRoot = rtrim($this->projectRoot, DIRECTORY_SEPARATOR);
    }

    public function list(string $search = '', int $limit = 60): array
    {
        $limit = max(1, min(100, $limit));
        $sql = 'SELECT id,file_name,file_path,mime_type,file_size,width,height,alt_text,created_at FROM core_media';
        $params = [];
        if (trim($search) !== '') {
            $sql .= ' WHERE file_name LIKE ? OR alt_text LIKE ?';
            $term = '%' . trim($search) . '%';
            $params = [$term, $term];
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function upload(array $file, int $userId = 0, string $altText = ''): int
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadError((int)($file['error'] ?? UPLOAD_ERR_NO_FILE)));
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Geçersiz yükleme kaynağı.');
        }
        if ($size < 1 || $size > self::MAX_BYTES) {
            throw new RuntimeException('Dosya boş olamaz ve en fazla 8 MB olabilir.');
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmp);
        if (!isset(self::TYPES[$mime])) {
            throw new RuntimeException('Yalnızca JPEG, PNG, GIF ve WebP görselleri yüklenebilir.');
        }
        $image = @getimagesize($tmp);
        if (!$image || ($image['mime'] ?? '') !== $mime) {
            throw new RuntimeException('Dosya geçerli bir görsel değil.');
        }

        $directory = $this->mediaDirectory();
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Medya klasörü oluşturulamadı.');
        }
        $this->protectDirectory($directory);

        $extension = self::TYPES[$mime];
        $name = bin2hex(random_bytes(20)) . '.' . $extension;
        $target = $directory . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file($tmp, $target)) {
            throw new RuntimeException('Görsel sunucuya kaydedilemedi.');
        }
        @chmod($target, 0644);
        $relative = 'uploads/media/' . $name;
        $altText = trim(strip_tags($altText));
        if (function_exists('mb_substr')) $altText = mb_substr($altText, 0, 255);
        else $altText = substr($altText, 0, 255);

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO core_media (file_name,file_path,mime_type,file_size,width,height,alt_text,uploaded_by) VALUES (?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([$name, $relative, $mime, $size, (int)$image[0], (int)$image[1], $altText, $userId > 0 ? $userId : null]);
            return (int)$this->db->lastInsertId();
        } catch (\Throwable $e) {
            @unlink($target);
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('SELECT file_name,file_path FROM core_media WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Medya kaydı bulunamadı.');
        $name = (string)$row['file_name'];
        if (!preg_match('/\\A[a-f0-9]{40}\\.(?:jpg|png|gif|webp)\\z/', $name)) {
            throw new RuntimeException('Güvenli olmayan medya yolu; dosya silinmedi.');
        }
        $expected = 'uploads/media/' . $name;
        if (!hash_equals($expected, (string)$row['file_path'])) {
            throw new RuntimeException('Medya yolu doğrulanamadı; dosya silinmedi.');
        }
        $path = $this->mediaDirectory() . DIRECTORY_SEPARATOR . $name;
        $this->db->prepare('DELETE FROM core_media WHERE id=?')->execute([$id]);
        if (is_file($path)) @unlink($path);
    }

    public function url(string $relativePath): string
    {
        if (!preg_match('~\\Auploads/media/[a-f0-9]{40}\\.(?:jpg|png|gif|webp)\\z~', $relativePath)) {
            return '';
        }
        return rtrim($this->publicBase, '/') . '/' . $relativePath;
    }

    private function mediaDirectory(): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'media';
    }

    private function protectDirectory(string $directory): void
    {
        $rules = "Options -Indexes\n<FilesMatch \"\\\\.(?:php[0-9]?|phtml|phar|cgi|pl|py|sh)$\">\n  Require all denied\n</FilesMatch>\n";
        $htaccess = $directory . DIRECTORY_SEPARATOR . '.htaccess';
        if (!is_file($htaccess)) file_put_contents($htaccess, $rules, LOCK_EX);
        $index = $directory . DIRECTORY_SEPARATOR . 'index.html';
        if (!is_file($index)) file_put_contents($index, '', LOCK_EX);
    }

    private function uploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Görsel 8 MB sınırını aşıyor.',
            UPLOAD_ERR_PARTIAL => 'Görselin yalnızca bir kısmı yüklendi.',
            UPLOAD_ERR_NO_FILE => 'Lütfen bir görsel seçin.',
            default => 'Görsel yüklenemedi. Sunucu yükleme ayarlarını kontrol edin.',
        };
    }
}
