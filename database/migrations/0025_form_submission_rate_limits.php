<?php
declare(strict_types=1);

return static function (PDO $db): void {
    $driver=(string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if($driver==='sqlite'){
        $db->exec("CREATE TABLE IF NOT EXISTS core_form_rate_limits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            form_id INTEGER NOT NULL,
            ip_hash TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(form_id) REFERENCES core_forms(id) ON DELETE CASCADE
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_core_form_rate_limits_lookup ON core_form_rate_limits(form_id,ip_hash,created_at)");
        return;
    }
    $db->exec("CREATE TABLE IF NOT EXISTS core_form_rate_limits (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        form_id BIGINT UNSIGNED NOT NULL,
        ip_hash CHAR(64) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_core_form_rate_limits_lookup (form_id,ip_hash,created_at),
        CONSTRAINT fk_core_form_rate_limit_form FOREIGN KEY (form_id) REFERENCES core_forms(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};
