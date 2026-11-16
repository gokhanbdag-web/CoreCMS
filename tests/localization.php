<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/LocaleManager.php';
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$migration = require dirname(__DIR__) . '/database/migrations/0011_localization.php';
$migration($db);
$adminTranslations = require dirname(__DIR__) . '/database/migrations/0012_admin_ui_translations.php';
$adminTranslations($db);
$adminContentTranslations = require dirname(__DIR__) . '/database/migrations/0013_admin_content_seo_translations.php';
$adminContentTranslations($db);
$locales = new CoreCMS\Core\LocaleManager($db);
$assert = static function(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$assert($locales->defaultCode() === 'tr', 'Turkish must be seeded as default');
$assert($locales->isEnabled('tr'), 'default locale must be enabled');
$assert($locales->isEnabled('en'), 'English must be enabled by the admin translation seed');
$assert($locales->translate('admin.nav.overview', 'en') === 'Overview', 'admin navigation English translation');
$assert($locales->translate('admin.roles.heading', 'tr') === 'Kullanıcılar ve roller', 'admin roles Turkish translation');
$assert($locales->translate('admin.pages.edit', 'en') === 'Edit page', 'page editor English translation');
$assert($locales->translate('admin.seo.audit_title', 'en') === 'Page SEO audit', 'SEO audit English translation');
$locales->saveLanguage('en', 'English');
$locales->saveLanguage('de', 'Deutsch');
$locales->saveTranslation('en', 'admin.dashboard.title', 'Dashboard');
$locales->saveTranslation('en', 'admin.dashboard.title', 'Overview');
$assert($locales->translate('admin.dashboard.title', 'en') === 'Overview', 'translation upsert');
$assert($locales->translate('missing.key', 'en', 'Fallback') === 'Fallback', 'fallback text');
$locales->setDefault('en');
$assert($locales->defaultCode() === 'en', 'default language switch');
$locales->saveLanguage('de', 'Deutsch', false);
$failed = false; try { $locales->setDefault('de'); } catch (RuntimeException) { $failed = true; }
$assert($failed, 'disabled language cannot become default');
$failed = false; try { $locales->saveTranslation('de', 'x.y', 'Nicht aktiv'); } catch (RuntimeException) { $failed = true; }
$assert($failed, 'disabled language cannot receive translations');
$failed = false; try { $locales->saveTranslation('en', '../bad', 'No'); } catch (RuntimeException) { $failed = true; }
$assert($failed, 'unsafe translation key rejected');
$rows = $locales->translations('en', 'admin.dashboard');
$assert(count($rows) === 1 && $rows[0]['translation_key'] === 'admin.dashboard.title', 'translation search');
echo "Localization checks passed (14).\n";
