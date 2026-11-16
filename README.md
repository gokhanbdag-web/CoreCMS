# CoreCMS

A modular, shared-hosting-friendly PHP CMS foundation. CoreCMS is designed around one reusable core and independent sector modules, rather than a monolithic collection of unrelated features.

## Requirements
- PHP 8.1+ (PDO, PDO MySQL, JSON, OpenSSL)
- MySQL 5.7+ / MariaDB 10.3+
- No Composer, SSH, Node.js, or command-line installation required

## Install
1. Upload the repository contents to a PHP/MySQL hosting account.
2. Visit the site URL. The browser installer starts automatically.
3. Enter database credentials and the first administrator account.
4. Sign in at `/admin`.

The installer creates `config.php`; keep it private and never commit it.

## Architecture
- `app/Core`: database, migrations, module registry and shared services
- `database/migrations`: ordered PHP migrations used by fresh installs and upgrades
- `modules/<module-id>`: isolated sector modules and their manifests
- `themes`: frontend theme layer
- `installer`: browser-based installation flow
- `admin`: core administration interface

## Demo packages
- Sector modules can declare demo packages in `module.json` with page and menu definitions.
- Admins can preview, install, reset and remove a package from the Demo Packages section.
- Reset/removal preserve edited or revision-backed content and avoid duplicate content on reinstall.

## Module updates and rollback
- Installed modules are updated only while disabled; version changes run ordered, idempotent migrations and optional before/after hooks.
- New reversible migrations return `up` and `down` callables. Automatic rollback is offered only when every migration in the update has a `down` callable.
- Legacy callable-only migrations are treated as irreversible. Failed or partial updates are recorded for operator review; rollback is never guessed.
- Optional manifest `update_hooks` keys: `before_update`, `after_update`, `before_rollback`, `after_rollback`; each path must stay inside the module directory and return a callable.

See the Module update and rollback contract in docs/MODULE-UPDATES.md for migration and hook guidance.

See the Module runtime contract in docs/MODULE-RUNTIME.md for route, asset, template and theme conventions.

See the localization contract in docs/LOCALIZATION.md for locale management and translation lookup.

## Module uninstall safety
- Modules must be disabled and have no installed dependents before removal.
- Untouched module-provisioned pages and menus are removed; edited or historically revised content is detached and preserved.
- Module-owned database tables, customer data and migration history are retained so reinstalling does not rerun destructive schema changes.

## Administration and permissions
- Built-in administrator access remains fully compatible.
- Custom roles can be created and assigned to existing users from the admin role manager.
- Permissions are checked server-side for dashboard sections, content, menus, media, SEO, modules, demo packages, themes, settings and user management.

## Module contract
Every module has a `module.json` manifest with an ID, version, minimum core version, dependencies, permissions, routes, assets and provisioning metadata. Module-specific schema changes belong in that module's `migrations/` directory. Module-owned content must be tagged with its source module/demo package so removal tools can preserve customer-created data.

## Development status
This repository starts with the CoreCMS foundation and installation pipeline. Sector modules are being built incrementally; a manifest is not a claim that a sector module is feature-complete. See `docs/ROADMAP.md`.

## Security
Do not expose `config.php`, installer files, logs or backups publicly. Use HTTPS in production. Review the security checklist before launch.
