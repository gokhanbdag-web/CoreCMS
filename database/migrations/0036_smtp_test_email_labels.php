<?php
declare(strict_types=1);

return static function (PDO $db): void {
    $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql = $driver === 'sqlite'
        ? 'INSERT INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?) ON CONFLICT(locale_code,translation_key) DO UPDATE SET translation_value=excluded.translation_value,updated_at=CURRENT_TIMESTAMP'
        : 'INSERT INTO core_translations(locale_code,translation_key,translation_value) VALUES(?,?,?) ON DUPLICATE KEY UPDATE translation_value=VALUES(translation_value),updated_at=CURRENT_TIMESTAMP';
    $insert = $db->prepare($sql);
    $entries = [
        'admin.settings.smtp_test_title' => ['Test e-postası gönder', 'Send a test email'],
        'admin.settings.smtp_test_help' => ['Önce SMTP ayarlarınızı kaydedin, ardından gönderimi doğrulamak için test mesajı gönderin.', 'Save your SMTP settings first, then send a test message to verify delivery.'],
        'admin.settings.smtp_test_recipient' => ['Test alıcı e-posta adresi', 'Test recipient email'],
        'admin.settings.smtp_test_send' => ['Test e-postası gönder', 'Send test email'],
        'admin.settings.smtp_test_enable_first' => ['Test e-postası göndermeden önce SMTP’yi etkinleştirip ayarları kaydedin.', 'Enable SMTP and save settings before sending a test email.'],
        'admin.settings.smtp_test_sent' => ['Test e-postası SMTP sunucusu tarafından kabul edildi.', 'A test email was accepted by the SMTP server.'],
        'admin.settings.smtp_test_failed' => ['Test e-postası gönderilemedi. SMTP ayarlarını ve sunucu hata kaydını kontrol edin.', 'The test email could not be sent. Check the SMTP settings and server error log.'],
    ];
    foreach ($entries as $key => $values) {
        $insert->execute(['tr', $key, $values[0]]);
        $insert->execute(['en', $key, $values[1]]);
    }
};
