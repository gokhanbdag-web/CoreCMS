<?php
declare(strict_types=1);
return static function (PDO $db): void {
    $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql = $driver === 'sqlite'
        ? 'INSERT OR IGNORE INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?)'
        : 'INSERT IGNORE INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?)';
    $insert = $db->prepare($sql);
    $entries = [
        "admin.seo.audit_title" => ["Sayfa SEO denetimi", "Page SEO audit"],
        "admin.seo.bulk_title" => ["Toplu SEO düzenleme", "Bulk SEO editing"],
        "admin.seo.title_template" => ["SEO başlığı şablonu", "SEO title template"],
        "admin.seo.description_template" => ["Meta açıklama şablonu", "Meta description template"],
        "admin.seo.apply_mode" => ["Uygulama şekli", "Apply mode"],
        "admin.seo.broken_links" => ["Bozuk site içi bağlantılar", "Broken internal links"],
        "admin.redirects.list" => ["Yönlendirme kayıtları", "Redirect records"],
        "admin.redirects.edit" => ["Yönlendirmeyi düzenle", "Edit redirect"],
        "admin.redirects.new" => ["Yeni yönlendirme", "New redirect"],
        "admin.redirects.source" => ["Eski adres", "Old path"],
        "admin.redirects.destination" => ["Yeni hedef adres", "New destination"],
        "admin.redirects.http_code" => ["HTTP kodu", "HTTP status code"],
        "admin.redirects.save" => ["Yönlendirmeyi kaydet", "Save redirect"],
        "admin.redirects.confirm_delete" => ["Yönlendirme silinsin mi?", "Delete this redirect?"],
        "admin.media.upload_title" => ["Görsel yükle", "Upload image"],
        "admin.media.file" => ["Görsel dosyası", "Image file"],
        "admin.media.alt_text" => ["Alternatif metin (erişilebilirlik)", "Alternative text (accessibility)"],
        "admin.media.library" => ["Medya kütüphanesi", "Media library"],
        "admin.media.image_url" => ["Görsel URL", "Image URL"],
        "admin.media.delete" => ["Görseli sil", "Delete image"],
        "admin.pages.edit" => ["Sayfayı düzenle", "Edit page"],
        "admin.pages.new" => ["Yeni sayfa", "New page"],
        "admin.pages.title" => ["Başlık", "Title"],
        "admin.pages.slug" => ["URL kısa adı", "URL slug"],
        "admin.pages.content" => ["İçerik", "Content"],
        "admin.pages.status" => ["Durum", "Status"],
        "admin.pages.seo_settings" => ["SEO ayarları", "SEO settings"],
        "admin.pages.seo_title" => ["SEO başlığı", "SEO title"],
        "admin.pages.meta_description" => ["Meta açıklama", "Meta description"],
        "admin.pages.canonical_url" => ["Canonical URL", "Canonical URL"],
        "admin.pages.revision_history" => ["Revizyon geçmişi", "Revision history"],
        "admin.pages.list" => ["Sayfalar", "Pages"],
        "admin.pages.confirm_delete" => ["Sayfa silinsin mi?", "Delete this page?"],
        "admin.pages.link_prompt" => ["HTTPS, mailto veya site içi /yol adresi:", "Enter an HTTPS, mailto or internal /path URL:"],
        "admin.pages.link_invalid" => ["Geçerli ve güvenli bir bağlantı girin.", "Enter a valid and secure link."],
        "admin.menus.edit_item" => ["Menü öğesini düzenle", "Edit menu item"],
        "admin.menus.new_item" => ["Yeni menü öğesi", "New menu item"],
        "admin.menus.menu_key" => ["Menü anahtarı", "Menu key"],
        "admin.menus.label" => ["Etiket", "Label"],
        "admin.menus.link" => ["Bağlantı", "Link"],
        "admin.menus.items" => ["Menü öğeleri", "Menu items"],
        "admin.menus.confirm_delete" => ["Öğe silinsin mi?", "Delete this menu item?"]
    ];
    foreach ($entries as $key => $values) {
        $insert->execute(['tr', $key, $values[0]]);
        $insert->execute(['en', $key, $values[1]]);
    }
};
