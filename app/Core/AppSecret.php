<?php
declare(strict_types=1);

namespace CoreCMS\Core;

use PDO;
use RuntimeException;

final class AppSecret
{
    public static function ensure(PDO $db): string
    {
        $select = $db->prepare("SELECT setting_value FROM core_settings WHERE setting_key='app_secret' LIMIT 1");
        $select->execute();
        $existing = $select->fetchColumn();
        if (is_string($existing) && $existing !== '') return $existing;

        $secret = bin2hex(random_bytes(32));
        $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $db->exec("INSERT OR IGNORE INTO core_settings (setting_key,setting_value) VALUES ('app_secret','')");
        } else {
            $db->exec("INSERT IGNORE INTO core_settings (setting_key,setting_value) VALUES ('app_secret','')");
        }
        $update = $db->prepare("UPDATE core_settings SET setting_value=? WHERE setting_key='app_secret' AND (setting_value IS NULL OR setting_value='')");
        $update->execute([$secret]);

        $select->execute();
        $value = $select->fetchColumn();
        if (!is_string($value) || $value === '') {
            throw new RuntimeException('CoreCMS application secret could not be initialized.');
        }
        return $value;
    }
}
