<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Url.php';
require_once dirname(__DIR__) . '/app/Core/Csrf.php';
require_once dirname(__DIR__) . '/app/Core/Auth.php';
CoreCMS\Core\Auth::startSession();

if (is_file(dirname(__DIR__) . '/config.php')) {
    header('Location: ' . CoreCMS\Core\Url::to('/admin/'));
    exit;
}

$error = '';
$complete = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CoreCMS\Core\Csrf::requireValidPost();
    $host = trim($_POST['db_host'] ?? 'localhost');
    $port = (int)($_POST['db_port'] ?? 3306);
    $name = trim($_POST['db_name'] ?? '');
    $user = trim($_POST['db_user'] ?? '');
    $pass = (string)($_POST['db_pass'] ?? '');
    $adminName = trim($_POST['admin_name'] ?? '');
    $email = trim($_POST['admin_email'] ?? '');
    $password = (string)($_POST['admin_password'] ?? '');
    if (!$name || !$user || !$adminName || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
        $error = 'Tüm alanları doldurun. Yönetici parolası en az 12 karakter olmalıdır.';
    } else {
        $configFile = dirname(__DIR__) . '/config.php';
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
            $db = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            $config = ['database' => ['host'=>$host,'port'=>$port,'name'=>$name,'user'=>$user,'password'=>$pass]];
            $payload = "<?php\ndeclare(strict_types=1);\nreturn " . var_export($config, true) . ";\n";
            $handle = @fopen($configFile, 'x');
            if ($handle === false) throw new RuntimeException('config.php oluşturulamadı. Dizin izinlerini ve kurulum durumunu kontrol edin.');
            try {
                if (!flock($handle, LOCK_EX) || fwrite($handle, $payload) === false || !fflush($handle)) {
                    throw new RuntimeException('Yapılandırma dosyası güvenli biçimde yazılamadı.');
                }
            } finally {
                fclose($handle);
            }
            @chmod($configFile, 0640);

            require_once dirname(__DIR__) . '/app/Core/Migrator.php';
            $migrator = new CoreCMS\Core\Migrator($db, dirname(__DIR__) . '/database/migrations');
            $migrator->migrate();
            $stmt = $db->prepare("INSERT INTO core_users (name,email,password_hash,role) VALUES (?,?,?,'admin')");
            $stmt->execute([$adminName, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $complete = true;
        } catch (Throwable $e) {
            if (isset($configFile) && is_file($configFile)) @unlink($configFile);
            $error = 'Kurulum tamamlanamadı: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CoreCMS Kurulum</title><link rel="stylesheet" href="<?= htmlspecialchars(CoreCMS\Core\Url::asset('css/site.css'),ENT_QUOTES,'UTF-8') ?>"></head><body><main class="install-wrap"><section class="install-card"><span class="eyebrow">CORECMS INSTALLER</span><h1>Kuruluma başlayalım.</h1>
<?php if ($complete): ?><h2>Kurulum tamamlandı</h2><p>Veritabanı ve yönetici hesabı oluşturuldu.</p><a class="button" href="<?= htmlspecialchars(CoreCMS\Core\Url::to('/admin/'),ENT_QUOTES,'UTF-8') ?>">Yönetim paneline giriş</a>
<?php else: ?><?php if ($error): ?><p class="error"><?= $error ?></p><?php endif; ?><form method="post"><?= CoreCMS\Core\Csrf::field() ?><h2>Veritabanı</h2><label>Sunucu<input name="db_host" value="localhost" required></label><label>Port<input name="db_port" type="number" value="3306" required></label><label>Veritabanı adı<input name="db_name" required></label><label>Veritabanı kullanıcı adı<input name="db_user" required></label><label>Veritabanı parolası<input name="db_pass" type="password" autocomplete="new-password"></label><h2>Yönetici hesabı</h2><label>Ad soyad<input name="admin_name" required></label><label>E-posta<input name="admin_email" type="email" required></label><label>Parola (en az 12 karakter)<input name="admin_password" type="password" minlength="12" required autocomplete="new-password"></label><button class="button" type="submit">CoreCMS'yi kur</button></form><?php endif; ?></section></main></body></html>
