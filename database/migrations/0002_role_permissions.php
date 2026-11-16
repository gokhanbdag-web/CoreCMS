<?php
declare(strict_types=1);

return static function (PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS core_roles (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        role_key VARCHAR(40) NOT NULL UNIQUE,
        name VARCHAR(100) NOT NULL,
        is_system TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("CREATE TABLE IF NOT EXISTS core_role_permissions (
        role_id BIGINT UNSIGNED NOT NULL,
        permission_key VARCHAR(80) NOT NULL,
        PRIMARY KEY (role_id,permission_key),
        CONSTRAINT fk_core_role_permissions_role FOREIGN KEY (role_id) REFERENCES core_roles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $permissions = [
        'dashboard.view','pages.view','pages.manage','menus.manage','media.manage',
        'seo.manage','modules.manage','appearance.manage','settings.manage','users.manage'
    ];
    $insertRole = $db->prepare('INSERT IGNORE INTO core_roles (role_key,name,is_system) VALUES (?,?,0)');
    $insertRole->execute(['editor','Editör']);
    $roleId = $db->prepare('SELECT id FROM core_roles WHERE role_key=?');
    $roleId->execute(['editor']);
    $editorId = (int)$roleId->fetchColumn();
    $insertPermission = $db->prepare('INSERT IGNORE INTO core_role_permissions (role_id,permission_key) VALUES (?,?)');
    foreach (['dashboard.view','pages.view','pages.manage','menus.manage','media.manage'] as $permission) {
        $insertPermission->execute([$editorId,$permission]);
    }
};
