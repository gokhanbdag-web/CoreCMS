<?php
declare(strict_types=1);

namespace CoreCMS\Core;

use PDO;
use RuntimeException;

final class PageRevisionManager
{
    public function __construct(private PDO $db) {}

    public function snapshot(int $pageId, ?int $editorId): void
    {
        $query = $this->db->prepare('SELECT title,slug,body,status FROM core_pages WHERE id=? LIMIT 1');
        $query->execute([$pageId]);
        $page = $query->fetch(PDO::FETCH_ASSOC);
        if (!$page) throw new RuntimeException('Sayfa bulunamadı.');

        $latest = $this->db->prepare('SELECT title,slug,body,status FROM core_page_revisions WHERE page_id=? ORDER BY id DESC LIMIT 1');
        $latest->execute([$pageId]);
        $previous = $latest->fetch(PDO::FETCH_ASSOC);
        if ($previous && $previous === $page) return;

        $insert = $this->db->prepare('INSERT INTO core_page_revisions (page_id,title,slug,body,status,editor_id) VALUES (?,?,?,?,?,?)');
        $insert->execute([$pageId,$page['title'],$page['slug'],$page['body'],$page['status'],$editorId]);
    }

    public function history(int $pageId, int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        $query = $this->db->prepare('SELECT id,page_id,title,slug,status,editor_id,created_at FROM core_page_revisions WHERE page_id=? ORDER BY id DESC LIMIT '.$limit);
        $query->execute([$pageId]);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function restore(int $revisionId, ?int $editorId): int
    {
        $query = $this->db->prepare('SELECT page_id,title,slug,body,status FROM core_page_revisions WHERE id=? LIMIT 1');
        $query->execute([$revisionId]);
        $revision = $query->fetch(PDO::FETCH_ASSOC);
        if (!$revision) throw new RuntimeException('Revizyon bulunamadı.');

        $this->db->beginTransaction();
        try {
            $this->snapshot((int)$revision['page_id'], $editorId);
            $update = $this->db->prepare('UPDATE core_pages SET title=?,slug=?,body=?,status=? WHERE id=?');
            $update->execute([$revision['title'],$revision['slug'],$revision['body'],$revision['status'],$revision['page_id']]);
            if ($update->rowCount() < 1) {
                $exists = $this->db->prepare('SELECT id FROM core_pages WHERE id=?');
                $exists->execute([(int)$revision['page_id']]);
                if (!$exists->fetchColumn()) throw new RuntimeException('Sayfa artık mevcut değil.');
            }
            $this->db->commit();
            return (int)$revision['page_id'];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}