<?php
declare(strict_types=1);

return static function (PDO $db): void {
    $driver=(string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if($driver==='sqlite'){
        $db->exec("CREATE TABLE IF NOT EXISTS core_mail_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            recipient TEXT NOT NULL,
            subject TEXT NOT NULL,
            body TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending',
            attempts INTEGER NOT NULL DEFAULT 0,
            next_attempt_at TEXT NULL,
            sent_at TEXT NULL,
            last_error TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_core_mail_queue_due ON core_mail_queue(status,next_attempt_at,id)");
        return;
    }
    $db->exec("CREATE TABLE IF NOT EXISTS core_mail_queue (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        recipient VARCHAR(190) NOT NULL,
        subject VARCHAR(500) NOT NULL,
        body LONGTEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        next_attempt_at DATETIME NULL,
        sent_at DATETIME NULL,
        last_error TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_core_mail_queue_due (status,next_attempt_at,id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};
