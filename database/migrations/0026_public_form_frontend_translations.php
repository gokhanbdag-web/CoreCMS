<?php
declare(strict_types=1);

return static function (PDO $db): void {
    $driver=(string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql=$driver==='sqlite'
        ? 'INSERT OR IGNORE INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?)'
        : 'INSERT IGNORE INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?)';
    $insert=$db->prepare($sql);
    $entries=[
        'frontend.forms.submit'=>['Gönder','Send'],
        'frontend.forms.success'=>['Mesajınız alındı.','Your message has been received.'],
        'frontend.forms.error'=>['Form gönderilemedi. Lütfen tekrar deneyin.','The form could not be submitted. Please try again.'],
    ];
    foreach($entries as $key=>$values){
        $insert->execute(['tr',$key,$values[0]]);
        $insert->execute(['en',$key,$values[1]]);
    }
};
