<?php
declare(strict_types=1);

return static function (PDO $db): void {
    if ((string)$db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $db->exec("CREATE TABLE IF NOT EXISTS core_demo_installs (
            module_id TEXT NOT NULL,
            demo_id TEXT NOT NULL,
            installed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (module_id,demo_id)
        )");
        return;
    }
    $db->exec("CREATE TABLE IF NOT EXISTS core_demo_installs (
        module_id VARCHAR(100) NOT NULL,
        demo_id VARCHAR(40) NOT NULL,
        installed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (module_id,demo_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};
