<?php
declare(strict_types=1);

return static function (PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS core_page_revisions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        page_id BIGINT UNSIGNED NOT NULL,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL,
        body LONGTEXT NULL,
        status VARCHAR(20) NOT NULL,
        editor_id BIGINT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_page_revisions_page_created (page_id, created_at),
        INDEX idx_page_revisions_editor (editor_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};