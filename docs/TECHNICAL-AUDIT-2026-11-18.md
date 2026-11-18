# CoreCMS Technical Audit — 2026-11-18

## Scope and method

This repository-level audit reviewed the installer, bootstrap, authentication and authorization flow, migration runner, CI workflow, module runtime, media handling, public forms, email queue, sector module workflows, and regression tests.

The audit now has automated coverage for PHP 8.1–8.5, MySQL 8.0, and MariaDB 10.11. The browser installer is exercised over HTTP with a real database, and core migrations are tested on fresh and upgrade paths. Static review and automated tests are not a substitute for a production penetration test or load test.

## Resolved findings

| ID | Priority | Finding | Resolution |
|---|---|---|---|
| R-01 | P1 | PHP 8.4 was missing from the compatibility matrix. | Added PHP 8.4. PR #59 merged; all PHP matrix jobs passed. |
| R-02 | P1 | Active admin sessions retained stale roles and account status after account changes. | Added database-backed session revalidation to admin dashboard, role manager, autosave and preview endpoints. PR #60 merged; PHP 8.1–8.5 passed. |
| R-03 | P1 | Admin login had no brute-force throttling. | Added persistent limits of 5 failures per IP/email pair and 30 per IP per 15-minute window, hashed identifiers, and successful-login reset. PR #62 merged; PHP 8.1–8.5 passed. |
| R-04 | P1 | CI did not exercise actual MySQL/MariaDB migrations or upgrade paths. | Added MySQL 8.0 and MariaDB 10.11 jobs, fresh core migrations, module installation, repeat-run checks, and upgrade from migration 0020. PR #63 merged. |
| R-05 | P1 | The actual browser installer form was not tested against MySQL or nested paths. | Added HTTP-level installer tests at the domain root and in a nested subdirectory, covering session cookie, CSRF, database connection, config placement, admin creation, nested asset URLs, application secret, and repeat-visit redirects. PRs #66 and #68 merged; both database engines passed. |
| R-06 | P2 | Form rate limiting reused the database password as its HMAC secret. | Added a separate random application secret in core settings, preserving existing non-empty secrets. PR #64 merged; PHP 8.1–8.5 passed. |
| R-07 | P2 | Public form rate-limit count and insert were not atomic and used PHP-local cutoffs against database timestamps. | Added per-form transaction locking and database-time windows for MySQL and SQLite. PR #65 merged; PHP 8.1–8.5 and MySQL/MariaDB jobs passed. |
| R-08 | P1 | Interrupted installer migrations were not tested through the real HTTP flow. | Added a fault-injection migration that leaves committed DDL but no migration-ledger entry on its first run, then verifies safe retry, config cleanup and exactly-once recording. PR #70 merged; MySQL 8.0 and MariaDB 10.11 passed. |

## Remaining findings

### A-01 — Admin controller is a large monolithic entry point

- **Priority:** P2
- **Evidence:** `admin/index.php` is approximately 115 KB and contains authentication, action dispatch, module workflows, settings, content, forms, email, and rendering logic.
- **Risk:** Changes to one feature have a large regression surface; authorization and validation rules are harder to review consistently.
- **Recommended action:** Extract action handlers and view rendering incrementally by domain. Preserve existing routes, request names, UI, and permission checks. Do not perform a wholesale rewrite.
- **Validation required:** Existing regression suite plus request/permission tests for every extracted action.

### A-02 — No browser-based database backup and restore workflow

- **Priority:** P1
- **Evidence:** No application backup/restore service or admin action was found. The deployment documentation describes a timestamped archive of the live code directory, but excludes `config.php`, `storage/` and `uploads/`; it does not create a database dump.
- **Risk:** A code deployment archive is not a complete site backup. Database content, credentials/configuration and uploaded customer assets cannot be restored together from that archive after data loss or a failed upgrade.
- **Recommended action:** Implement a browser-only, administrator-authorized backup and restore workflow suitable for shared hosting. Include database data/schema plus configuration and user uploads, store temporary files outside the public web root, validate integrity before restore, and provide a pre-restore backup. Do not require SSH, shell utilities or Composer.
- **Validation required:** MySQL/MariaDB backup-and-restore round trip, corrupted archive rejection, large-file limits, interrupted restore safety, permission/CSRF checks and subdirectory operation.

### A-02 — Historical migration files have duplicate numeric prefixes

- **Priority:** P2 (maintenance)
- **Evidence:** Both `database/migrations/0002_module_content_registry.php` and `database/migrations/0002_role_permissions.php` use the `0002` prefix.
- **Risk:** The current migrator sorts and records complete filenames, so this is not a demonstrated runtime collision. It makes the historical ordering less obvious.
- **Resolution policy:** Never rename or edit already-applied migrations because the migration ledger stores their filenames. New migrations must use the next unique numeric prefix. This is now documented in `docs/MIGRATIONS.md`; migration `0037_login_rate_limits.php` follows the unique sequence.
- **Validation required:** Continue checking deterministic filename order and unique numeric prefixes for every new migration.

## Additional observations

- The installer uses a non-blocking exclusive lock and exclusive creation of `config.php`; its filesystem regression tests cover those guarantees.
- The module runtime validates module paths and constrains route handlers, templates and assets to their module directories.
- Tourism capacity reservation uses a conditional atomic update; salon appointment creation serializes booking attempts through a staff-row update within a transaction.
- The media library validates MIME type and image structure, generates random filenames, and blocks script execution in its upload directory on Apache.
- The public form rate limiter now serializes concurrent requests per form and uses database time, avoiding PHP/database timezone mismatches.
- The browser installer is tested at the domain root and in a nested subdirectory; an injected DDL failure is retried through the real installer form on MySQL and MariaDB.
- These observations are code-review notes, not claims of a complete security certification.

## Next development order

1. Implement browser-based full backup/restore for database, configuration and uploads, with integrity verification and safe rollback.
2. Incrementally split `admin/index.php` into domain controllers and views, beginning with a low-risk, well-tested area.
3. Perform a focused authorization review across every admin action and module permission boundary.
4. Add production-like performance and security tests before release.

Each change should use its own branch, preserve existing behavior, run the full PHP and database test matrices, and merge only after all required checks pass.
