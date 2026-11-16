<?php
declare(strict_types=1);

return static function (PDO $db): void {
    if ((string)$db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $db->exec("CREATE TABLE IF NOT EXISTS core_module_update_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            module_id TEXT NOT NULL,
            from_version TEXT NOT NULL,
            to_version TEXT NOT NULL,
            status TEXT NOT NULL,
            migrations_json TEXT NOT NULL,
            is_reversible INTEGER NOT NULL DEFAULT 0,
            error_text TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            rolled_back_at TEXT NULL
        )");
        return;
    }
    $db->exec("CREATE TABLE IF NOT EXISTS core_module_update_history (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        module_id VARCHAR(100) NOT NULL,
        from_version VARCHAR(40) NOT NULL,
        to_version VARCHAR(40) NOT NULL,
        status VARCHAR(30) NOT NULL,
        migrations_json LONGTEXT NOT NULL,
        is_reversible TINYINT(1) NOT NULL DEFAULT 0,
        error_text TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        rolled_back_at TIMESTAMP NULL,
        INDEX idx_module_update_history (module_id,id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};
