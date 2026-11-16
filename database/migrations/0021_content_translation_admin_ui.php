<?php
declare(strict_types=1);
return static function (PDO $db): void {
    $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql = $driver === 'sqlite'
        ? 'INSERT OR IGNORE INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?)'
        : 'INSERT IGNORE INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?)';
    $insert = $db->prepare($sql);
    $entries = [
        "admin.content.language_label" => ["İçerik dili", "Content language"],
        "admin.content.change_language" => ["Dili yükle", "Load language"],
        "admin.content.translation_help" => ["Başlık, URL, içerik ve SEO alanlarını seçilen dil için ayrı ayrı düzenleyebilirsiniz.", "Edit the title, URL, body and SEO fields independently for the selected language."],
        "admin.content.menu_translation_help" => ["Bu menü öğesinin etiketini ve bağlantısını seçilen dil için ayrı ayrı düzenleyebilirsiniz.", "Edit this menu item's label and URL independently for the selected language."],
        "admin.content.translation_missing" => ["Bu dil için henüz çeviri yok. Başlangıç olarak ana dildeki içerik gösteriliyor.", "No translation exists for this language yet. Base-language content is shown as a starting point."],
        "admin.content.save_base_first" => ["Çeviri eklemeden önce sayfayı ana dilde kaydedin.", "Save the page in the base language before adding a translation."],
        "admin.content.save_menu_base_first" => ["Çeviri eklemeden önce menü öğesini ana dilde kaydedin.", "Save the menu item in the base language before adding a translation."]
    ];
    foreach ($entries as $key => $values) {
        $insert->execute(['tr', $key, $values[0]]);
        $insert->execute(['en', $key, $values[1]]);
    }
};
