<?php
declare(strict_types=1);

return static function (PDO $db): void {
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $db->exec("CREATE TABLE IF NOT EXISTS core_module_content_registry (
            module_id TEXT NOT NULL, content_type TEXT NOT NULL, content_key TEXT NOT NULL,
            content_id INTEGER NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (module_id,content_type,content_key)
        )");
        return;
    }
    $db->exec("CREATE TABLE IF NOT EXISTS core_module_content_registry (
        module_id VARCHAR(100) NOT NULL,
        content_type VARCHAR(20) NOT NULL,
        content_key VARCHAR(100) NOT NULL,
        content_id BIGINT UNSIGNED NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (module_id,content_type,content_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};
