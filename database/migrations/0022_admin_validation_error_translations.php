<?php
declare(strict_types=1);
return static function (PDO $db): void {
    $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql = $driver === 'sqlite'
        ? 'INSERT OR IGNORE INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?)'
        : 'INSERT IGNORE INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?)';
    $insert = $db->prepare($sql);
    $entries = [
        "admin.error.role_invalid_key" => ["Rol anahtarı 2-40 karakter olmalı; harf, rakam ve alt çizgi kullanılabilir.", "Role key must be 2–40 characters and contain only letters, numbers and underscores."],
        "admin.error.role_invalid_name" => ["Rol adı zorunludur ve 100 karakteri aşamaz.", "Role name is required and cannot exceed 100 characters."],
        "admin.error.role_system_edit" => ["Sistem rolleri düzenlenemez.", "System roles cannot be edited."],
        "admin.error.role_system_delete" => ["Sistem rolleri silinemez.", "System roles cannot be deleted."],
        "admin.error.role_in_use" => ["Bu role atanmış kullanıcılar var. Önce kullanıcı rollerini değiştirin.", "Users are assigned to this role. Reassign them before deleting it."],
        "admin.error.role_admin_assignment" => ["Yönetici rolü yalnızca tam yetkili yöneticiler tarafından atanabilir.", "The administrator role can only be assigned by a full administrator."],
        "admin.error.role_not_found" => ["Seçilen rol bulunamadı.", "The selected role was not found."],
        "admin.error.role_super_admin_assignment" => ["Süper yönetici rolü bu ekrandan atanamaz.", "The super administrator role cannot be assigned from this screen."],
        "admin.error.user_not_found" => ["Kullanıcı bulunamadı.", "The user was not found."],
        "admin.error.super_admin_protected" => ["Süper yönetici hesabı değiştirilemez.", "A super administrator account cannot be changed."],
        "admin.error.menu_url_invalid" => ["Menü bağlantısı geçersiz.", "The menu URL is invalid."],
        "admin.error.url_https_or_internal" => ["Bağlantı yalnızca site içi /yol veya HTTPS adresi olabilir.", "The URL must be an internal path or an HTTPS address."],
        "admin.error.page_title_required" => ["Sayfa başlığı zorunludur (en fazla 255 karakter).", "Page title is required and cannot exceed 255 characters."],
        "admin.error.page_slug_invalid" => ["Sayfa bağlantısı geçersiz.", "The page URL slug is invalid."],
        "admin.error.page_status_invalid" => ["Yayın durumu geçersiz.", "The publication status is invalid."],
        "admin.error.seo_field_too_long" => ["SEO alanlarından biri izin verilen uzunluğu aşıyor.", "One of the SEO fields exceeds the allowed length."],
        "admin.error.canonical_invalid" => ["Canonical adresi HTTPS veya site içi yol olmalı.", "The canonical URL must be HTTPS or an internal path."],
        "admin.error.scheduled_date_invalid" => ["Planlı yayın için gelecekte geçerli bir tarih ve saat seçin.", "Choose a valid future date and time for scheduled publishing."],
        "admin.error.social_image_missing" => ["Sosyal paylaşım görseli medya kütüphanesinde bulunamadı.", "The social sharing image was not found in the media library."],
        "admin.error.page_not_found" => ["Sayfa bulunamadı.", "The page was not found."],
        "admin.error.translation_locale_disabled" => ["Çeviri dili etkin olmalıdır.", "The translation language must be enabled."],
        "admin.error.edit_base_locale" => ["Ana dilin içeriği doğrudan düzenlenmelidir.", "Edit the base-language content directly."],
        "admin.error.translation_page_title_required" => ["Çeviri sayfa başlığı zorunludur (en fazla 255 karakter).", "Translated page title is required and cannot exceed 255 characters."],
        "admin.error.translation_slug_invalid" => ["Çeviri sayfa bağlantısı geçersiz.", "The translated page URL slug is invalid."],
        "admin.error.translation_slug_in_use" => ["Çeviri sayfa bağlantısı başka bir sayfa tarafından kullanılıyor.", "The translated page URL slug is already used by another page."],
        "admin.error.menu_item_not_found" => ["Menü öğesi bulunamadı.", "The menu item was not found."],
        "admin.error.translation_menu_label_required" => ["Menü çeviri etiketi zorunludur (en fazla 190 karakter).", "Translated menu label is required and cannot exceed 190 characters."],
        "admin.error.page_already_owned" => ["Sayfa zaten yöneticiye ait.", "The page is already managed independently."],
        "admin.error.module_page_delete" => ["Modül tarafından sağlanan sayfalar silinemez; önce modül içeriğinden ayırın.", "Module-provided pages cannot be deleted. Detach the page from its module first."],
        "admin.error.menu_key_invalid" => ["Menü anahtarı 1-100 karakter olmalı; harf, rakam, nokta, tire ve alt çizgi kullanılabilir.", "Menu key must be 1–100 characters and contain only letters, numbers, dots, hyphens and underscores."],
        "admin.error.menu_label_required" => ["Menü etiketi zorunludur (en fazla 190 karakter).", "Menu label is required and cannot exceed 190 characters."],
        "admin.error.menu_already_owned" => ["Menü öğesi zaten yöneticiye ait.", "The menu item is already managed independently."],
        "admin.error.module_menu_delete" => ["Modül tarafından sağlanan menü öğeleri silinemez.", "Module-provided menu items cannot be deleted."],
        "admin.error.redirect_path_invalid" => ["Yönlendirme adresi güvenli bir site içi yol olmalı.", "The redirect address must be a safe internal path."],
        "admin.error.redirect_root_source" => ["Kaynak adres kök dizin olamaz.", "The root path cannot be used as a redirect source."],
        "admin.error.redirect_reserved_source" => ["Bu sistem adresi yönlendirme kaynağı olarak kullanılamaz.", "This system path cannot be used as a redirect source."],
        "admin.error.redirect_same_path" => ["Kaynak ve hedef adres aynı olamaz.", "The source and destination paths cannot be the same."],
        "admin.error.redirect_code_invalid" => ["Yalnızca 301 veya 302 yönlendirmesi kullanılabilir.", "Only 301 or 302 redirects are supported."],
        "admin.error.redirect_source_exists" => ["Bu kaynak adres için zaten yönlendirme var.", "A redirect already exists for this source path."],
        "admin.error.redirect_chain" => ["Hedef adres başka bir yönlendirmeye gidiyor; yönlendirme zinciri oluşturulamaz.", "The destination points to another redirect; redirect chains are not allowed."],
        "admin.error.redirect_not_found" => ["Yönlendirme kaydı bulunamadı.", "The redirect record was not found."],
        "admin.error.upload_source_invalid" => ["Geçersiz yükleme kaynağı.", "The upload source is invalid."],
        "admin.error.upload_size_invalid" => ["Dosya boş olamaz ve en fazla 8 MB olabilir.", "The file cannot be empty and must be no larger than 8 MB."],
        "admin.error.upload_type_invalid" => ["Yalnızca JPEG, PNG, GIF ve WebP görselleri yüklenebilir.", "Only JPEG, PNG, GIF and WebP images can be uploaded."],
        "admin.error.upload_image_invalid" => ["Dosya geçerli bir görsel değil.", "The file is not a valid image."],
        "admin.error.media_directory_failed" => ["Medya klasörü oluşturulamadı.", "The media directory could not be created."],
        "admin.error.media_save_failed" => ["Görsel sunucuya kaydedilemedi.", "The image could not be saved on the server."],
        "admin.error.media_record_not_found" => ["Medya kaydı bulunamadı.", "The media record was not found."],
        "admin.error.media_unsafe_path" => ["Güvenli olmayan medya yolu; dosya silinmedi.", "The media path is unsafe; the file was not deleted."],
        "admin.error.media_path_unverified" => ["Medya yolu doğrulanamadı; dosya silinmedi.", "The media path could not be verified; the file was not deleted."],
        "admin.error.locale_code_invalid" => ["Dil kodu geçersiz. Örnek: tr, en veya en-US.", "The locale code is invalid. Examples: tr, en or en-US."],
        "admin.error.locale_name_invalid" => ["Dil adı zorunludur ve 80 karakteri aşamaz.", "Language name is required and cannot exceed 80 characters."],
        "admin.error.default_locale_invalid" => ["Varsayılan dil kodu geçersiz.", "The default locale code is invalid."],
        "admin.error.default_locale_disabled" => ["Varsayılan dil etkin bir dil olmalıdır.", "The default locale must be enabled."],
        "admin.error.translation_locale_invalid" => ["Çeviri dili geçersiz.", "The translation locale is invalid."],
        "admin.error.translation_key_invalid" => ["Çeviri anahtarı geçersiz.", "The translation key is invalid."],
        "admin.error.translation_value_too_long" => ["Çeviri metni 10.000 karakteri aşamaz.", "Translation text cannot exceed 10,000 characters."],
        "admin.error.translation_locale_required" => ["Çeviri eklemek için dil etkin olmalıdır.", "The language must be enabled before adding translations."],
        "admin.error.theme_unavailable" => ["Tema etkin değil veya bulunamadı.", "The theme is not enabled or could not be found."],
        "admin.error.theme_template_missing" => ["Tema şablonu tanımlanmamış.", "The theme template is not defined."],
        "admin.error.theme_file_missing" => ["Tema şablon dosyası bulunamadı.", "The theme template file could not be found."],
        "admin.error.theme_render_failed" => ["Tema şablonu oluşturulamadı: ", "The theme could not be rendered: "],
        "admin.error.theme_asset_undefined" => ["Tema varlığı manifest içinde tanımlı değil.", "The theme asset is not declared in the manifest."],
        "admin.error.theme_invalid" => ["Seçilen tema etkin bir modülde bulunamadı veya geçerli bir layout.php dosyası yok.", "The selected theme is not in an enabled module or has no valid layout.php file."]
    ];
    foreach ($entries as $key => $values) {
        $insert->execute(['tr', $key, $values[0]]);
        $insert->execute(['en', $key, $values[1]]);
    }
};
