<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/Core/AdminErrorTranslator.php';
$t = static fn(string $key, string $fallback): string => htmlspecialchars($translate($key, $fallback), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
CoreCMS\Core\Auth::startSession();
CoreCMS\Core\Csrf::requireValidPost();
CoreCMS\Core\Auth::requireAdmin();

$manager = new CoreCMS\Core\RoleManager($db);
$role = (string)($_SESSION['corecms_user_role'] ?? '');
if (!$manager->can($role, 'users.manage')) {
    http_response_code(403);
    exit($t('admin.access.forbidden', 'You are not allowed to view this section.'));
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
        $error = CoreCMS\\Core\\AdminErrorTranslator::translate($e->getMessage(), $translate);
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
$userRoleOptions = static function (array $user) use ($roles, $role, $h, $t): string {
    $html = '';
    if (in_array($role, ['admin', 'super_admin'], true)) {
        $selected = $user['role'] === 'admin' ? ' selected' : '';
        $html .= '<option value="admin"' . $selected . '>' . $t('admin.roles.admin_full', 'Administrator (full access)') . '</option>';
    }
    foreach ($roles as $option) {
        $selected = $user['role'] === $option['role_key'] ? ' selected' : '';
        $html .= '<option value="' . $h($option['role_key']) . '"' . $selected . '>' . $h($option['name']) . '</option>';
    }
    return $html;
};
$userRowsHtml = '';
foreach ($users as $user) {
    if ($user['role'] === 'super_admin') {
        $roleCell = $t('admin.roles.protected_account', 'Protected account');
    } else {
        $roleCell = '<form method="post" class="inline-form">' . CoreCMS\Core\Csrf::field()
            . '<input type="hidden" name="action" value="assign_role">'
            . '<input type="hidden" name="user_id" value="' . (int)$user['id'] . '">'
            . '<select name="role">' . $userRoleOptions($user) . '</select>'
            . '<button class="button secondary">' . $t('admin.common.save', 'Save') . '</button></form>';
    }
    $userRowsHtml .= '<tr><td>' . $h($user['name']) . '</td><td>' . $h($user['email']) . '</td><td>'
        . $h($user['role']) . '</td><td>' . $roleCell . '</td></tr>';
}


?><!doctype html>
<html lang="<?= $h($currentLocale) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $t('admin.roles.page_title', 'Users and roles') ?> · CoreCMS</title><link rel="stylesheet" href="<?= $h(CoreCMS\Core\Url::asset('css/site.css')) ?>"><link rel="stylesheet" href="<?= $h(CoreCMS\Core\Url::asset('css/responsive-nav.css')) ?>?v=3"></head>
<body class="admin-body"><header class="site-header"><a class="brand" href="<?= $h(CoreCMS\Core\Url::to('/admin/')) ?>">CoreCMS</a><nav><a href="<?= $h(CoreCMS\Core\Url::to('/admin/')) ?>"><?= $t('admin.roles.back', 'Back to dashboard') ?></a></nav></header>
<nav class="admin-nav"><a href="<?= $h(CoreCMS\Core\Url::to('/admin/')) ?>"><?= $t('admin.nav.overview', 'Overview') ?></a><a href="<?= $h(CoreCMS\Core\Url::to('/admin/?section=pages')) ?>"><?= $t('admin.nav.pages', 'Pages') ?></a><a href="<?= $h(CoreCMS\Core\Url::to('/admin/?section=menus')) ?>"><?= $t('admin.nav.menus', 'Menus') ?></a><a href="<?= $h(CoreCMS\Core\Url::to('/admin/?section=media')) ?>"><?= $t('admin.nav.media', 'Media library') ?></a><a href="<?= $h(CoreCMS\Core\Url::to('/admin/?section=seo')) ?>"><?= $t('admin.nav.seo', 'SEO analysis') ?></a><a href="<?= $h(CoreCMS\Core\Url::to('/admin/?section=modules')) ?>"><?= $t('admin.nav.modules', 'Modules') ?></a><a href="<?= $h(CoreCMS\Core\Url::to('/admin/?section=settings')) ?>"><?= $t('admin.nav.settings', 'Site settings') ?></a><a href="<?= $h(CoreCMS\Core\Url::to('/admin/roles.php')) ?>" aria-current="page"><?= $t('admin.nav.users_roles', 'Users and roles') ?></a></nav>
<main class="container admin"><h1><?= $t('admin.roles.heading', 'Users and roles') ?></h1><p><?= $t('admin.roles.intro', 'Assign permissions to roles individually. Permissions are also enforced on the server.') ?></p>
<?php if ($error): ?><p class="error"><?= $h($error) ?></p><?php endif ?>
<?php if (isset($_GET['saved'])): ?><p class="success"><?= $t('admin.roles.saved', 'Role saved.') ?></p><?php endif ?>
<?php if (isset($_GET['deleted'])): ?><p class="success"><?= $t('admin.roles.deleted', 'Role deleted.') ?></p><?php endif ?>
<?php if (isset($_GET['assigned'])): ?><p class="success"><?= $t('admin.roles.assigned', 'User role updated.') ?></p><?php endif ?>
<section class="content-admin"><h2><?= $editing ? $t('admin.roles.edit', 'Edit role') : $t('admin.roles.create', 'Create role') ?></h2>
<form method="post" class="editor-form"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="save_role"><input type="hidden" name="role_id" value="<?= (int)($editing['id'] ?? 0) ?>">
<label><?= $t('admin.roles.key', 'Role key') ?><input name="role_key" required pattern="[a-z][a-z0-9_]{1,39}" maxlength="40" value="<?= $h($editing['role_key'] ?? '') ?>" placeholder="icerik_editoru"></label>
<label><?= $t('admin.roles.name', 'Role name') ?><input name="role_name" required maxlength="100" value="<?= $h($editing['name'] ?? '') ?>" placeholder="İçerik Editörü"></label>
<h3><?= $t('admin.roles.permissions', 'Permissions') ?></h3>
<?php foreach (CoreCMS\Core\RoleManager::permissionCatalog() as $key => $label): ?><label><input type="checkbox" name="permissions[]" value="<?= $h($key) ?>" <?= in_array($key, $selected, true) ? 'checked' : '' ?>> <?= $t('admin.permission.' . str_replace('.', '_', $key), $label) ?></label><?php endforeach ?>
<button class="button"><?= $t('admin.roles.save', 'Save role') ?></button><?php if ($editing): ?> <a href="<?= $h(CoreCMS\Core\Url::to('/admin/roles.php')) ?>"><?= $t('admin.common.cancel', 'Cancel') ?></a><?php endif ?>
</form></section>
<section class="content-admin"><h2><?= $t('admin.roles.defined', 'Defined roles') ?></h2><div class="table-wrap"><table><thead><tr><th><?= $t('admin.roles.table.role', 'Role') ?></th><th><?= $t('admin.roles.table.key', 'Key') ?></th><th><?= $t('admin.roles.table.permissions', 'Permissions') ?></th><th><?= $t('admin.common.actions', 'Actions') ?></th></tr></thead><tbody>
<?php foreach ($roles as $item): ?><tr><td><?= $h($item['name']) ?></td><td><code><?= $h($item['role_key']) ?></code></td><td><?= (int)$item['permission_count'] ?></td><td><a href="<?= $h(CoreCMS\Core\Url::to('/admin/roles.php?edit=' . (int)$item['id'])) ?>"><?= $t('admin.common.edit', 'Edit') ?></a> <?php if (!(int)$item['is_system']): ?><form method="post" class="inline-form" onsubmit="return confirm(<?= json_encode($translate('admin.roles.confirm_delete', 'Delete this role?')) ?>)"><?= CoreCMS\Core\Csrf::field() ?><input type="hidden" name="action" value="delete_role"><input type="hidden" name="role_id" value="<?= (int)$item['id'] ?>"><button class="link-button danger"><?= $t('admin.common.delete', 'Delete') ?></button></form><?php else: ?><?= $t('admin.roles.system_role', 'System role') ?><?php endif ?></td></tr><?php endforeach ?>
</tbody></table></div></section>
<section class="content-admin"><h2><?= $t('admin.roles.user_roles', 'User roles') ?></h2><p><?= $t('admin.roles.protected_intro', 'Super administrator accounts are protected and cannot be changed.') ?></p><div class="table-wrap"><table><thead><tr><th><?= $t('admin.common.name', 'Name') ?></th><th><?= $t('admin.common.email', 'Email') ?></th><th><?= $t('admin.roles.current_role', 'Current role') ?></th><th><?= $t('admin.roles.assign_role', 'Assign role') ?></th></tr></thead><tbody>
<?= $userRowsHtml ?>
</tbody></table></div></section></main><script src="<?= $h(CoreCMS\Core\Url::asset('js/responsive-nav.js')) ?>?v=3" defer></script></body></html>
