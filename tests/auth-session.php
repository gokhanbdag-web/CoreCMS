<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/Url.php';
require dirname(__DIR__) . '/app/Core/RoleManager.php';
require dirname(__DIR__) . '/app/Core/Auth.php';

use CoreCMS\Core\Auth;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "Authentication session tests skipped: SQLite PDO driver unavailable." . PHP_EOL;
    exit(0);
}

$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE core_users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, role TEXT NOT NULL, status TEXT NOT NULL)');
$db->exec('CREATE TABLE core_roles (id INTEGER PRIMARY KEY, role_key TEXT NOT NULL UNIQUE, name TEXT NOT NULL, is_system INTEGER NOT NULL DEFAULT 0)');
$db->exec('CREATE TABLE core_role_permissions (role_id INTEGER NOT NULL, permission_key TEXT NOT NULL, PRIMARY KEY(role_id,permission_key))');
$db->exec("INSERT INTO core_users (id,name,role,status) VALUES (1,'Admin','admin','active')");
$db->exec("INSERT INTO core_roles (id,role_key,name) VALUES (1,'content_editor','Content editor')");

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
};

session_id('corecms-auth-session-test');
session_start();
$_SESSION['corecms_user_id'] = 1;
$_SESSION['corecms_user_name'] = 'Admin';
$_SESSION['corecms_user_role'] = 'admin';
$_SESSION['_corecms_csrf'] = str_repeat('a', 64);

$assert(Auth::refreshSession($db), 'active account session remains valid');
$assert($_SESSION['corecms_user_role'] === 'admin', 'unchanged role remains in session');

$db->exec("UPDATE core_users SET role='content_editor', name='Editor' WHERE id=1");
$assert(Auth::refreshSession($db), 'role change keeps the account authenticated');
$assert($_SESSION['corecms_user_role'] === 'content_editor', 'role change is reflected immediately');
$assert($_SESSION['corecms_user_name'] === 'Editor', 'account name change is reflected immediately');
$assert(!isset($_SESSION['_corecms_csrf']), 'CSRF token is rotated when privileges change');

$db->exec("UPDATE core_users SET status='inactive' WHERE id=1");
$assert(!Auth::refreshSession($db), 'inactive account is rejected');
$assert(!Auth::check(), 'inactive account session is cleared');

session_id('corecms-auth-session-deleted-test');
session_start();
$_SESSION['corecms_user_id'] = 1;
$_SESSION['corecms_user_name'] = 'Editor';
$_SESSION['corecms_user_role'] = 'content_editor';
$db->exec('DELETE FROM core_users WHERE id=1');
$assert(!Auth::refreshSession($db), 'deleted account is rejected');
$assert(!Auth::check(), 'deleted account session is cleared');

echo 'Authentication session checks passed (' . $checks . ').' . PHP_EOL;
