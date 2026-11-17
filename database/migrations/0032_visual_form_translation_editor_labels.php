<?php
declare(strict_types=1);

return static function (PDO $db): void {
    $driver=(string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql=$driver==='sqlite'
        ? 'INSERT INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?) ON CONFLICT(locale_code,translation_key) DO UPDATE SET translation_value=excluded.translation_value,updated_at=CURRENT_TIMESTAMP'
        : 'INSERT INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?) ON DUPLICATE KEY UPDATE translation_value=VALUES(translation_value),updated_at=CURRENT_TIMESTAMP';
    $insert=$db->prepare($sql);
    $entries=[
        'admin.forms.visual_translation_help'=>['Çevirileri aşağıdaki alanlara girin. Boş bıraktığınız alanlar özgün metni kullanır.','Enter translations below. Leave a field empty to keep its original text.'],
        'admin.forms.form_details'=>['Form bilgileri','Form details'],
        'admin.forms.field_label'=>['Alan etiketi','Field label'],
        'admin.forms.option_labels'=>['Seçenek etiketleri','Option labels'],
    ];
    foreach($entries as $key=>$values){
        $insert->execute(['tr',$key,$values[0]]);
        $insert->execute(['en',$key,$values[1]]);
    }
};
