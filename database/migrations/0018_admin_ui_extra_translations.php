<?php
declare(strict_types=1);
return static function (PDO $db): void {
    $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql = $driver === 'sqlite'
        ? 'INSERT OR IGNORE INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?)'
        : 'INSERT IGNORE INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?)';
    $insert = $db->prepare($sql);
    $entries = [
        "admin.seo.description_placeholder" => ["{title} hakkında detaylı bilgi. {site_name}", "Detailed information about {title}. {site_name}"],
        "admin.common.select" => ["Seç", "Select"],
        "admin.common.page" => ["Sayfa", "Page"],
        "admin.seo.target" => ["Hedef", "Target"],
        "admin.seo.select_page" => ["Sayfasını seç", "Select page"],
        "admin.seo.pages_updated_suffix" => ["sayfanın SEO alanları güncellendi.", "pages SEO fields updated."],
        "admin.redirects.empty" => ["Henüz yönlendirme kaydı yok.", "No redirects have been created yet."],
        "admin.media.search_placeholder" => ["Dosya veya alt metin ara", "Search file or alt text"],
        "admin.media.confirm_delete" => ["Bu görsel ve dosyası silinsin mi?", "Delete this image and its file?"],
        "admin.media.empty" => ["Henüz görsel yok. İlk görseli yukarıdan yükleyebilirsiniz.", "No images yet. You can upload the first image above."],
        "admin.pages.heading" => ["Başlık", "Heading"],
        "admin.pages.link" => ["Bağlantı", "Link"],
        "admin.pages.open_preview" => ["Önizlemeyi aç", "Open preview"],
        "admin.pages.alignment" => ["Hizalama", "Alignment"],
        "admin.pages.insert_into_editor" => ["görselini editöre ekle", "Insert into editor"],
        "admin.menus.confirm_detach" => ["Menü öğesi modülden ayrılıp bağımsız hale getirilsin mi?", "Detach this menu item from its module and make it independent?"],
        "admin.access.action_forbidden" => ["Bu işlem için yetkiniz bulunmuyor.", "You are not allowed to perform this action."],
        "admin.access.migrations_admin_only" => ["Veritabanı güncellemelerini yalnızca yöneticiler uygulayabilir.", "Only administrators can apply database updates."],
        "admin.error.apply_pending_migrations" => ["Önce bekleyen veritabanı migrasyonlarını uygulayın.", "Apply pending database migrations first."],
        "admin.error.page_revision_migrations" => ["Sayfa revizyonları için bekleyen veritabanı migrasyonlarını önce uygulayın.", "Apply pending database migrations before using page revisions."],
        "admin.notice.migrations_applied" => ["%d çekirdek migrasyonu uygulandı.", "%d core migrations applied."],
        "admin.notice.demo_already_installed" => ["Demo paketi zaten kurulu.", "This demo package is already installed."],
        "admin.notice.demo_installed" => ["Demo paketi kuruldu: %d sayfa, %d menü.", "Demo package installed: %d pages, %d menu items."],
        "admin.notice.demo_reset" => ["Demo sıfırlandı. Korunan içerik: %d sayfa, %d menü.", "Demo reset. Preserved content: %d pages, %d menu items."],
        "admin.notice.demo_removed" => ["Demo kaldırıldı. Korunan içerik: %d sayfa, %d menü.", "Demo removed. Preserved content: %d pages, %d menu items."],
        "admin.notice.module_installed" => ["Modül kuruldu veya güncellendi.", "Module installed or updated."],
        "admin.notice.module_enabled" => ["Modül etkinleştirildi ve içeriği eşitlendi.", "Module enabled and its content synchronized."],
        "admin.notice.module_disabled" => ["Modül devre dışı bırakıldı.", "Module disabled."],
        "admin.notice.module_uninstalled" => ["Modül kaldırıldı. Değiştirilmiş içerik korundu: %d sayfa, %d menü.", "Module removed. Modified content preserved: %d pages, %d menu items."],
        "admin.notice.module_updated" => ["Modül güncellendi. Uygulanan migrasyon: %d.", "Module updated. Applied migrations: %d."],
        "admin.notice.module_rolled_back" => ["Modül %s sürümünden %s sürümüne geri alındı.", "Module rolled back from version %s to %s."],
        "admin.access.section_forbidden" => ["Bu bölümü görüntülemek için yetkiniz bulunmuyor.", "You are not allowed to view this section."],
        "admin.notice.page_revision_restored" => ["Sayfa revizyonu geri yüklendi; önceki sürüm de geçmişe kaydedildi.", "Page revision restored; the previous version was also saved to history."],
        "admin.notice.media_uploaded" => ["Görsel kütüphaneye eklendi.", "Image added to the media library."],
        "admin.notice.saved" => ["Değişiklikler kaydedildi.", "Changes saved."],
        "admin.notice.deleted" => ["Kayıt silindi.", "Record deleted."],
        "admin.notice.content_detached" => ["İçerik modülden ayrıldı ve artık bağımsız yönetiliyor.", "Content detached from its module and is now managed independently."]
    ];
    foreach ($entries as $key => $values) {
        $insert->execute(['tr', $key, $values[0]]);
        $insert->execute(['en', $key, $values[1]]);
    }
};
