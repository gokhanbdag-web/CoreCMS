<?php
declare(strict_types=1);

return static function (PDO $db): void {
    $driver=(string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql=$driver==='sqlite'
        ? 'INSERT OR IGNORE INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?)'
        : 'INSERT IGNORE INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?)';
    $insert=$db->prepare($sql);
    $entries=[
        'admin.settings.mail_sender'=>['E-posta gönderim ayarları','Email sender settings'],
        'admin.settings.mail_from_name'=>['Gönderici adı','Sender name'],
        'admin.settings.mail_from_email'=>['Gönderici e-posta adresi','Sender email address'],
        'admin.settings.mail_help'=>['Bildirimler PHP mail() ile gönderilir. Hosting sağlayıcınızın kabul ettiği bir gönderici adresi girin.','Notifications are sent using PHP mail(). Configure a sender address accepted by your hosting provider.'],
        'admin.email.queue_title'=>['E-posta gönderim kuyruğu','Email delivery queue'],
        'admin.email.queue_help'=>['E-postalar hemen gönderilmeye çalışılır; sunucu gönderemezse artan aralıklarla yeniden denenir. Site ayarlarından gönderici adresini yapılandırın.','Messages are attempted immediately and retried with increasing delays when the server cannot send them. Configure the sender address in site settings.'],
        'admin.email.queue_retried'=>['E-posta yeniden gönderim kuyruğuna alındı.','Delivery retry queued.'],
        'admin.email.recipient'=>['Alıcı','Recipient'],
        'admin.email.queue_status'=>['Durum','Status'],
        'admin.email.attempts'=>['Deneme','Attempts'],
        'admin.email.last_error'=>['Son gönderim notu','Last delivery note'],
        'admin.email.retry'=>['Yeniden dene','Retry'],
        'admin.email.queue_empty'=>['Henüz e-posta bildirimi kuyruğa alınmadı.','No email notifications have been queued.'],
    ];
    foreach($entries as $key=>$values){
        $insert->execute(['tr',$key,$values[0]]);
        $insert->execute(['en',$key,$values[1]]);
    }
};
