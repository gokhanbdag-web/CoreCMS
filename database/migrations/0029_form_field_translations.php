<?php
declare(strict_types=1);

return static function (PDO $db): void {
    $driver=(string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if($driver==='sqlite'){
        $db->exec("CREATE TABLE IF NOT EXISTS core_form_field_translations (
            form_id INTEGER NOT NULL,
            locale_code TEXT NOT NULL,
            translations_json TEXT NOT NULL,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(form_id,locale_code),
            FOREIGN KEY(form_id) REFERENCES core_forms(id) ON DELETE CASCADE,
            FOREIGN KEY(locale_code) REFERENCES core_languages(locale_code) ON DELETE CASCADE
        )");
        return;
    }
    $db->exec("CREATE TABLE IF NOT EXISTS core_form_field_translations (
        form_id BIGINT UNSIGNED NOT NULL,
        locale_code VARCHAR(12) NOT NULL,
        translations_json LONGTEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY(form_id,locale_code),
        CONSTRAINT fk_core_form_field_translation_form FOREIGN KEY (form_id) REFERENCES core_forms(id) ON DELETE CASCADE,
        CONSTRAINT fk_core_form_field_translation_locale FOREIGN KEY (locale_code) REFERENCES core_languages(locale_code) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};
