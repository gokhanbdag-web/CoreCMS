# CoreCMS Roadmap

## Foundation (current milestone)
- [x] Native PHP + PDO; no Composer
- [x] Browser-first installer
- [x] Shared-hosting deployment assumptions
- [x] Versioned core migration runner
- [x] Manifest-based module discovery and dependency safeguards
- [x] Secure sessions, CSRF and relocatable routing
- [x] Installer hardening and install lock
- [ ] Full live database/browser installation verification

## Core CMS
- [x] Roles and granular permissions
- [x] Dynamic pages and navigation editor
- [x] Media library and safe uploads
- [x] SEO metadata and sitemap
- [x] Locale registry, default-language selection and translation editor foundation
- [x] Extract built-in admin/frontend system UI strings into translation keys
- [x] Per-language page/menu content variants (page body/slug/SEO and menu label/URL)
- [x] Site settings UI
- [ ] Forms and email templates
- [x] Theme and template registry

## Module platform
- [x] Module installation registry and version tracking
- [x] Per-module ordered migrations with idempotent tracking
- [x] Dependency-aware install, activation and deactivation
- [x] Preserve module tables and customer data on deactivation
- [x] Explicit non-destructive uninstall contract
- [x] Page/menu provisioning with ownership tracking
- [x] Demo package preview/install/reset/remove
- [x] Module update hooks and rollback strategy
- [x] Module-owned routes, assets, themes and templates

## Sector modules
- [x] Real estate: listings, agents, listing search/detail, lead forms
- [x] Tourism: tours, destinations, availability, booking enquiries
- [x] Hair salon: services, staff, appointment requests
- [ ] Each sector module owns its schema, admin screens, routes and frontend templates

## Release gates
A milestone is complete only after PHP syntax checks, clean-database installer tests, upgrade/migration tests, module lifecycle tests and responsive UI review. A manifest alone does not mean a sector module is feature-complete.
