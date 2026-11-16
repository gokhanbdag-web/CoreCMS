# Module runtime contract

Modules are trusted, versioned PHP packages. Install only code from a trusted source. Enabling a module allows its declared route handlers to execute as application PHP.

## Manifest extensions
- `routes`: ordered list of exact or named-segment paths, allowed HTTP methods, and a handler file relative to the module directory. Handler files must return a callable receiving `PDO $db, array $params, array $request, array $module`; return a response string or null.
- `pages`: map of stable content keys to `title`, `slug`, optional `body` and `status`.
- `menus`: map of stable keys to `label`, `url`, optional `menu_key` and `sort_order`.
- `theme`: optional `name` and a module-relative `directory`. Theme paths are resolved within the module directory.

## Ownership and updates
Page and menu provisioning is recorded in `core_module_content_registry`. Existing registered content is never overwritten by later syncs, so administrator edits survive module updates. Removed manifest entries are not automatically deleted. Slug conflicts are logged and skipped rather than replacing customer pages.

## Security
- Do not install untrusted module packages: route handlers execute PHP with the application's permissions.
- Route handler paths are resolved and constrained to the module directory.
- Module internal PHP, manifests, migrations, templates and demo files must not be directly web-accessible. Only files in a module's public assets directory should be served directly.
- Validate and authorize all state-changing actions inside module handlers; use the shared CSRF and authentication services.
