<?php
declare(strict_types=1);

return static function (PDO $db): void {
    $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $tables = ['core_pages','core_page_revisions','core_page_autosaves'];
    foreach ($tables as $table) {
        $columns = $driver === 'mysql'
            ? ['social_image_media_id' => 'BIGINT UNSIGNED NULL']
            : ['social_image_media_id' => 'INTEGER NULL'];
        foreach ($columns as $name => $definition) {
            if ($driver === 'mysql') {
                $check = $db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
                $check->execute([$table,$name]);
                $exists = (int)$check->fetchColumn() > 0;
            } else {
                $exists = false;
                foreach ($db->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC) as $column) {
                    if (($column['name'] ?? '') === $name) $exists = true;
                }
            }
            if (!$exists) $db->exec('ALTER TABLE '.$table.' ADD COLUMN '.$name.' '.$definition);
        }
    }
    $sql = $driver === 'mysql'
        ? "CREATE TABLE IF NOT EXISTS core_redirects (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            source_path VARCHAR(500) NOT NULL UNIQUE,
            target_path VARCHAR(500) NOT NULL,
            http_code SMALLINT NOT NULL DEFAULT 301,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        : "CREATE TABLE IF NOT EXISTS core_redirects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            source_path VARCHAR(500) NOT NULL UNIQUE,
            target_path VARCHAR(500) NOT NULL,
            http_code INTEGER NOT NULL DEFAULT 301,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )";
    $db->exec($sql);
};
