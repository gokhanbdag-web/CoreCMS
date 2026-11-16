<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/Core/RoleManager.php';

use CoreCMS\\Core\\RoleManager;
use PDO;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "Role manager tests skipped: SQLite PDO driver unavailable.".PHP_EOL;
    exit(0);
}
$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE core_roles(id INTEGER PRIMARY KEY AUTOINCREMENT,role_key TEXT UNIQUE,name TEXT,is_system INTEGER DEFAULT 0)");
$db->exec("CREATE TABLE core_role_permissions(role_id INTEGER,permission_key TEXT,PRIMARY KEY(role_id,permission_key))");
$db->exec("CREATE TABLE core_users(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT,email TEXT,role TEXT)");
$manager=new RoleManager($db); $checks=0;
$assert=static function(bool $ok,string $message) use (&$checks):void { $checks++; if(!$ok) throw new RuntimeException('FAIL: '.$message); };
$manager->save(null,'content_editor','İçerik Editörü',['dashboard.view','pages.view','pages.manage','invalid.permission']);
$assert($manager->can('content_editor','pages.manage'),'granted page permission');
$assert(!$manager->can('content_editor','settings.manage'),'ungranted settings permission');
$assert(!$manager->can('content_editor','unknown.permission'),'unknown permission denied');
$assert($manager->can('admin','settings.manage'),'legacy admin retains full access');
$assert($manager->can('super_admin','users.manage'),'super admin bypass');
$assert($manager->roleExists('content_editor'),'custom role exists');
$db->exec("INSERT INTO core_users(name,email,role) VALUES ('Editor','editor@example.test','content_editor')");
$roleId=(int)$db->query("SELECT id FROM core_roles WHERE role_key='content_editor'")->fetchColumn();
$blocked=false; try{$manager->delete($roleId);}catch(RuntimeException $e){$blocked=true;}
$assert($blocked,'cannot delete role assigned to a user');
$manager->assignUser(1,'content_editor');
$assert($db->query('SELECT role FROM core_users WHERE id=1')->fetchColumn()==='content_editor','assign role to user');
$manager->delete($roleId);
$assert(!$manager->roleExists('content_editor'),'unused custom role deleted');
echo 'Role manager checks passed ('.$checks.').'.PHP_EOL;
