<?php
declare(strict_types=1);

return static function (PDO $db): void {
    $driver=(string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql=$driver==='sqlite'
        ? 'INSERT OR IGNORE INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?)'
        : 'INSERT IGNORE INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?)';
    $insert=$db->prepare($sql);
    $entries=[
        'admin.forms.translations_title'=>['Form alanı çevirileri','Form field translations'],
        'admin.forms.translations_help'=>['Bir dil seçerek alan etiketlerini ve seçenek etiketlerini çevirebilirsiniz. Doğrulama için orijinal seçenek değerleri korunur.','Choose a language to translate field labels and select option labels. Original option values remain unchanged for validation.'],
        'admin.forms.translations_json'=>['Alan çevirileri (JSON)','Field translations (JSON)'],
        'admin.forms.translations_json_help'=>['Örnek: {"name":{"label":"Adınız"},"topic":{"label":"Konu","options":{"Sales":"Satış","Support":"Destek"}}}','Example: {"name":{"label":"Your name"},"topic":{"label":"Topic","options":{"Sales":"Sales","Support":"Help"}}}'],
    ];
    foreach($entries as $key=>$values){
        $insert->execute(['tr',$key,$values[0]]);
        $insert->execute(['en',$key,$values[1]]);
    }
};
