<?php
declare(strict_types=1);

namespace CoreCMS\\Core;

final class AdminErrorTranslator
{
    private const MESSAGES = [
        'Rol anahtarı 2-40 karakter olmalı; harf, rakam ve alt çizgi kullanılabilir.' => ['admin.error.role_invalid_key', 'Role key must be 2–40 characters and contain only letters, numbers and underscores.'],
        'Rol adı zorunludur ve 100 karakteri aşamaz.' => ['admin.error.role_invalid_name', 'Role name is required and cannot exceed 100 characters.'],
        'Sistem rolleri düzenlenemez.' => ['admin.error.role_system_edit', 'System roles cannot be edited.'],
        'Sistem rolleri silinemez.' => ['admin.error.role_system_delete', 'System roles cannot be deleted.'],
        'Bu role atanmış kullanıcılar var. Önce kullanıcı rollerini değiştirin.' => ['admin.error.role_in_use', 'Users are assigned to this role. Reassign them before deleting it.'],
        'Yönetici rolü yalnızca tam yetkili yöneticiler tarafından atanabilir.' => ['admin.error.role_admin_assignment', 'The administrator role can only be assigned by a full administrator.'],
        'Seçilen rol bulunamadı.' => ['admin.error.role_not_found', 'The selected role was not found.'],
        'Süper yönetici rolü bu ekrandan atanamaz.' => ['admin.error.role_super_admin_assignment', 'The super administrator role cannot be assigned from this screen.'],
        'Kullanıcı bulunamadı.' => ['admin.error.user_not_found', 'The user was not found.'],
        'Süper yönetici hesabı değiştirilemez.' => ['admin.error.super_admin_protected', 'A super administrator account cannot be changed.'],

        'Menü bağlantısı geçersiz.' => ['admin.error.menu_url_invalid', 'The menu URL is invalid.'],
        'Bağlantı yalnızca site içi /yol veya HTTPS adresi olabilir.' => ['admin.error.url_https_or_internal', 'The URL must be an internal path or an HTTPS address.'],
        'Sayfa başlığı zorunludur (en fazla 255 karakter).' => ['admin.error.page_title_required', 'Page title is required and cannot exceed 255 characters.'],
        'Sayfa bağlantısı geçersiz.' => ['admin.error.page_slug_invalid', 'The page URL slug is invalid.'],
        'Yayın durumu geçersiz.' => ['admin.error.page_status_invalid', 'The publication status is invalid.'],
        'SEO alanlarından biri izin verilen uzunluğu aşıyor.' => ['admin.error.seo_field_too_long', 'One of the SEO fields exceeds the allowed length.'],
        'Canonical adresi HTTPS veya site içi yol olmalı.' => ['admin.error.canonical_invalid', 'The canonical URL must be HTTPS or an internal path.'],
        'Planlı yayın için gelecekte geçerli bir tarih ve saat seçin.' => ['admin.error.scheduled_date_invalid', 'Choose a valid future date and time for scheduled publishing.'],
        'Sosyal paylaşım görseli medya kütüphanesinde bulunamadı.' => ['admin.error.social_image_missing', 'The social sharing image was not found in the media library.'],
        'Sayfa bulunamadı.' => ['admin.error.page_not_found', 'The page was not found.'],
        'Çeviri dili etkin olmalıdır.' => ['admin.error.translation_locale_disabled', 'The translation language must be enabled.'],
        'Ana dilin içeriği doğrudan düzenlenmelidir.' => ['admin.error.edit_base_locale', 'Edit the base-language content directly.'],
        'Çeviri sayfa başlığı zorunludur (en fazla 255 karakter).' => ['admin.error.translation_page_title_required', 'Translated page title is required and cannot exceed 255 characters.'],
        'Çeviri sayfa bağlantısı geçersiz.' => ['admin.error.translation_slug_invalid', 'The translated page URL slug is invalid.'],
        'Çeviri sayfa bağlantısı başka bir sayfa tarafından kullanılıyor.' => ['admin.error.translation_slug_in_use', 'The translated page URL slug is already used by another page.'],
        'Menü öğesi bulunamadı.' => ['admin.error.menu_item_not_found', 'The menu item was not found.'],
        'Menü çeviri etiketi zorunludur (en fazla 190 karakter).' => ['admin.error.translation_menu_label_required', 'Translated menu label is required and cannot exceed 190 characters.'],
        'Sayfa zaten yöneticiye ait.' => ['admin.error.page_already_owned', 'The page is already managed independently.'],
        'Modül tarafından sağlanan sayfalar silinemez; önce modül içeriğinden ayırın.' => ['admin.error.module_page_delete', 'Module-provided pages cannot be deleted. Detach the page from its module first.'],
        'Menü anahtarı 1-100 karakter olmalı; harf, rakam, nokta, tire ve alt çizgi kullanılabilir.' => ['admin.error.menu_key_invalid', 'Menu key must be 1–100 characters and contain only letters, numbers, dots, hyphens and underscores.'],
        'Menü etiketi zorunludur (en fazla 190 karakter).' => ['admin.error.menu_label_required', 'Menu label is required and cannot exceed 190 characters.'],
        'Menü öğesi zaten yöneticiye ait.' => ['admin.error.menu_already_owned', 'The menu item is already managed independently.'],
        'Modül tarafından sağlanan menü öğeleri silinemez.' => ['admin.error.module_menu_delete', 'Module-provided menu items cannot be deleted.'],

        'Yönlendirme adresi güvenli bir site içi yol olmalı.' => ['admin.error.redirect_path_invalid', 'The redirect address must be a safe internal path.'],
        'Kaynak adres kök dizin olamaz.' => ['admin.error.redirect_root_source', 'The root path cannot be used as a redirect source.'],
        'Bu sistem adresi yönlendirme kaynağı olarak kullanılamaz.' => ['admin.error.redirect_reserved_source', 'This system path cannot be used as a redirect source.'],
        'Kaynak ve hedef adres aynı olamaz.' => ['admin.error.redirect_same_path', 'The source and destination paths cannot be the same.'],
        'Yalnızca 301 veya 302 yönlendirmesi kullanılabilir.' => ['admin.error.redirect_code_invalid', 'Only 301 or 302 redirects are supported.'],
        'Bu kaynak adres için zaten yönlendirme var.' => ['admin.error.redirect_source_exists', 'A redirect already exists for this source path.'],
        'Hedef adres başka bir yönlendirmeye gidiyor; yönlendirme zinciri oluşturulamaz.' => ['admin.error.redirect_chain', 'The destination points to another redirect; redirect chains are not allowed.'],
        'Yönlendirme kaydı bulunamadı.' => ['admin.error.redirect_not_found', 'The redirect record was not found.'],

        'Geçersiz yükleme kaynağı.' => ['admin.error.upload_source_invalid', 'The upload source is invalid.'],
        'Dosya boş olamaz ve en fazla 8 MB olabilir.' => ['admin.error.upload_size_invalid', 'The file cannot be empty and must be no larger than 8 MB.'],
        'Yalnızca JPEG, PNG, GIF ve WebP görselleri yüklenebilir.' => ['admin.error.upload_type_invalid', 'Only JPEG, PNG, GIF and WebP images can be uploaded.'],
        'Dosya geçerli bir görsel değil.' => ['admin.error.upload_image_invalid', 'The file is not a valid image.'],
        'Medya klasörü oluşturulamadı.' => ['admin.error.media_directory_failed', 'The media directory could not be created.'],
        'Görsel sunucuya kaydedilemedi.' => ['admin.error.media_save_failed', 'The image could not be saved on the server.'],
        'Medya kaydı bulunamadı.' => ['admin.error.media_record_not_found', 'The media record was not found.'],
        'Güvenli olmayan medya yolu; dosya silinmedi.' => ['admin.error.media_unsafe_path', 'The media path is unsafe; the file was not deleted.'],
        'Medya yolu doğrulanamadı; dosya silinmedi.' => ['admin.error.media_path_unverified', 'The media path could not be verified; the file was not deleted.'],

        'Dil kodu geçersiz. Örnek: tr, en veya en-US.' => ['admin.error.locale_code_invalid', 'The locale code is invalid. Examples: tr, en or en-US.'],
        'Dil adı zorunludur ve 80 karakteri aşamaz.' => ['admin.error.locale_name_invalid', 'Language name is required and cannot exceed 80 characters.'],
        'Varsayılan dil kodu geçersiz.' => ['admin.error.default_locale_invalid', 'The default locale code is invalid.'],
        'Varsayılan dil etkin bir dil olmalıdır.' => ['admin.error.default_locale_disabled', 'The default locale must be enabled.'],
        'Çeviri dili geçersiz.' => ['admin.error.translation_locale_invalid', 'The translation locale is invalid.'],
        'Çeviri anahtarı geçersiz.' => ['admin.error.translation_key_invalid', 'The translation key is invalid.'],
        'Çeviri metni 10.000 karakteri aşamaz.' => ['admin.error.translation_value_too_long', 'Translation text cannot exceed 10,000 characters.'],
        'Çeviri eklemek için dil etkin olmalıdır.' => ['admin.error.translation_locale_required', 'The language must be enabled before adding translations.'],

        'Tema etkin değil veya bulunamadı.' => ['admin.error.theme_unavailable', 'The theme is not enabled or could not be found.'],
        'Tema şablonu tanımlanmamış.' => ['admin.error.theme_template_missing', 'The theme template is not defined.'],
        'Tema şablon dosyası bulunamadı.' => ['admin.error.theme_file_missing', 'The theme template file could not be found.'],
        'Tema şablonu oluşturulamadı: ' => ['admin.error.theme_render_failed', 'The theme could not be rendered: '],
        'Tema varlığı manifest içinde tanımlı değil.' => ['admin.error.theme_asset_undefined', 'The theme asset is not declared in the manifest.'],
        'Seçilen tema etkin bir modülde bulunamadı veya geçerli bir layout.php dosyası yok.' => ['admin.error.theme_invalid', 'The selected theme is not in an enabled module or has no valid layout.php file.'],
    ];

    public static function translate(string $message, callable $translate): string
    {
        if (isset(self::MESSAGES[$message])) {
            [$key, $fallback] = self::MESSAGES[$message];
            return (string)$translate($key, $fallback);
        }
        foreach (self::MESSAGES as $source => [$key, $fallback]) {
            if (str_starts_with($message, $source) && $source !== 'Tema şablonu oluşturulamadı: ') {
                return (string)$translate($key, $fallback);
            }
        }
        if (str_starts_with($message, 'Tema şablonu oluşturulamadı: ')) {
            $detail = substr($message, strlen('Tema şablonu oluşturulamadı: '));
            return (string)$translate('admin.error.theme_render_failed', 'The theme could not be rendered: ') . $detail;
        }
        return $message;
    }
}
