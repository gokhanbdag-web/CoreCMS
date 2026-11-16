<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
CoreCMS\Core\Auth::startSession();
CoreCMS\Core\Csrf::requireValidPost();
CoreCMS\Core\Auth::requireAdmin();

$manager = new CoreCMS\Core\RoleManager($db);
$role = (string)($_SESSION['corecms_user_role'] ?? '');
if (!$manager->can($role, 'users.manage')) {
    http_response_code(403);
    exit('Bu bölümü görüntülemek için yetkiniz bulunmuyor.');
}
$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save_role') {
            $manager->save(
                (int)($_POST['role_id'] ?? 0) ?: null,
                (string)($_POST['role_key'] ?? ''),
                (string)($_POST['role_name'] ?? ''),
                (array)($_POST['permissions'] ?? [])
            );
            header('Location: ' . CoreCMS\Core\Url::to('/admin/roles.php?saved=1'));
            exit;
        }
        if ($action === 'delete_role') {
            $manager->delete((int)($_POST['role_id'] ?? 0));
            header('Location: ' . CoreCMS\Core\Url::to('/admin/roles.php?deleted=1'));
            exit;
        }
        if ($action === 'assign_role') {
            $manager->assignUser((int)($_POST['user_id'] ?? 0), (string)($_POST['role'] ?? ''), in_array($role, ['admin','super_admin'], true));
            header('Location: ' . CoreCMS\Core\Url::to('/admin/roles.php?assigned=1'));
            exit;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
$roles = $manager->roles();
$users = $db->query('SELECT id,name,email,role,status FROM core_users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare('SELECT * FROM core_roles WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
$selected = $editing ? $manager->permissionsForRole((string)$editing['role_key']) : [];
$h = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?><!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Kullanıcılar ve Roller · CoreCMS</title><link rel="stylesheet" href="<?= $h(CoreCMS\Core\Url::asset('css/site.css')) ?>"></head>
<body class="admin-body"><header class="site-header"><a class="brand" href="<?= $h(CoreCMS\Core\Url::to('/admin/')) ?>">CoreCMS</a><nav><a href="<?= $h(CoreCMS\Core\Url::to('/admin/')) ?>">Yönetim paneline dön</a></nav></header>
<main class="container admin"><h1>Kullanıcılar ve roller</h1><p>Roller için izinleri ayrı ayrı belirleyin. Yetkiler sunucu tarafında da doğrulanır.</p>
<?php if ($error): ?><p class="error"><?= $h($error) ?></p><?php endif ?>
<?php if (isset($_GET['saved'])): ?><p class="success">Rol kaydedildi.</p><?php endif ?>
<?php if (isset($_GET['deleted'])): ?><p class="success">Rol silindi.</p><?php endif ?>
<?php if (isset($_GET['assigned'])): ?><p class="success">Kullanıcı rolü güncellendi.</p><?php endif ?>
<section class="content-admin"><h2><?= $editing ? 'Rolü düzenle' : 'Yeni rol oluştur' ?></h2>
<form method="post" class="editor-form"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="save_role"><input type="hidden" name="role_id" value="<?= (int)($editing['id'] ?? 0) ?>">
<label>Rol anahtarı<input name="role_key" required pattern="[a-z][a-z0-9_]{1,39}" maxlength="40" value="<?= $h($editing['role_key'] ?? '') ?>" placeholder="icerik_editoru"></label>
<label>Rol adı<input name="role_name" required maxlength="100" value="<?= $h($editing['name'] ?? '') ?>" placeholder="İçerik Editörü"></label>
<h3>İzinler</h3>
<?php foreach (CoreCMS\Core\RoleManager::permissionCatalog() as $key => $label): ?><label><input type="checkbox" name="permissions[]" value="<?= $h($key) ?>" <?= in_array($key, $selected, true) ? 'checked' : '' ?>> <?= $h($label) ?></label><?php endforeach ?>
<button class="button">Rolü kaydet</button><?php if ($editing): ?> <a href="<?= $h(CoreCMS\Core\Url::to('/admin/roles.php')) ?>">Vazgeç</a><?php endif ?>
</form></section>
<section class="content-admin"><h2>Tanımlı roller</h2><div class="table-wrap"><table><thead><tr><th>Rol</th><th>Anahtar</th><th>İzin</th><th>İşlem</th></tr></thead><tbody>
<?php foreach ($roles as $item): ?><tr><td><?= $h($item['name']) ?></td><td><code><?= $h($item['role_key']) ?></code></td><td><?= (int)$item['permission_count'] ?></td><td><a href="<?= $h(CoreCMS\Core\Url::to('/admin/roles.php?edit=' . (int)$item['id'])) ?>">Düzenle</a> <?php if (!(int)$item['is_system']): ?><form method="post" class="inline-form" onsubmit="return confirm('Rol silinsin mi?')"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="delete_role"><input type="hidden" name="role_id" value="<?= (int)$item['id'] ?>"><button class="link-button danger">Sil</button></form><?php else: ?>Sistem rolü<?php endif ?></td></tr><?php endforeach ?>
</tbody></table></div></section>
<section class="content-admin"><h2>Kullanıcı rolleri</h2><p>Süper yönetici hesapları koruma altındadır ve değiştirilemez.</p><div class="table-wrap"><table><thead><tr><th>Ad</th><th>E-posta</th><th>Mevcut rol</th><th>Rol ata</th></tr></thead><tbody>
<?php foreach ($users as $user): ?><tr><td><?= $h($user['name']) ?></td><td><?= $h($user['email']) ?></td><td><?= $h($user['role']) ?></td><td><?php if ($user['role'] === 'super_admin'): ?>Korunan hesap<?php else: ?><form method="post" class="inline-form"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="assign_role"><input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>"><select name="role"><?php if (in_array($role, ['admin','super_admin'], true)): ?><option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Yönetici (tam erişim)</option><?php endif ?><?php foreach ($roles as $option): ?><option value="<?= $h($option['role_key']) ?>" <?= $user['role'] === $option['role_key'] ? 'selected' : '' ?>><?= $h($option['name']) ?></option><?php endforeach ?></select><button class="button secondary">Kaydet</button></form><?php endif ?></td></tr><?php endforeach ?>
</tbody></table></div></section></main></body></html>
