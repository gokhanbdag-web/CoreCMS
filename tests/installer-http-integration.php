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
$logFile = tempnam(sys_get_temp_dir(), 'corecms-installer-log-');
$process = null;
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

$request = static function (string $url, ?array $post = null) use ($cookieFile): array {
    $curl = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
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
    if (is_file($configFile)) throw new RuntimeException('config.php already exists; refusing to overwrite an installation.');

    $server = $connectServer();
    $server->exec('DROP DATABASE IF EXISTS `' . $database . '`');
    $server->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

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
    $assert($postStatus === 200 && str_contains($resultHtml, 'Kurulum tamamlandı'), 'browser installer completes successfully');
    $assert(is_file($configFile), 'installer writes config.php');

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

    echo 'Browser installer HTTP integration checks passed (' . $checks . ').' . PHP_EOL;
} finally {
    if (is_resource($process)) {
        proc_terminate($process);
        proc_close($process);
    }
    if (is_file($configFile)) @unlink($configFile);
    if (is_file($lockFile)) @unlink($lockFile);
    if (is_file($cookieFile)) @unlink($cookieFile);
    if (is_file($logFile)) @unlink($logFile);
    try {
        $server = $connectServer();
        $server->exec('DROP DATABASE IF EXISTS `' . $database . '`');
    } catch (Throwable $e) {
        error_log('[CoreCMS] Installer integration cleanup failed: ' . $e->getMessage());
    }
}
