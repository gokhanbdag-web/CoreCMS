<?php
declare(strict_types=1);
return static function (PDO $db): void {
    $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql = $driver === 'sqlite'
        ? 'INSERT OR IGNORE INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?)'
        : 'INSERT IGNORE INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?)';
    $insert = $db->prepare($sql);
    $entries = [
        "admin.core_updates.title" => ["Çekirdek güncellemeleri", "Core updates"],
        "admin.core_updates.pending_suffix" => ["bekleyen migrasyon.", "pending migrations."],
        "admin.core_updates.apply" => ["Migrasyonları uygula", "Apply migrations"],
        "admin.core_updates.current" => ["Veritabanı güncel.", "Database is up to date."],
        "admin.modules.manager" => ["Modül yöneticisi", "Module manager"],
        "admin.modules.enabled" => ["Etkin", "Enabled"],
        "admin.modules.installed_disabled" => ["Kurulu / pasif", "Installed / disabled"],
        "admin.modules.not_installed" => ["Kurulmamış", "Not installed"],
        "admin.modules.install" => ["Kur", "Install"],
        "admin.modules.update" => ["Güncelle", "Update"],
        "admin.modules.disable_before_update" => ["Güncellemek için önce modülü devre dışı bırakın.", "Disable the module before updating it."],
        "admin.modules.disable" => ["Devre dışı bırak", "Disable"],
        "admin.modules.enable" => ["Etkinleştir", "Enable"],
        "admin.modules.confirm_rollback" => ["Son modül güncellemesi geri alınacak. Devam edilsin mi?", "The latest module update will be rolled back. Continue?"],
        "admin.modules.rollback" => ["Son güncellemeyi geri al", "Roll back latest update"],
        "admin.modules.confirm_uninstall" => ["Modül kaldırılacak. Değiştirilmiş sayfalar ve menüler korunacak; modül tabloları ve verileri silinmeyecek. Devam edilsin mi?", "The module will be uninstalled. Modified pages and menus will be preserved; module tables and data will not be deleted. Continue?"],
        "admin.modules.uninstall" => ["Modülü güvenli kaldır", "Safely uninstall module"],
        "admin.demos.title" => ["Demo paketleri", "Demo packages"],
        "admin.demos.intro" => ["Önizlemede paket içeriği ve kurulum durumu gösterilir. Kurulum yalnızca etkin modüllerde yapılır. Sıfırlama ve kaldırma sırasında düzenlenmiş veya revizyon geçmişi olan içerikler korunur.", "The preview shows package contents and installation status. Packages can only be installed for enabled modules. Edited content and content with revision history is preserved when resetting or removing a package."],
        "admin.demos.pages_count_suffix" => ["sayfa", "pages"],
        "admin.demos.menus_count_suffix" => ["menü", "menus"],
        "admin.demos.page_preview" => ["Sayfa önizlemesi", "Page preview"],
        "admin.demos.menu_preview" => ["Menü önizlemesi", "Menu preview"],
        "admin.demos.installed" => ["Kurulu", "Installed"],
        "admin.demos.ready" => ["Kuruluma hazır", "Ready to install"],
        "admin.demos.enable_module_first" => ["Önce modülü kurup etkinleştirin", "Install and enable the module first"],
        "admin.demos.confirm_reset" => ["Demo varsayılanları yeniden uygulanacak. Düzenlenmiş içerikler korunacak. Devam edilsin mi?", "Demo defaults will be applied again. Edited content will be preserved. Continue?"],
        "admin.demos.reset" => ["Demo paketini sıfırla", "Reset demo package"],
        "admin.demos.confirm_remove" => ["Demo kaldırılacak. Değiştirilmiş içerikler korunacak. Devam edilsin mi?", "The demo package will be removed. Modified content will be preserved. Continue?"],
        "admin.demos.remove" => ["Demo paketini kaldır", "Remove demo package"],
        "admin.demos.confirm_install" => ["Demo sayfaları ve menüleri eklenecek. Devam edilsin mi?", "Demo pages and menus will be added. Continue?"],
        "admin.demos.install" => ["Demo paketini kur", "Install demo package"]
    ];
    foreach ($entries as $key => $values) {
        $insert->execute(['tr', $key, $values[0]]);
        $insert->execute(['en', $key, $values[1]]);
    }
};
