<?php
declare(strict_types=1);

namespace CoreCMS\Core;

use PDO;
use RuntimeException;
use Throwable;

final class SeoBulkEditor
{
    public function __construct(
        private PDO $db,
        private PageRevisionManager $revisions,
        private PageAutosaveManager $autosaves
    ) {}

    private static function length(string $value): int
    {
        return preg_match_all('/./us', $value, $matches) ?: 0;
    }

    public function apply(array $pageIds, string $titlePattern, string $descriptionPattern, string $mode, string $siteName, int $userId): int
    {
        if (!in_array($mode, ['fill_missing','overwrite'], true)) throw new RuntimeException('Toplu SEO işlem türü geçersiz.');
        $ids = [];
        foreach ($pageIds as $id) {
            $id = filter_var($id, FILTER_VALIDATE_INT);
            if ($id !== false && $id > 0) $ids[] = $id;
        }
        $ids = array_values(array_unique($ids));
        if (!$ids || count($ids) > 100) throw new RuntimeException('Bir işlemde 1–100 yayınlanmış sayfa seçin.');

        $titlePattern = trim(strip_tags($titlePattern));
        $descriptionPattern = trim(strip_tags($descriptionPattern));
        if ($titlePattern === '' && $descriptionPattern === '') throw new RuntimeException('En az bir SEO şablonu girin.');
        if (self::length($titlePattern) > 255 || self::length($descriptionPattern) > 320) throw new RuntimeException('SEO şablonlarından biri izin verilen uzunluğu aşıyor.');

        $placeholders = ['{site_name}' => $siteName];
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $query = $this->db->prepare("SELECT id,title,slug,seo_title,seo_description FROM core_pages WHERE status='published' AND id IN ($marks) ORDER BY id");
        $query->execute($ids);
        $pages = $query->fetchAll(PDO::FETCH_ASSOC);
        if (count($pages) !== count($ids)) throw new RuntimeException('Seçilen sayfalardan biri bulunamadı veya yayın durumunda değil.');

        $changed = 0;
        $this->db->beginTransaction();
        try {
            foreach ($pages as $page) {
                $tokens = $placeholders + ['{title}' => (string)$page['title'], '{slug}' => (string)$page['slug']];
                $oldTitle = (string)($page['seo_title'] ?? '');
                $oldDescription = (string)($page['seo_description'] ?? '');
                $newTitle = $oldTitle;
                $newDescription = $oldDescription;
                if ($titlePattern !== '' && ($mode === 'overwrite' || trim($oldTitle) === '')) $newTitle = trim(strtr($titlePattern, $tokens));
                if ($descriptionPattern !== '' && ($mode === 'overwrite' || trim($oldDescription) === '')) $newDescription = trim(strtr($descriptionPattern, $tokens));
                if (self::length($newTitle) > 255 || self::length($newDescription) > 320) {
                    throw new RuntimeException('Şablon sonucu "' . (string)$page['title'] . '" sayfasında alan sınırını aşıyor. Şablonu kısaltın.');
                }
                if ($newTitle === $oldTitle && $newDescription === $oldDescription) continue;
                $this->revisions->snapshot((int)$page['id'], $userId > 0 ? $userId : null);
                $update = $this->db->prepare('UPDATE core_pages SET seo_title=?,seo_description=? WHERE id=?');
                $update->execute([$newTitle !== '' ? $newTitle : null, $newDescription !== '' ? $newDescription : null, (int)$page['id']]);
                if ($userId > 0) $this->autosaves->clear((int)$page['id'], $userId);
                $changed++;
            }
            if ($changed < 1) throw new RuntimeException('Seçilen sayfalarda değiştirilecek boş SEO alanı bulunamadı.');
            $this->db->commit();
            return $changed;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
