<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/Url.php';
require dirname(__DIR__) . '/app/Core/RoleManager.php';
require dirname(__DIR__) . '/app/Core/Auth.php';

use CoreCMS\Core\Auth;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "Admin login rate limit tests skipped: SQLite PDO driver unavailable." . PHP_EOL;
    exit(0);
}

$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE core_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, role TEXT NOT NULL, status TEXT NOT NULL)');
$db->exec('CREATE TABLE core_roles (id INTEGER PRIMARY KEY AUTOINCREMENT, role_key TEXT NOT NULL UNIQUE, name TEXT NOT NULL, is_system INTEGER NOT NULL DEFAULT 0)');
$db->exec('CREATE TABLE core_role_permissions (role_id INTEGER NOT NULL, permission_key TEXT NOT NULL, PRIMARY KEY(role_id,permission_key))');
$insert = $db->prepare('INSERT INTO core_users(name,email,password_hash,role,status) VALUES(?,?,?,?,?)');
$insert->execute(['Administrator','admin@example.test',password_hash('CorrectHorseBatteryStaple!',PASSWORD_DEFAULT),'admin','active']);

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
};

session_id('corecms-login-rate-test-1');
session_start();
$_SERVER['REMOTE_ADDR'] = '192.0.2.10';
for ($i = 0; $i < 5; $i++) {
    $assert(!Auth::attempt($db, 'admin@example.test', 'wrong-password'), 'invalid password is rejected');
}
$assert(!Auth::attempt($db, 'admin@example.test', 'CorrectHorseBatteryStaple!'), 'sixth attempt for the same IP/email pair is throttled');

$_SERVER['REMOTE_ADDR'] = '192.0.2.11';
for ($i = 0; $i < 4; $i++) {
    $assert(!Auth::attempt($db, 'admin@example.test', 'wrong-password'), 'attempt below pair limit remains available');
}
$assert(Auth::attempt($db, 'admin@example.test', 'CorrectHorseBatteryStaple!'), 'valid credentials work below the pair threshold');
Auth::logout();

session_id('corecms-login-rate-test-2');
session_start();
$_SERVER['REMOTE_ADDR'] = '192.0.2.11';
for ($i = 0; $i < 4; $i++) {
    $assert(!Auth::attempt($db, 'admin@example.test', 'wrong-password'), 'successful login cleared the previous pair history');
}
$assert(Auth::attempt($db, 'admin@example.test', 'CorrectHorseBatteryStaple!'), 'successful login resets the pair limiter');
Auth::logout();

session_id('corecms-login-rate-test-3');
session_start();
$_SERVER['REMOTE_ADDR'] = '192.0.2.12';
for ($i = 0; $i < 30; $i++) {
    $assert(!Auth::attempt($db, 'guess-' . $i . '@example.test', 'wrong-password'), 'failed attempts from distinct emails are recorded per IP');
}
$assert(!Auth::attempt($db, 'admin@example.test', 'CorrectHorseBatteryStaple!'), 'IP-wide threshold blocks attempts across different email addresses');

echo 'Admin login rate limit checks passed (' . $checks . ').' . PHP_EOL;
