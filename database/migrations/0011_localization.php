<?php
declare(strict_types=1);

return static function (PDO $db): void {
    $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $db->exec("CREATE TABLE IF NOT EXISTS core_languages (
            locale_code TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            is_default INTEGER NOT NULL DEFAULT 0,
            enabled INTEGER NOT NULL DEFAULT 1
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS core_translations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            locale_code TEXT NOT NULL,
            translation_key TEXT NOT NULL,
            translation_value TEXT NOT NULL,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(locale_code,translation_key),
            FOREIGN KEY(locale_code) REFERENCES core_languages(locale_code) ON UPDATE CASCADE ON DELETE CASCADE
        )");
        $db->exec("INSERT OR IGNORE INTO core_languages(locale_code,name,is_default,enabled) VALUES('tr','Türkçe',1,1)");
        return;
    }
    $db->exec("CREATE TABLE IF NOT EXISTS core_languages (
        locale_code VARCHAR(12) PRIMARY KEY,
        name VARCHAR(80) NOT NULL,
        is_default TINYINT(1) NOT NULL DEFAULT 0,
        enabled TINYINT(1) NOT NULL DEFAULT 1,
        KEY idx_core_languages_default (is_default,enabled)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("CREATE TABLE IF NOT EXISTS core_translations (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        locale_code VARCHAR(12) NOT NULL,
        translation_key VARCHAR(190) NOT NULL,
        translation_value TEXT NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_core_translation_locale_key (locale_code,translation_key),
        CONSTRAINT fk_core_translation_language FOREIGN KEY (locale_code) REFERENCES core_languages(locale_code) ON UPDATE CASCADE ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("INSERT IGNORE INTO core_languages(locale_code,name,is_default,enabled) VALUES('tr','Türkçe',1,1)");
};
