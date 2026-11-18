# Database Migration Guidelines

Core migrations live in `database/migrations/` and are applied in lexicographic filename order.

## Naming

- Use the format `NNNN_short_description.php`.
- Choose the next unused numeric prefix across the directory. Prefixes must be unique, even when filenames differ.
- Do not rename, delete, or rewrite a migration that may already have been applied. The migration ledger records the complete filename.
- Correct an already-applied migration by adding a new migration with the next unused prefix.

## Migration contract

Each migration must return a callable that accepts the active PDO connection:

```php
<?php
declare(strict_types=1);

return static function (PDO $db): void {
    // Apply one forward-only schema/data change.
};
```

## Compatibility and safety

- Support the production MySQL/MariaDB driver. Keep SQLite-compatible branches where the migration is exercised by SQLite regression tests.
- Use deterministic, bounded operations and preserve existing data.
- Assume MySQL DDL may implicitly commit; do not rely on transaction rollback to undo schema changes.
- Prefer idempotent DDL where practical. If a migration can partially apply before failing, make the retry path safe.
- Do not perform destructive table or column changes without a data-preserving upgrade path.
- Add assertions to the MySQL 8.0 and MariaDB 10.11 integration job for important schema and upgrade behavior.
