# Module update and rollback contract

## Update requirements

1. Bump the module manifest version.
2. Disable the module from the CoreCMS module manager.
3. Add an ordered migration under the module's migrations directory.
4. Each migration may return a legacy callable (irreversible) or an array containing up and down callables.
5. Run the update from the admin module manager. CoreCMS records the source/target versions and every applied migration.

A reversible migration returns an array with two callbacks: up(PDO $db) applies the change, and down(PDO $db) reverses it. The down callback must reverse the corresponding up operation without deleting customer data. Legacy callable migrations are treated as irreversible.

## Optional update hooks

The module manifest may declare these relative paths under update_hooks: before_update, after_update, before_rollback and after_rollback. Each hook file must return a callable. CoreCMS invokes it with (PDO $db, array $context, array $module). Hook paths are resolved inside the module directory; absolute paths and traversal paths are rejected.

## Rollback limits

- Updates and rollbacks require the module to be disabled.
- Automatic rollback is available only when every migration applied by the latest update has a down callable.
- Migrations run in order; rollback runs their down callables in reverse order and removes those migration names from the applied ledger.
- Failed or partially applied migrations are recorded. If a migration fails while executing, automatic rollback is disabled because MySQL DDL may have committed implicitly.
- Rollback restores database migrations and the stored module version. It does not restore old PHP files or assets. Deploy the matching previous code release separately.
- Always take a database backup before production updates. A down callable is module-author code, not a substitute for a verified backup.
