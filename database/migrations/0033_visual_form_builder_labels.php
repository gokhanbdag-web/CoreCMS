<?php
declare(strict_types=1);

return static function (PDO $db): void {
    $driver=(string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql=$driver==='sqlite'
        ? 'INSERT INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?) ON CONFLICT(locale_code,translation_key) DO UPDATE SET translation_value=excluded.translation_value,updated_at=CURRENT_TIMESTAMP'
        : 'INSERT INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?) ON DUPLICATE KEY UPDATE translation_value=VALUES(translation_value),updated_at=CURRENT_TIMESTAMP';
    $insert=$db->prepare($sql);
    $entries=[
        'admin.forms.field_builder'=>['Form alanları','Form fields'],
        'admin.forms.field_builder_help'=>['Alan ekleyin, türünü seçin, zorunlu yapın ve sırasını değiştirin. Açılır liste seçeneklerini satır satır yazın.','Add fields, choose their type, mark required fields and change their order. Enter dropdown options one per line.'],
        'admin.forms.field'=>['Alan','Field'],
        'admin.forms.field_key'=>['Alan anahtarı','Field key'],
        'admin.forms.field_type'=>['Alan türü','Field type'],
        'admin.forms.field_type_text'=>['Metin','Text'],
        'admin.forms.field_type_email'=>['E-posta','Email'],
        'admin.forms.field_type_tel'=>['Telefon','Phone'],
        'admin.forms.field_type_textarea'=>['Uzun metin','Long text'],
        'admin.forms.field_type_select'=>['Açılır liste','Dropdown'],
        'admin.forms.field_type_checkbox'=>['Onay kutusu','Checkbox'],
        'admin.forms.field_required'=>['Zorunlu alan','Required field'],
        'admin.forms.field_options'=>['Açılır liste seçenekleri','Dropdown options'],
        'admin.forms.move_up'=>['Yukarı taşı','Move up'],
        'admin.forms.move_down'=>['Aşağı taşı','Move down'],
        'admin.forms.remove_field'=>['Alanı sil','Remove field'],
        'admin.forms.add_field'=>['Alan ekle','Add field'],
        'admin.forms.max_fields'=>['Bir form en fazla 30 alan içerebilir.','A form can contain at most 30 fields.'],
    ];
    foreach($entries as $key=>$values){
        $insert->execute(['tr',$key,$values[0]]);
        $insert->execute(['en',$key,$values[1]]);
    }
};
