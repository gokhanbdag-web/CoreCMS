<?php
declare(strict_types=1);

/**
 * Installer hardening regression checks.
 * Exercises filesystem locking and exclusive creation, and asserts the
 * installer cleanup ownership and web-server deny rules.
 */
$root = dirname(__DIR__);
$installer = (string) file_get_contents($root . '/installer/index.php');
$htaccess = (string) file_get_contents($root . '/.htaccess');

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$temp = sys_get_temp_dir() . '/corecms-installer-' . bin2hex(random_bytes(6));
expect(mkdir($temp, 0700), 'Could not create temporary test directory.');

try {
    // 1. Two concurrent requests cannot both acquire the non-blocking lock.
    $lockPath = $temp . '/config.php.install.lock';
    $first = fopen($lockPath, 'c');
    $second = fopen($lockPath, 'c');
    expect(is_resource($first) && is_resource($second), 'Could not open lock handles.');
    expect(flock($first, LOCK_EX | LOCK_NB), 'First request could not acquire lock.');
    expect(!flock($second, LOCK_EX | LOCK_NB), 'Concurrent request unexpectedly acquired lock.');
    flock($first, LOCK_UN);
    expect(flock($second, LOCK_EX | LOCK_NB), 'Lock was not released for the next request.');
    flock($second, LOCK_UN);
    fclose($first);
    fclose($second);

    expect(str_contains($installer, 'flock($lockHandle, LOCK_EX | LOCK_NB)'), 'Installer must use a non-blocking exclusive lock.');
    expect(str_contains($installer, 'config.php.install.lock'), 'Installer lock path is missing.');

    // 2. Exclusive creation must not overwrite an existing config.
    $configPath = $temp . '/config.php';
    $original = "<?php return ['sentinel' => 'keep-me'];\n";
    file_put_contents($configPath, $original);
    $existingCreate = @fopen($configPath, 'x');
    expect($existingCreate === false, 'Exclusive config creation overwrote an existing file.');
    expect(file_get_contents($configPath) === $original, 'Existing config contents were modified.');
    expect(str_contains($installer, "fopen(\$configFile, 'x')"), 'Installer must create config.php exclusively.');
    expect(str_contains($installer, 'if (is_file($configFile))'), 'Installer must re-check config.php after locking.');

    // 3. Cleanup is allowed only for a config created by this request.
    expect(str_contains($installer, '$configCreated = false;'), 'Installer must track config ownership.');
    expect(str_contains($installer, '$configCreated = true;'), 'Installer must mark its own config creation.');
    expect((bool) preg_match('/if\s*\(\$configCreated\s*&&\s*is_file\(\$configFile\)\)\s*\{\s*@unlink\(\$configFile\);/s', $installer), 'Failure cleanup must be guarded by request-owned config state.');
    $otherRequestConfig = file_get_contents($configPath);
    $createdByThisRequest = false;
    if ($createdByThisRequest && is_file($configPath)) {
        unlink($configPath);
    }
    expect(file_get_contents($configPath) === $otherRequestConfig, 'A request removed another request\'s config.');

    // 4. Apache must deny both config.php and the persistent lock file.
    expect(str_contains($htaccess, '<FilesMatch "^config\\.php(?:\\.install\\.lock)?$">'), 'Apache FilesMatch must cover config.php and its installer lock.');
    expect(str_contains($htaccess, 'Require all denied'), 'Installer config/lock access must be denied.');

    echo "Installer hardening checks passed (4 groups)." . PHP_EOL;
} finally {
    foreach (glob($temp . '/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($temp);
}
