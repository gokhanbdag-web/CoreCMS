# Sector modules

Each sector module is an independent product layer. It may own its tables, admin screens, routes, permissions, templates, translations and demo packages, but must use shared core services for identity, media, settings, pages, menus, SEO and security.

Required module structure:
- `module.json` — manifest with ID, name, semantic version and optional dependency IDs
- `migrations/` — ordered PHP files returning a callable that receives the shared PDO connection
- `Admin/` — module-specific administration
- `Frontend/` — route handlers and view models
- `views/` — sector templates
- `assets/` — module styles, scripts and images
- `demo/` — independently installable demo packages

## Installation contract
1. A module ID must match its containing directory and pass manifest validation.
2. Dependencies must already be installed before installation; they must be enabled before the dependent module can be enabled.
3. Migration filenames are sorted lexically and recorded per module in `core_module_migrations`. A recorded migration is never run a second time.
4. Installation and update run pending migrations before recording the installed module version. A failed migration is not marked as applied.
5. Disabling a module never deletes its tables or customer data. A module cannot be disabled while another enabled module depends on it.
6. There is intentionally no automatic destructive uninstall. A future uninstall contract must require explicit module-owned cleanup rules and prove that customer-created records are preserved.

Migrations must be additive and safe to retry where practical. MySQL DDL can implicitly commit, so module authors must not assume that a PDO transaction can roll back every schema operation. Back up the database before production module upgrades and keep migrations forward-only unless a separately reviewed rollback contract is supplied.

Do not put sector-specific business logic in CoreCMS core. Do not delete customer-owned records when disabling or uninstalling a module.
