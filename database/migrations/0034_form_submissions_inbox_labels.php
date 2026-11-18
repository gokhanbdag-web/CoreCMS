<?php
declare(strict_types=1);
return static function (PDO $db): void {
    $driver=(string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql=$driver==='sqlite'
        ? 'INSERT INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?) ON CONFLICT(locale_code,translation_key) DO UPDATE SET translation_value=excluded.translation_value,updated_at=CURRENT_TIMESTAMP'
        : 'INSERT INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?) ON DUPLICATE KEY UPDATE translation_value=VALUES(translation_value),updated_at=CURRENT_TIMESTAMP';
    $insert=$db->prepare($sql);
    $entries=[
        'admin.submissions.all_forms'=>['Tüm formlar','All forms'],
        'admin.submissions.all_statuses'=>['Tüm durumlar','All statuses'],
        'admin.submissions.all_languages'=>['Tüm diller','All languages'],
        'admin.submissions.date_from'=>['Başlangıç tarihi','From date'],
        'admin.submissions.date_to'=>['Bitiş tarihi','To date'],
        'admin.submissions.search'=>['Ara','Search'],
        'admin.submissions.search_placeholder'=>['Form adı, anahtar veya gönderi içeriği','Form name, key or submitted content'],
        'admin.submissions.filter'=>['Filtrele','Filter'],
        'admin.submissions.clear'=>['Filtreleri temizle','Clear filters'],
        'admin.submissions.view_details'=>['Gönderi ayrıntılarını görüntüle','View submission details'],
        'admin.submissions.reply'=>['E-posta ile yanıtla','Reply by email'],
        'admin.submissions.reply_subject'=>['Mesajınız hakkında','Regarding your enquiry'],
        'admin.submissions.archive'=>['Arşivle','Archive'],
        'admin.submissions.restore'=>['Arşivden çıkar','Restore'],
        'admin.submissions.mark_read'=>['Okundu olarak işaretle','Mark as read'],
    ];
    foreach($entries as $key=>$values){$insert->execute(['tr',$key,$values[0]]);$insert->execute(['en',$key,$values[1]]);}
};
