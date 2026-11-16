# Sector modules

Each sector module is an independent product layer. It may own its tables, admin screens, routes, permissions, templates, translations and demo packages, but must use shared core services for identity, media, settings, pages, menus, SEO and security.

Required module structure (being implemented):
- `module.json` — manifest and compatibility contract
- `migrations/` — ordered, versioned module schema migrations
- `Admin/` — module-specific administration
- `Frontend/` — route handlers and view models
- `views/` — sector templates
- `assets/` — module styles, scripts and images
- `demo/` — independently installable demo packages

Do not put sector-specific business logic in CoreCMS core. Do not delete customer-owned records when disabling or uninstalling a module.
