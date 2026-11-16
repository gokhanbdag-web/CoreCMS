<?php
declare(strict_types=1);

return static function (PDO $db): void {
    $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $db->exec("CREATE TABLE IF NOT EXISTS core_module_content_registry (
            module_id TEXT NOT NULL, content_type TEXT NOT NULL, content_key TEXT NOT NULL,
            content_id INTEGER NOT NULL, managed_hash TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (module_id,content_type,content_key)
        )");
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS core_module_content_registry (
            module_id VARCHAR(100) NOT NULL,
            content_type VARCHAR(20) NOT NULL,
            content_key VARCHAR(100) NOT NULL,
            content_id BIGINT UNSIGNED NOT NULL,
            managed_hash CHAR(64) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (module_id,content_type,content_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    if ($driver === 'sqlite') {
        $columns = $db->query('PRAGMA table_info(core_module_content_registry)')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) if (($column['name'] ?? '') === 'managed_hash') return;
        $db->exec('ALTER TABLE core_module_content_registry ADD COLUMN managed_hash TEXT NULL');
        return;
    }

    $check = $db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $check->execute(['core_module_content_registry','managed_hash']);
    if ((int)$check->fetchColumn() === 0) {
        $db->exec('ALTER TABLE core_module_content_registry ADD COLUMN managed_hash CHAR(64) NULL');
    }
};
