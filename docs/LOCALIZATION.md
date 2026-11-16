# Localization and translation editor

CoreCMS stores enabled locales in `core_languages` and keyed text translations in `core_translations`. The browser-based administration editor manages locale activation, the default locale, and translation values without changing existing page content.

## Locale rules
- Locale codes use a lowercase language code with an optional uppercase region, such as `tr`, `en`, or `en-US`.
- Turkish (`tr`) is seeded as the initial enabled default.
- The default locale must always be enabled.
- Translation keys are stable identifiers (for example, `admin.dashboard.title`), not translated text.
- Missing keys return the supplied fallback, or the key itself when no fallback is supplied.

## PHP usage

```php
$locales = new CoreCMS\\Core\\LocaleManager($db);
echo htmlspecialchars($locales->translate('admin.dashboard.title', 'en', 'Dashboard'), ENT_QUOTES, 'UTF-8');
```

## Scope note
The editor and runtime lookup API provide the localization foundation. Existing hard-coded interface strings and stored page/menu content are not automatically translated; UI extraction and per-language content variants are separate follow-up work. Existing Turkish content remains unchanged.
