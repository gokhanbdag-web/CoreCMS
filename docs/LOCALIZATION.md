# Localization and translation editor

CoreCMS stores enabled locales in `core_languages` and keyed text translations in `core_translations`. The browser-based administration editor manages locale activation, the default locale, keyed interface translations, and per-language page/menu content.

## Locale rules
- Locale codes use a lowercase language code with an optional uppercase region, such as `tr`, `en`, or `en-US`.
- Turkish (`tr`) is seeded as the initial enabled default.
- The default locale must always be enabled.
- Translation keys are stable identifiers (for example, `admin.dashboard.title`), not translated text.
- Missing keys return the supplied fallback, or the key itself when no fallback is supplied.

## PHP usage

```php
$locales = new CoreCMS\Core\LocaleManager($db);
echo htmlspecialchars($locales->translate('admin.dashboard.title', 'en', 'Dashboard'), ENT_QUOTES, 'UTF-8');
```

## Per-language page and menu content
- Base-language content remains in `core_pages` and `core_menus`; it is not rewritten when another language is translated.
- Page translations are stored in `core_page_translations` by page and locale. They include title, URL slug, body, SEO title/description, canonical URL and social image.
- Menu translations are stored in `core_menu_translations` by menu item and locale. They include the visible label and destination URL.
- The page and menu editors expose a content-language selector. Save operations for a translated locale update only that locale's translation row.
- The public router resolves either the base slug or the current locale's translated slug. The language switcher uses the matching slug for each locale.
- If a translation is missing, the public site falls back to the base-language page/menu. Existing base-language content remains unchanged.
- Translation writes require the target locale to be enabled. The default locale must be edited through the base content fields, not a duplicate translation row.
- Until the new content migrations are applied, public page and menu lookup safely falls back to the existing base content.

## Scope note
Built-in admin and public system-interface strings are seeded in Turkish and English. This does not automatically translate customer-authored page/menu content, module-provided copy, sector-module content, forms, or email templates. Add those through their respective locale-aware content workflows.
