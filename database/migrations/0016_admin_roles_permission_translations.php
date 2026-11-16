<?php
declare(strict_types=1);
return static function (PDO $db): void {
    $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql = $driver === 'sqlite'
        ? 'INSERT OR IGNORE INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?)'
        : 'INSERT IGNORE INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?)';
    $insert = $db->prepare($sql);
    $entries = [
        "admin.permission.dashboard_view" => ["Kontrol panelini görüntüleme", "View dashboard"],
        "admin.permission.pages_manage" => ["Sayfa oluşturma ve düzenleme", "Create and edit pages"],
        "admin.permission.menus_manage" => ["Menüleri yönetme", "Manage menus"],
        "admin.permission.media_manage" => ["Medya kütüphanesini yönetme", "Manage media library"],
        "admin.permission.seo_manage" => ["SEO ve yönlendirmeleri yönetme", "Manage SEO and redirects"],
        "admin.permission.modules_manage" => ["Modülleri yönetme", "Manage modules"],
        "admin.permission.demos_manage" => ["Demo paketlerini yönetme", "Manage demo packages"],
        "admin.permission.appearance_manage" => ["Tema ve görünümü yönetme", "Manage themes and appearance"],
        "admin.permission.settings_manage" => ["Site ayarlarını yönetme", "Manage site settings"],
        "admin.permission.users_manage" => ["Kullanıcı ve rolleri yönetme", "Manage users and roles"]
        ,"admin.access.forbidden" => ["Bu bölümü görüntülemek için yetkiniz bulunmuyor.","You are not allowed to view this section."]
    ];
    foreach ($entries as $key => $values) {
        $insert->execute(['tr', $key, $values[0]]);
        $insert->execute(['en', $key, $values[1]]);
    }
};
