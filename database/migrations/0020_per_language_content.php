<?php
declare(strict_types=1);

return static function (PDO $db): void {
    $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $db->exec("CREATE TABLE IF NOT EXISTS core_page_translations (
            page_id INTEGER NOT NULL,
            locale_code TEXT NOT NULL,
            title TEXT NOT NULL,
            slug TEXT NOT NULL,
            body TEXT NULL,
            seo_title TEXT NULL,
            seo_description TEXT NULL,
            canonical_url TEXT NULL,
            social_image_media_id INTEGER NULL,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(page_id, locale_code),
            UNIQUE(locale_code, slug),
            FOREIGN KEY(page_id) REFERENCES core_pages(id) ON DELETE CASCADE,
            FOREIGN KEY(locale_code) REFERENCES core_languages(locale_code) ON DELETE CASCADE,
            FOREIGN KEY(social_image_media_id) REFERENCES core_media(id) ON DELETE SET NULL
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS core_menu_translations (
            menu_id INTEGER NOT NULL,
            locale_code TEXT NOT NULL,
            label TEXT NOT NULL,
            url TEXT NOT NULL,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(menu_id, locale_code),
            FOREIGN KEY(menu_id) REFERENCES core_menus(id) ON DELETE CASCADE,
            FOREIGN KEY(locale_code) REFERENCES core_languages(locale_code) ON DELETE CASCADE
        )");
        return;
    }

    $db->exec("CREATE TABLE IF NOT EXISTS core_page_translations (
        page_id BIGINT UNSIGNED NOT NULL,
        locale_code VARCHAR(12) NOT NULL,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL,
        body LONGTEXT NULL,
        seo_title VARCHAR(255) NULL,
        seo_description VARCHAR(320) NULL,
        canonical_url VARCHAR(500) NULL,
        social_image_media_id BIGINT UNSIGNED NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (page_id, locale_code),
        UNIQUE KEY uq_core_page_translation_locale_slug (locale_code, slug),
        CONSTRAINT fk_core_page_translation_page FOREIGN KEY (page_id) REFERENCES core_pages(id) ON DELETE CASCADE,
        CONSTRAINT fk_core_page_translation_locale FOREIGN KEY (locale_code) REFERENCES core_languages(locale_code) ON DELETE CASCADE,
        CONSTRAINT fk_core_page_translation_media FOREIGN KEY (social_image_media_id) REFERENCES core_media(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS core_menu_translations (
        menu_id BIGINT UNSIGNED NOT NULL,
        locale_code VARCHAR(12) NOT NULL,
        label VARCHAR(190) NOT NULL,
        url VARCHAR(500) NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (menu_id, locale_code),
        CONSTRAINT fk_core_menu_translation_menu FOREIGN KEY (menu_id) REFERENCES core_menus(id) ON DELETE CASCADE,
        CONSTRAINT fk_core_menu_translation_locale FOREIGN KEY (locale_code) REFERENCES core_languages(locale_code) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};
