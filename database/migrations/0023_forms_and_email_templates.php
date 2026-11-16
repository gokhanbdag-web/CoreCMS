<?php
declare(strict_types=1);

return static function (PDO $db): void {
    $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $db->exec("CREATE TABLE IF NOT EXISTS core_forms (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            form_key TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            description TEXT NULL,
            fields_json TEXT NOT NULL,
            recipient_email TEXT NULL,
            success_message TEXT NULL,
            enabled INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS core_form_submissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            form_id INTEGER NOT NULL,
            locale_code TEXT NULL,
            payload_json TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'new',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(form_id) REFERENCES core_forms(id) ON DELETE CASCADE,
            FOREIGN KEY(locale_code) REFERENCES core_languages(locale_code) ON DELETE SET NULL
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_core_form_submissions_form_created ON core_form_submissions(form_id,created_at)");
        $db->exec("CREATE TABLE IF NOT EXISTS core_email_templates (
            template_key TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            subject TEXT NOT NULL,
            body TEXT NOT NULL,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS core_email_template_translations (
            template_key TEXT NOT NULL,
            locale_code TEXT NOT NULL,
            subject TEXT NOT NULL,
            body TEXT NOT NULL,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(template_key,locale_code),
            FOREIGN KEY(template_key) REFERENCES core_email_templates(template_key) ON DELETE CASCADE,
            FOREIGN KEY(locale_code) REFERENCES core_languages(locale_code) ON DELETE CASCADE
        )");
        return;
    }

    $db->exec("CREATE TABLE IF NOT EXISTS core_forms (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        form_key VARCHAR(100) NOT NULL UNIQUE,
        name VARCHAR(190) NOT NULL,
        description TEXT NULL,
        fields_json LONGTEXT NOT NULL,
        recipient_email VARCHAR(190) NULL,
        success_message VARCHAR(500) NULL,
        enabled TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS core_form_submissions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        form_id BIGINT UNSIGNED NOT NULL,
        locale_code VARCHAR(12) NULL,
        payload_json LONGTEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'new',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_core_form_submissions_form_created (form_id,created_at),
        CONSTRAINT fk_core_form_submission_form FOREIGN KEY (form_id) REFERENCES core_forms(id) ON DELETE CASCADE,
        CONSTRAINT fk_core_form_submission_locale FOREIGN KEY (locale_code) REFERENCES core_languages(locale_code) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS core_email_templates (
        template_key VARCHAR(100) PRIMARY KEY,
        name VARCHAR(190) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        body LONGTEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS core_email_template_translations (
        template_key VARCHAR(100) NOT NULL,
        locale_code VARCHAR(12) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        body LONGTEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (template_key,locale_code),
        CONSTRAINT fk_core_email_template_translation_template FOREIGN KEY (template_key) REFERENCES core_email_templates(template_key) ON DELETE CASCADE,
        CONSTRAINT fk_core_email_template_translation_locale FOREIGN KEY (locale_code) REFERENCES core_languages(locale_code) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};
