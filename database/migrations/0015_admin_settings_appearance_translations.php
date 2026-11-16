<?php
declare(strict_types=1);
return static function (PDO $db): void {
    $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql = $driver === 'sqlite'
        ? 'INSERT OR IGNORE INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?)'
        : 'INSERT IGNORE INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?)';
    $insert = $db->prepare($sql);
    $entries = [
        "admin.localization.migration_required" => ["Dil yönetimi için veritabanı güncellemesi gerekli", "A database update is required for language management"],
        "admin.localization.pending_migrations" => ["bekleyen migrasyon var. Önce güncellemeyi uygulayın.", "pending migrations. Apply the update first."],
        "admin.localization.add_edit" => ["Dil ekle veya düzenle", "Add or edit a language"],
        "admin.localization.intro" => ["Türkçe ilk kurulum dili olarak korunur. Yeni dilleri etkinleştirip varsayılan dili seçebilirsiniz.", "Turkish is retained as the initial installation language. You can enable additional languages and choose the default language."],
        "admin.localization.code" => ["Dil kodu", "Language code"],
        "admin.localization.name" => ["Dil adı", "Language name"],
        "admin.localization.enabled" => ["Etkin", "Enabled"],
        "admin.localization.save_language" => ["Dili kaydet", "Save language"],
        "admin.localization.languages" => ["Etkin diller", "Languages"],
        "admin.localization.language" => ["Dil", "Language"],
        "admin.localization.status" => ["Durum", "Status"],
        "admin.localization.default" => ["Varsayılan", "Default"],
        "admin.localization.disabled" => ["Pasif", "Disabled"],
        "admin.localization.make_default" => ["Varsayılan yap", "Make default"],
        "admin.localization.edit_translations" => ["Çevirileri düzenle", "Edit translations"],
        "admin.localization.translations_suffix" => ["çevirileri", "translations"],
        "admin.localization.search_placeholder" => ["Anahtar veya çeviri ara", "Search key or translation"],
        "admin.common.search" => ["Ara", "Search"],
        "admin.localization.translation_key" => ["Çeviri anahtarı", "Translation key"],
        "admin.localization.translation_value" => ["Çeviri metni", "Translation text"],
        "admin.localization.save_translation" => ["Çeviriyi kaydet", "Save translation"],
        "admin.localization.no_translations" => ["Bu dilde henüz çeviri yok veya arama sonucu bulunamadı.", "No translations found for this language or search."],
        "admin.settings.general" => ["Genel site ayarları", "General site settings"],
        "admin.settings.site_name" => ["Site adı", "Site name"],
        "admin.settings.site_language" => ["Site dili", "Site language"],
        "admin.settings.timezone" => ["Saat dilimi", "Time zone"],
        "admin.settings.home_hero" => ["Ana sayfa hero alanı", "Homepage hero section"],
        "admin.settings.home_eyebrow" => ["Üst küçük başlık", "Eyebrow text"],
        "admin.settings.home_title" => ["Ana başlık", "Main heading"],
        "admin.settings.home_accent" => ["Vurgulu ikinci başlık", "Highlighted secondary heading"],
        "admin.settings.description" => ["Açıklama", "Description"],
        "admin.settings.technical_seo" => ["Teknik SEO ve arama motorları", "Technical SEO and search engines"],
        "admin.settings.site_url" => ["Site URL (alan adınız ve varsa alt dizin)", "Site URL (domain and optional subdirectory)"],
        "admin.settings.site_url_help" => ["Sitemap ve canonical adresleri bu güvenilir URL üzerinden oluşturulur; HTTP Host başlığı kullanılmaz.", "Sitemap and canonical URLs are generated from this trusted URL; the HTTP Host header is not used."],
        "admin.settings.organization_name" => ["Kuruluş / marka adı", "Organization / brand name"],
        "admin.settings.organization_logo" => ["Kuruluş logosu (mutlak URL)", "Organization logo (absolute URL)"],
        "admin.settings.social_image" => ["Sosyal paylaşım varsayılan görseli (mutlak URL)", "Default social sharing image (absolute URL)"],
        "admin.settings.search_console" => ["Google Search Console doğrulama kodu", "Google Search Console verification code"],
        "admin.settings.meta_content_help" => ["Meta etiketindeki content değeri", "The content value from the meta tag"],
        "admin.settings.save" => ["Ayarları kaydet", "Save settings"],
        "admin.appearance.active_theme" => ["Aktif tema", "Active theme"],
        "admin.appearance.help" => ["Yalnızca etkin modüllerde bulunan ve güvenli dizin içinde geçerli bir layout.php dosyası olan temalar seçilebilir.", "Only themes from enabled modules with a valid layout.php file in a safe directory can be selected."],
        "admin.appearance.select_theme" => ["Tema seçimi", "Select theme"],
        "admin.appearance.automatic" => ["Otomatik (ilk uygun etkin tema)", "Automatic (first eligible enabled theme)"],
        "admin.appearance.save" => ["Temayı kaydet", "Save theme"],
        "admin.appearance.none" => ["Şu anda seçilebilir tema yok. Tema sağlayan bir modülü kurup etkinleştirin.", "No selectable themes are available. Install and enable a module that provides a theme."]
    ];
    foreach ($entries as $key => $values) {
        $insert->execute(['tr', $key, $values[0]]);
        $insert->execute(['en', $key, $values[1]]);
    }
};
