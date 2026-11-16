<?php
declare(strict_types=1);

namespace CoreCMS\Core;

use PDO;
use RuntimeException;
use Throwable;

final class Migrator
{
    public function __construct(private PDO $db, private string $directory) {}

    public function ensureRepository(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS core_migrations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(190) NOT NULL UNIQUE,
            batch INT UNSIGNED NOT NULL,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function pending(): array
    {
        $this->ensureRepository();
        $done = $this->db->query('SELECT migration FROM core_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $files = glob(rtrim($this->directory, '/') . '/*.php') ?: [];
        sort($files, SORT_STRING);
        return array_values(array_filter($files, static fn($file) => !in_array(basename($file), $done, true)));
    }

    public function migrate(): array
    {
        $this->ensureRepository();
        $pending = $this->pending();
        if (!$pending) return [];
        $batch = (int)$this->db->query('SELECT COALESCE(MAX(batch),0)+1 FROM core_migrations')->fetchColumn();
        $applied = [];
        foreach ($pending as $file) {
            $migration = require $file;
            if (!is_callable($migration)) throw new RuntimeException('Migration must return a callable: ' . basename($file));
            $this->db->beginTransaction();
            try {
                $migration($this->db);
                $stmt = $this->db->prepare('INSERT INTO core_migrations (migration,batch) VALUES (?,?)');
                $stmt->execute([basename($file), $batch]);
                // MySQL/MariaDB implicitly commit around DDL statements. A migration
                // containing CREATE/ALTER TABLE may therefore end the transaction
                // before its migration record is inserted. Commit only when PDO still
                // reports an active transaction; otherwise the record is autocommitted.
                if ($this->db->inTransaction()) $this->db->commit();
                $applied[] = basename($file);
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) $this->db->rollBack();
                throw new RuntimeException('Migration failed: ' . basename($file) . ' — ' . $e->getMessage(), 0, $e);
            }
        }
        return $applied;
    }
}
