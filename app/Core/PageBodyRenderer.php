<?php
declare(strict_types=1);

namespace CoreCMS\Core;

use PDO;

final class PageBodyRenderer
{
    public static function render(PDO $db, string $storedBody, MediaLibrary $media): string
    {
        $body = html_entity_decode($storedBody, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $parts = preg_split('/(\[\[media:[1-9][0-9]*\]\])/', $body, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) return nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $html = '';
        foreach ($parts as $part) {
            if (preg_match('/^\[\[media:([1-9][0-9]*)\]\]$/', $part, $match)) {
                $stmt = $db->prepare('SELECT file_path,alt_text FROM core_media WHERE id=? LIMIT 1');
                $stmt->execute([(int)$match[1]]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $url = $media->url((string)$row['file_path']);
                    if ($url !== '') {
                        $html .= '<img class="content-image" src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars((string)$row['alt_text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" loading="lazy">';
                    }
                }
                continue;
            }
            $html .= nl2br(htmlspecialchars($part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        }
        return $html;
    }
}
