# CoreCMS Technical Audit — 2026-11-18

## Scope and method

This is a repository-level static audit of the current `main` branch. It reviewed the installer, bootstrap, authentication and authorization flow, migration runner, CI workflow, module runtime, media handling, public forms, email queue, sector module workflows, and existing regression tests.

The latest CI run on the audited baseline completed successfully across PHP 8.1, 8.2, 8.3, 8.4 and 8.5. Static review is not a substitute for a production-like MySQL installation/upgrade test or a penetration test.

## Resolved during this audit

| ID | Priority | Finding | Resolution |
|---|---|---|---|
| R-01 | P1 | PHP 8.4 was declared compatible by the PHP 8.1+ requirement but was missing from the CI matrix. | Added PHP 8.4 to CI. PR #59 merged; the full matrix passed. |
| R-02 | P1 | Active admin sessions trusted the role and account status captured at login. A role downgrade or account deactivation did not invalidate the old session. | Added database-backed session revalidation to dashboard, role manager, autosave and preview endpoints; added regression tests. PR #60 merged; PHP 8.1–8.5 passed. |

## Open findings

### A-01 — Admin login has no brute-force throttling

- **Priority:** P1
- **Evidence:** `app/Core/Auth.php::attempt()` performs a user lookup and password verification but does not record or limit failed attempts. The login handler in `admin/index.php` returns the same error for failed credentials, but there is no request/IP/email throttle.
- **Risk:** Repeated password guesses can be attempted against the administrator login endpoint.
- **Recommended action:** Add a database-backed limiter keyed by a one-way hash of the remote IP and normalized email, with a short rolling window, bounded retention, and generic responses. Ensure the limiter works before and after pending migrations.
- **Validation required:** Parallel-attempt tests, expiry tests, successful-login reset tests, and MySQL integration tests.

### A-02 — CI does not exercise a real MySQL installation or upgrade

- **Priority:** P1
- **Evidence:** `.github/workflows/ci.yml` enables `pdo_mysql` but does not start a MySQL/MariaDB service. The installer hardening test checks locking, exclusive file creation and Apache deny rules; it does not execute the browser installer against MySQL. Sector-module tests construct in-memory SQLite databases.
- **Risk:** MySQL-specific DDL, implicit commits, foreign keys, collation behavior, SQL dialect differences, and fresh-install/upgrade recovery can regress while the current CI remains green.
- **Recommended action:** Add a MySQL service job that runs the ordered core migrations from an empty database, installs/enables each sector module, verifies the resulting schema and key CRUD workflows, then exercises an upgrade from a representative prior migration state. Keep SQLite tests for fast unit/regression coverage.
- **Validation required:** MySQL 8.x and a supported MariaDB version; fresh install, interrupted migration retry, upgrade, and repeat-run idempotency.

### A-03 — Public form rate-limit check and insert are not atomic

- **Priority:** P2
- **Evidence:** `app/Core/FormSubmissionService.php::submit()` counts recent attempts and inserts a new rate-limit row in separate statements without a transaction or database-side atomic limiter.
- **Risk:** Concurrent submissions from the same address can all observe a count below the threshold and exceed the intended limit.
- **Recommended action:** Use an atomic database-side rate-limit operation or a transaction/locking strategy supported by both MySQL and SQLite. Preserve per-form isolation and retention cleanup.
- **Validation required:** Concurrent submission test that proves the configured maximum cannot be exceeded.

### A-04 — Public form rate-limit HMAC key reuses the database password

- **Priority:** P2
- **Evidence:** `index.php` constructs `FormSubmissionService` using `$config['database']['password']` as its HMAC secret.
- **Risk:** The rate-limit hashing key is coupled to a credential with a different purpose. Rotating the database password changes all IP hashes and resets the effective rate-limit history; an empty database password causes form submissions to be rejected by the service's non-empty-secret check.
- **Recommended action:** Generate a separate random application secret during installation, store it in protected application configuration, and use it exclusively for rate-limit hashing. Add a backward-compatible upgrade path for existing installations.
- **Validation required:** Existing config compatibility, empty DB password handling, secret rotation behavior, and form submission regression tests.

### A-05 — Admin controller is a large monolithic entry point

- **Priority:** P2
- **Evidence:** `admin/index.php` is approximately 115 KB and contains authentication, action dispatch, module workflows, settings, content, forms, email, and rendering logic.
- **Risk:** Changes to one feature have a large regression surface; authorization and validation rules are harder to review consistently.
- **Recommended action:** Extract action handlers and view rendering incrementally by domain. Preserve existing routes, request names, UI, and permission checks. Do not perform a wholesale rewrite.
- **Validation required:** Existing regression suite plus request/permission tests for every extracted action.

### A-06 — Migration filename sequence contains duplicate numeric prefixes

- **Priority:** P2
- **Evidence:** Both `database/migrations/0002_module_content_registry.php` and `database/migrations/0002_role_permissions.php` use the `0002` prefix.
- **Risk:** The current migrator sorts and records complete filenames, so this is not a demonstrated runtime collision. However, duplicate sequence numbers make ordering less obvious and increase the chance of future migration-order mistakes.
- **Recommended action:** Do not rename already-applied migrations, because the migration ledger stores their filenames. Adopt a unique next migration number for all new migrations and document the filename convention.
- **Validation required:** Migration ordering test that verifies deterministic ordering and unique filenames.

## Additional observations

- The installer already uses a non-blocking exclusive lock and exclusive creation of `config.php`; its regression test covers those filesystem guarantees.
- The module runtime validates module paths and constrains route handlers, templates and assets to their module directories.
- Tourism capacity reservation uses a conditional atomic update; salon appointment creation serializes booking attempts through a staff-row update within a transaction.
- The media library validates MIME type and image structure, generates random filenames, and blocks script execution in its upload directory on Apache.
- These observations are code-review notes, not claims of a complete security certification.

## Recommended next sequence

1. A-01: admin login brute-force throttling.
2. A-02: MySQL/MariaDB integration coverage for fresh install and upgrades.
3. A-04: dedicated application secret for form rate limiting.
4. A-03: make public form rate limiting concurrency-safe.
5. A-06: enforce unique migration numbering for future migrations.
6. A-05: incremental extraction of the admin controller after behavior is covered by tests.

Each item should be implemented on its own branch, tested on the supported PHP matrix, reviewed for regressions, and merged only after the required checks pass.
