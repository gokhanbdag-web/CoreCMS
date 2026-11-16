<?php
declare(strict_types=1);

return static function (PDO $db): void {
    $statements = [
        "CREATE TABLE IF NOT EXISTS core_settings (setting_key VARCHAR(190) PRIMARY KEY, setting_value LONGTEXT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS core_users (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(160) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, role VARCHAR(40) NOT NULL DEFAULT 'admin', status VARCHAR(20) NOT NULL DEFAULT 'active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS core_modules (module_id VARCHAR(100) PRIMARY KEY, version VARCHAR(40) NOT NULL, enabled TINYINT(1) NOT NULL DEFAULT 0, installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS core_pages (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL UNIQUE, body LONGTEXT NULL, status VARCHAR(20) NOT NULL DEFAULT 'draft', source_module VARCHAR(100) NULL, source_demo VARCHAR(100) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS core_menus (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, menu_key VARCHAR(100) NOT NULL, label VARCHAR(190) NOT NULL, url VARCHAR(500) NOT NULL, sort_order INT NOT NULL DEFAULT 0, source_module VARCHAR(100) NULL, source_demo VARCHAR(100) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS core_media (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, file_name VARCHAR(255) NOT NULL, file_path VARCHAR(500) NOT NULL, mime_type VARCHAR(120) NULL, source_module VARCHAR(100) NULL, source_demo VARCHAR(100) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS core_audit_log (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NULL, action VARCHAR(120) NOT NULL, details TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    foreach ($statements as $sql) $db->exec($sql);
    $defaults = ['site_name' => 'CoreCMS', 'site_language' => 'tr', 'timezone' => 'Europe/Istanbul'];
    $stmt = $db->prepare('INSERT IGNORE INTO core_settings (setting_key,setting_value) VALUES (?,?)');
    foreach ($defaults as $key => $value) $stmt->execute([$key, $value]);
};
