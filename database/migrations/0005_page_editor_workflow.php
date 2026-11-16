<?php
declare(strict_types=1);

return static function (PDO $db): void {
    $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $columns = [
        'seo_title' => "VARCHAR(255) NULL",
        'seo_description' => "VARCHAR(320) NULL",
        'canonical_url' => "VARCHAR(500) NULL",
        'scheduled_at' => "DATETIME NULL",
    ];
    foreach ($columns as $name => $definition) {
        if ($driver === 'mysql') {
            $check = $db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
            $check->execute(['core_pages', $name]);
            $exists = (int)$check->fetchColumn() > 0;
        } else {
            $exists = false;
            foreach ($db->query('PRAGMA table_info(core_pages)')->fetchAll(PDO::FETCH_ASSOC) as $column) {
                if (($column['name'] ?? '') === $name) $exists = true;
            }
        }
        if (!$exists) $db->exec('ALTER TABLE core_pages ADD COLUMN '.$name.' '.$definition);
    }

    foreach (['seo_title' => 'VARCHAR(255) NULL', 'seo_description' => 'VARCHAR(320) NULL', 'canonical_url' => 'VARCHAR(500) NULL', 'scheduled_at' => 'DATETIME NULL'] as $name => $definition) {
        if ($driver === 'mysql') {
            $check = $db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
            $check->execute(['core_page_revisions', $name]);
            $exists = (int)$check->fetchColumn() > 0;
        } else {
            $exists = false;
            foreach ($db->query('PRAGMA table_info(core_page_revisions)')->fetchAll(PDO::FETCH_ASSOC) as $column) if (($column['name'] ?? '') === $name) $exists = true;
        }
        if (!$exists) $db->exec('ALTER TABLE core_page_revisions ADD COLUMN '.$name.' '.$definition);
    }

    $autosaveSql = "CREATE TABLE IF NOT EXISTS core_page_autosaves (
        page_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL,
        body LONGTEXT NULL,
        seo_title VARCHAR(255) NULL,
        seo_description VARCHAR(320) NULL,
        canonical_url VARCHAR(500) NULL,
        saved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (page_id, user_id)
    )";
    $db->exec($driver === 'mysql' ? $autosaveSql.' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : $autosaveSql);
    if ($driver === 'mysql') {
        $index = $db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
        $index->execute(['core_page_autosaves','idx_page_autosaves_saved']);
        if ((int)$index->fetchColumn() === 0) $db->exec('CREATE INDEX idx_page_autosaves_saved ON core_page_autosaves (saved_at)');
    }
};