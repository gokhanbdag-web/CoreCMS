<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'index.php', 'app/bootstrap.php', 'app/Core/Database.php',
    'app/Core/Migrator.php', 'app/Core/ModuleManager.php', 'app/Core/ModuleRuntime.php', 'app/Core/ThemeManager.php', 'database/migrations/0010_module_update_history.php', 'tests/module-update.php', 'tests/module-runtime.php', 'tests/theme-manager.php', 'docs/MODULE-RUNTIME.md', '.htaccess',
    'app/Core/Url.php', 'app/Core/Csrf.php', 'app/Core/Auth.php',
    'installer/index.php', 'admin/index.php', 'app/Core/DemoPackageManager.php', 'database/migrations/0009_demo_package_installs.php', 'tests/demo-packages.php', 'tests/module-uninstall.php', 'database/migrations/0008_module_uninstall_contract.php', 'admin/roles.php', 'app/Core/RoleManager.php', 'database/migrations/0002_role_permissions.php', 'tests/role-manager.php',
    'database/migrations/0001_core_schema.php', 'tests/core-foundation.php', 'app/Core/LocaleManager.php', 'database/migrations/0011_localization.php', 'tests/localization.php', 'docs/LOCALIZATION.md', 'app/Core/FormRenderer.php', 'app/Core/FormSubmissionService.php', 'database/migrations/0025_form_submission_rate_limits.php', 'tests/form-public.php', 'database/migrations/0026_public_form_frontend_translations.php'
];
$failed = [];
foreach ($required as $file) {
    if (!is_file($root . '/' . $file)) $failed[] = 'Missing: ' . $file;
}
foreach (glob($root . '/modules/*/module.json') ?: [] as $manifest) {
    $data = json_decode((string)file_get_contents($manifest), true);
    if (!is_array($data) || empty($data['id']) || empty($data['version'])) $failed[] = 'Invalid manifest: ' . $manifest;
}
if ($failed) {
    fwrite(STDERR, implode(PHP_EOL, $failed) . PHP_EOL);
    exit(1);
}
echo "CoreCMS smoke checks passed." . PHP_EOL;
