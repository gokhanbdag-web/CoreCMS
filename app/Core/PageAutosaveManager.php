<?php
declare(strict_types=1);

namespace CoreCMS\Core;

use PDO;

final class PageAutosaveManager
{
    public function __construct(private PDO $db) {}

    public function get(int $pageId, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT title,slug,body,seo_title,seo_description,canonical_url,saved_at FROM core_page_autosaves WHERE page_id=? AND user_id=? LIMIT 1');
        $stmt->execute([$pageId,$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function clear(int $pageId, int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM core_page_autosaves WHERE page_id=? AND user_id=?');
        $stmt->execute([$pageId,$userId]);
    }
}