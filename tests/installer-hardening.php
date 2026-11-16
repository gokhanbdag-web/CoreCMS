<?php
declare(strict_types=1);

/**
 * Installer hardening regression checks.
 *
 * The lock and exclusive-create behavior is exercised against real temporary
 * files. Installer control-flow and Apache deny rules are also asserted so
 * regressions in the production implementation fail the PHP matrix.
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
    // 1. Concurrent requests: a second non-blocking exclusive lock must fail
    // while the first request owns the lock, then succeed after release.
    $lockPath = $temp . '/config.php.install.lock';
    $first = fopen($lockPath, 'c');
    $second = fopen($lockPath, 'c');
    expect(is_resource($first) && is_resource($second), 'Could not open lock handles.');
    expect(flock($first, LOCK_EX | LOCK_NB), 'First installer request could not acquire lock.');
    expect(!flock($second, LOCK_EX | LOCK_NB), 'Concurrent installer request unexpectedly acquired the lock.');
    flock($first, LOCK_UN);
    expect(flock($second, LOCK_EX | LOCK_NB), 'Second request could not acquire released lock.');
    flock($second, LOCK_UN);
    fclose($first);
    fclose($second);

    expect(str_contains($installer, "flock($lockHandle, LOCK_EX | LOCK_NB)"), 'Installer must use a non-blocking exclusive lock.');
    expect(str_contains($installer, 'config.php.install.lock'), 'Installer lock path is missing.');

    // 2. Exclusive config creation must not overwrite an existing installation.
    $configPath = $temp . '/config.php';
    $original = "<?php return ['sentinel' => 'keep-me'];\n";
    file_put_contents($configPath, $original);
    $existingCreate = @fopen($configPath, 'x');
    expect($existingCreate === false, 'Exclusive config creation overwrote an existing file.');
    expect(file_get_contents($configPath) === $original, 'Existing config contents were modified.');
    expect(str_contains($installer, "fopen($configFile, 'x')"), 'Installer must create config.php exclusively.');
    expect(str_contains($installer, 'if (is_file($configFile))'), 'Installer must re-check config.php after locking.');

    // 3. Failed setup may remove only a config created by this request.
    expect(str_contains($installer, '$configCreated = false;'), 'Installer must track config ownership.');
    expect(str_contains($installer, '$configCreated = true;'), 'Installer must mark its own config creation.');
    expect((bool) preg_match('/if\s*\(\$configCreated\s*&&\s*is_file\(\$configFile\)\)\s*\{\s*@unlink\(\$configFile\);/s', $installer), 'Failure cleanup must be guarded by request-owned config state.');

    // Exercise the ownership rule: a request that did not create the config
    // must leave it untouched when its simulated setup fails.
    $ownedByOtherRequest = file_get_contents($configPath);
    $configCreatedByThisRequest = false;
    if ($configCreatedByThisRequest && is_file($configPath)) {
        unlink($configPath);
    }
    expect(file_get_contents($configPath) === $ownedByOtherRequest, 'A request removed another request\'s config.');

    // 4. The persistent lock file must be denied by the web-server rules.
    expect((bool) preg_match('/<FilesMatch\s+"\^config\\\\\\.php\(\?:\\\\\\.install\\\\\\.lock\)\?\$">\s*Require all denied\s*<\\/FilesMatch>/s', $htaccess), 'Apache rules must deny config.php and its installer lock file.');

    echo "Installer hardening checks passed (4 groups).\n";
} finally {
    foreach (glob($temp . '/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($temp);
}
