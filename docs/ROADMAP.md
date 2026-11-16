# CoreCMS Roadmap

## Foundation (current milestone)
- [x] Native PHP + PDO; no Composer
- [x] Browser-first installer
- [x] Shared-hosting deployment assumptions
- [x] Versioned core migration runner
- [x] Manifest-based module discovery and activation
- [x] Initial sector module manifests
- [ ] Installer hardening and install lock
- [ ] CSRF protection and admin authorization middleware
- [ ] Automated smoke/integration test coverage

## Core CMS
- [ ] Roles and granular permissions
- [ ] Dynamic pages and navigation editor
- [ ] Media library and safe uploads
- [ ] SEO metadata and sitemap
- [ ] Localization and translation editor
- [ ] Forms, email templates and settings UI
- [ ] Theme and template registry

## Module platform
- [ ] Dependency/version compatibility resolver
- [ ] Module migration runner and rollback strategy
- [ ] Page/menu provisioning with ownership tracking
- [ ] Demo package preview/install/reset/remove
- [ ] Customer-content preservation guarantees
- [ ] Module update and deactivation lifecycle hooks

## Sector modules
- [ ] Real estate: listings, agents, listing search/detail, lead forms
- [ ] Tourism: tours, destinations, availability, booking enquiries
- [ ] Hair salon: services, staff, appointment requests
- [ ] Each sector module owns its schema, admin screens, routes and frontend templates

## Release gates
A milestone is complete only after PHP syntax checks, installer tests on a clean database, upgrade/migration tests, module lifecycle tests and responsive UI review. A manifest alone does not mean a module is ready for production.
