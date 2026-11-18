<?php
declare(strict_types=1);

if (!function_exists('curl_init')) {
    throw new RuntimeException('PHP cURL extension is required for the installer HTTP integration test.');
}

$host = (string)(getenv('CORECMS_DB_HOST') ?: '127.0.0.1');
$port = (int)(getenv('CORECMS_DB_PORT') ?: 3306);
$user = (string)(getenv('CORECMS_DB_USER') ?: 'root');
$password = (string)(getenv('CORECMS_DB_PASSWORD') ?: 'root');
$database = (string)(getenv('CORECMS_INSTALL_TEST_DB') ?: 'corecms_installer_http_test');
if (!preg_match('/^[a-zA-Z0-9_]+$/', $database)) {
    throw new RuntimeException('Installer integration database name is invalid.');
}

$root = dirname(__DIR__);
$configFile = $root . '/config.php';
$lockFile = $configFile . '.install.lock';
$baseUrl = 'http://127.0.0.1:8081';
$cookieFile = tempnam(sys_get_temp_dir(), 'corecms-cookie-');
$subdirCookieFile = tempnam(sys_get_temp_dir(), 'corecms-subdir-cookie-');
$logFile = tempnam(sys_get_temp_dir(), 'corecms-installer-log-');
$process = null;
$createdDatabase = false;
$createdConfig = false;
$createdLock = false;
$subdirRoot = $root . '/subdir_test';
$subdirConfigFile = $subdirRoot . '/config.php';
$subdirLockFile = $subdirConfigFile . '.install.lock';
$subdirDatabase = $database . '_subdir';
$createdSubdirRoot = false;
$createdSubdirDatabase = false;
$createdSubdirConfig = false;
$createdSubdirLock = false;
$checks = 0;

$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
};

$connectServer = static function () use ($host, $port, $user, $password): PDO {
    return new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port), $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
};

$request = static function (string $url, ?array $post = null, ?string $cookieJar = null) use ($cookieFile): array {
    $cookieJar = $cookieJar ?? $cookieFile;
    $curl = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 20,
    ];
    if ($post !== null) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($post);
        $options[CURLOPT_HTTPHEADER] = ['Content-Type: application/x-www-form-urlencoded'];
    }
    curl_setopt_array($curl, $options);
    $raw = curl_exec($curl);
    if ($raw === false) {
        $error = curl_error($curl);
        curl_close($curl);
        throw new RuntimeException('Installer HTTP request failed: ' . $error);
    }
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $headerSize = (int)curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $headers = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    curl_close($curl);
    return [$status, $headers, $body];
};

try {
    if (is_file($configFile) || is_file($lockFile)) throw new RuntimeException('An existing installation or installer lock was found; refusing to modify it.');

    $server = $connectServer();
    $server->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $createdDatabase = true;

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['file', $logFile, 'a'],
        2 => ['file', $logFile, 'a'],
    ];
    $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:8081', '-t', $root], $descriptors, $pipes, $root);
    if (!is_resource($process)) throw new RuntimeException('Could not start the PHP test web server.');
    if (isset($pipes[0]) && is_resource($pipes[0])) fclose($pipes[0]);

    $ready = false;
    for ($attempt = 0; $attempt < 30; $attempt++) {
        try {
            [$status] = $request($baseUrl . '/installer/');
            if ($status === 200) { $ready = true; break; }
        } catch (Throwable) {
            usleep(250000);
        }
    }
    $assert($ready, 'installer responds over HTTP');

    [$getStatus, , $installerHtml] = $request($baseUrl . '/installer/');
    $assert($getStatus === 200, 'installer page loads');
    if (!preg_match('/name="_csrf"\s+value="([a-f0-9]{64})"/', $installerHtml, $matches)) {
        throw new RuntimeException('Installer CSRF token was not rendered.');
    }

    [$postStatus, , $resultHtml] = $request($baseUrl . '/installer/', [
        '_csrf' => $matches[1],
        'db_host' => $host,
        'db_port' => (string)$port,
        'db_name' => $database,
        'db_user' => $user,
        'db_pass' => $password,
        'admin_name' => 'CoreCMS Integration Admin',
        'admin_email' => 'installer-test@example.test',
        'admin_password' => 'Integration-Test-Password-2026!',
    ]);
    $createdConfig = is_file($configFile);
    $createdLock = is_file($lockFile);
    $assert($postStatus === 200 && str_contains($resultHtml, 'Kurulum tamamlandı'), 'browser installer completes successfully');
    $assert($createdConfig, 'installer writes config.php');

    [$adminStatus, , $adminHtml] = $request($baseUrl . '/admin/');
    $assert($adminStatus === 200 && str_contains($adminHtml, 'name="email"'), 'installed admin login page is reachable');

    $installedDb = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database),
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $admin = $installedDb->prepare("SELECT COUNT(*) FROM core_users WHERE email=? AND status='active'");
    $admin->execute(['installer-test@example.test']);
    $assert((int)$admin->fetchColumn() === 1, 'installer creates the active administrator account');

    $secret = $installedDb->query("SELECT setting_value FROM core_settings WHERE setting_key='app_secret'")->fetchColumn();
    $assert(is_string($secret) && strlen($secret) === 64, 'first admin request initializes a separate application secret');

    [$secondInstallerStatus, $headers] = $request($baseUrl . '/installer/');
    $assert($secondInstallerStatus === 302 && stripos($headers, 'Location: /admin/') !== false, 'revisiting installer redirects to admin after installation');

    // Repeat the complete flow from a nested installation path.
    if (is_file($subdirConfigFile) || is_file($subdirLockFile) || is_dir($subdirRoot)) {
        throw new RuntimeException('Subdirectory installer test path already exists; refusing to modify it.');
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $subdirDatabase)) {
        throw new RuntimeException('Subdirectory installer database name is invalid.');
    }
    mkdir($subdirRoot, 0700, true);
    $createdSubdirRoot = true;
    foreach (['admin','app','assets','database','installer','modules'] as $directory) {
        $source = $root . '/' . $directory;
        if (!is_dir($source)) throw new RuntimeException('Required application directory is missing: ' . $directory);
        $target = $subdirRoot . '/' . $directory;
        mkdir($target, 0700, true);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isLink()) continue;
            $relative = substr($item->getPathname(), strlen($source) + 1);
            $destination = $target . '/' . $relative;
            if ($item->isDir()) {
                if (!is_dir($destination)) mkdir($destination, 0700, true);
            } elseif ($item->isFile()) {
                if (!copy($item->getPathname(), $destination)) throw new RuntimeException('Could not copy subdirectory test file: ' . $relative);
            }
        }
    }
    foreach (['index.php','.htaccess'] as $file) {
        if (!copy($root . '/' . $file, $subdirRoot . '/' . $file)) throw new RuntimeException('Could not copy subdirectory entry file: ' . $file);
    }

    $server->exec('CREATE DATABASE `' . $subdirDatabase . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $createdSubdirDatabase = true;
    $subdirUrl = $baseUrl . '/subdir_test/installer/';
    [$subdirGetStatus, , $subdirHtml] = $request($subdirUrl, null, $subdirCookieFile);
    $assert($subdirGetStatus === 200, 'installer page loads from a nested path');
    $assert(str_contains($subdirHtml, '/subdir_test/assets/css/site.css'), 'installer asset URLs retain the nested base path');
    if (!preg_match('/name="_csrf"\s+value="([a-f0-9]{64})"/', $subdirHtml, $subdirMatches)) {
        throw new RuntimeException('Subdirectory installer CSRF token was not rendered.');
    }

    [$subdirPostStatus, , $subdirResult] = $request($subdirUrl, [
        '_csrf' => $subdirMatches[1],
        'db_host' => $host,
        'db_port' => (string)$port,
        'db_name' => $subdirDatabase,
        'db_user' => $user,
        'db_pass' => $password,
        'admin_name' => 'CoreCMS Subdirectory Admin',
        'admin_email' => 'subdir-installer-test@example.test',
        'admin_password' => 'Subdirectory-Test-Password-2026!',
    ], $subdirCookieFile);
    $createdSubdirConfig = is_file($subdirConfigFile);
    $createdSubdirLock = is_file($subdirLockFile);
    $assert($subdirPostStatus === 200 && str_contains($subdirResult, 'Kurulum tamamlandı'), 'browser installer completes from a nested path');
    $assert($createdSubdirConfig, 'nested installer writes config.php inside its own directory');

    [$subdirAdminStatus, , $subdirAdminHtml] = $request($baseUrl . '/subdir_test/admin/', null, $subdirCookieFile);
    $assert($subdirAdminStatus === 200 && str_contains($subdirAdminHtml, 'name="email"'), 'nested admin login page is reachable');

    $subdirInstalledDb = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $subdirDatabase),
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $subdirAdmin = $subdirInstalledDb->prepare("SELECT COUNT(*) FROM core_users WHERE email=? AND status='active'");
    $subdirAdmin->execute(['subdir-installer-test@example.test']);
    $assert((int)$subdirAdmin->fetchColumn() === 1, 'nested installer creates the active administrator account');

    [$subdirSecondStatus, $subdirHeaders] = $request($subdirUrl, null, $subdirCookieFile);
    $assert($subdirSecondStatus === 302 && stripos($subdirHeaders, 'Location: /subdir_test/admin/') !== false, 'nested installer redirects to the nested admin path after installation');

    echo 'Browser installer HTTP integration checks passed (' . $checks . ').' . PHP_EOL;
} finally {
    if (is_resource($process)) {
        proc_terminate($process);
        proc_close($process);
    }
    if ($createdConfig && is_file($configFile)) @unlink($configFile);
    if ($createdLock && is_file($lockFile)) @unlink($lockFile);
    if ($createdSubdirConfig && is_file($subdirConfigFile)) @unlink($subdirConfigFile);
    if ($createdSubdirLock && is_file($subdirLockFile)) @unlink($subdirLockFile);
    if (is_file($cookieFile)) @unlink($cookieFile);
    if (is_file($subdirCookieFile)) @unlink($subdirCookieFile);
    if (is_file($logFile)) @unlink($logFile);
    try {
        $server = $connectServer();
        if ($createdDatabase) $server->exec('DROP DATABASE IF EXISTS `' . $database . '`');
        if ($createdSubdirDatabase) $server->exec('DROP DATABASE IF EXISTS `' . $subdirDatabase . '`');
    } catch (Throwable $e) {
        error_log('[CoreCMS] Installer integration cleanup failed: ' . $e->getMessage());
    }
    if ($createdSubdirRoot && is_dir($subdirRoot)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($subdirRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        @rmdir($subdirRoot);
    }
}
