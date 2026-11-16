<?php
declare(strict_types=1);
return static function (PDO $db): void {
    $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql = $driver === 'sqlite'
        ? 'INSERT OR IGNORE INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?)'
        : 'INSERT IGNORE INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?)';
    $insert = $db->prepare($sql);
    $entries = [
        "frontend.home.admin_button" => ["Yönetim panelini aç", "Open administration"],
        "frontend.home.enabled_modules" => ["Etkin modüller", "Enabled modules"],
        "frontend.home.no_modules" => ["Henüz etkin sektör modülü bulunmuyor.", "No sector modules are enabled yet."],
        "frontend.404.title" => ["Sayfa bulunamadı", "Page not found"],
        "frontend.404.description" => ["Aradığınız içerik mevcut değil.", "The content you are looking for is unavailable."],
        "frontend.nav.admin" => ["Yönetim", "Administration"],
        "frontend.language_switcher" => ["Dil seçimi", "Select language"]
    ];
    foreach ($entries as $key => $values) {
        $insert->execute(['tr', $key, $values[0]]);
        $insert->execute(['en', $key, $values[1]]);
    }
};
