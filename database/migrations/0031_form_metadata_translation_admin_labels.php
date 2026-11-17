<?php
declare(strict_types=1);

return static function (PDO $db): void {
    $driver=(string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql=$driver==='sqlite'
        ? 'INSERT OR IGNORE INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?)'
        : 'INSERT IGNORE INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?)';
    $insert=$db->prepare($sql);
    $entries=[
        'admin.forms.translations_title'=>['Form içeriği çevirileri','Form content translations'],
        'admin.forms.translations_help'=>['Form adını, açıklamasını, başarı mesajını, alan etiketlerini ve seçim seçeneklerini çevirebilirsiniz. Doğrulama için seçeneklerin özgün değerleri korunur.','Translate the form name, description, success message, field labels and select option labels. Original option values remain unchanged for validation.'],
        'admin.forms.translations_json'=>['Form ve alan çevirileri (JSON)','Form and field translations (JSON)'],
        'admin.forms.translations_json_help'=>['Örnek: {"_form":{"name":"Bize ulaşın","description":"Mesaj gönderin","success_message":"Teşekkürler!"},"name":{"label":"Adınız"}}','Example: {"_form":{"name":"Contact us","description":"Send a message","success_message":"Thanks!"},"name":{"label":"Your name"}}'],
    ];
    foreach($entries as $key=>$values){
        $insert->execute(['tr',$key,$values[0]]);
        $insert->execute(['en',$key,$values[1]]);
    }
};
