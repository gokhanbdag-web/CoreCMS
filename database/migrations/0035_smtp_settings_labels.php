<?php
declare(strict_types=1);
return static function (PDO $db): void {
    $driver=(string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql=$driver==='sqlite'
        ? 'INSERT INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?) ON CONFLICT(locale_code,translation_key) DO UPDATE SET translation_value=excluded.translation_value,updated_at=CURRENT_TIMESTAMP'
        : 'INSERT INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?) ON DUPLICATE KEY UPDATE translation_value=VALUES(translation_value),updated_at=CURRENT_TIMESTAMP';
    $insert=$db->prepare($sql);
    $entries=[
        'admin.settings.smtp_enabled'=>['PHP mail() yerine SMTP kullan','Use SMTP instead of PHP mail()'],
        'admin.settings.smtp_host'=>['SMTP sunucusu','SMTP server'],
        'admin.settings.smtp_port'=>['SMTP portu','SMTP port'],
        'admin.settings.smtp_security'=>['Bağlantı güvenliği','Connection security'],
        'admin.settings.smtp_username'=>['SMTP kullanıcı adı','SMTP username'],
        'admin.settings.smtp_password'=>['SMTP parolası','SMTP password'],
        'admin.settings.smtp_password_saved'=>['Parola kayıtlı; korumak için boş bırakın','A password is already saved; leave blank to keep it'],
        'admin.settings.smtp_password_empty'=>['SMTP parolasını girin','Enter SMTP password'],
        'admin.settings.smtp_timeout'=>['Bağlantı zaman aşımı (saniye)','Connection timeout (seconds)'],
    ];
    foreach($entries as $key=>$values){$insert->execute(['tr',$key,$values[0]]);$insert->execute(['en',$key,$values[1]]);}
};
