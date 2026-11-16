<?php
declare(strict_types=1);

return static function (PDO $db): void {
    $defaults = [
        'site_url' => '',
        'seo_org_name' => '',
        'seo_social_image' => '',
        'search_console_verification' => '',
    ];
    $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql = $driver === 'sqlite'
        ? 'INSERT INTO core_settings(setting_key,setting_value) VALUES(?,?) ON CONFLICT(setting_key) DO NOTHING'
        : 'INSERT IGNORE INTO core_settings(setting_key,setting_value) VALUES(?,?)';
    $stmt = $db->prepare($sql);
    foreach ($defaults as $key => $value) $stmt->execute([$key, $value]);
};
