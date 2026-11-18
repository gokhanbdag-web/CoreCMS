<?php
declare(strict_types=1);

return static function (PDO $db): void {
    if ((string)$db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $db->exec("CREATE TABLE IF NOT EXISTS core_login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip_hash CHAR(64) NOT NULL,
            email_hash CHAR(64) NOT NULL,
            attempted_at TEXT NOT NULL
        )");
        $db->exec('CREATE INDEX IF NOT EXISTS idx_core_login_attempts_ip_time ON core_login_attempts(ip_hash,attempted_at)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_core_login_attempts_pair_time ON core_login_attempts(ip_hash,email_hash,attempted_at)');
        return;
    }
    $db->exec("CREATE TABLE IF NOT EXISTS core_login_attempts (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ip_hash CHAR(64) NOT NULL,
        email_hash CHAR(64) NOT NULL,
        attempted_at DATETIME NOT NULL,
        KEY idx_core_login_attempts_ip_time (ip_hash,attempted_at),
        KEY idx_core_login_attempts_pair_time (ip_hash,email_hash,attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};
