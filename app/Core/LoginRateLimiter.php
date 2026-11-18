<?php
declare(strict_types=1);

namespace CoreCMS\Core;

use PDO;

final class LoginRateLimiter
{
    private const WINDOW_SECONDS = 900;
    private const MAX_IP_ATTEMPTS = 30;
    private const MAX_PAIR_ATTEMPTS = 5;

    public function __construct(private PDO $db)
    {
    }

    private function ensureTable(): void
    {
        if ((string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->db->exec("CREATE TABLE IF NOT EXISTS core_login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_hash CHAR(64) NOT NULL,
                email_hash CHAR(64) NOT NULL,
                attempted_at TEXT NOT NULL
            )");
            $this->db->exec('CREATE INDEX IF NOT EXISTS idx_core_login_attempts_ip_time ON core_login_attempts(ip_hash,attempted_at)');
            $this->db->exec('CREATE INDEX IF NOT EXISTS idx_core_login_attempts_pair_time ON core_login_attempts(ip_hash,email_hash,attempted_at)');
            return;
        }

        $this->db->exec("CREATE TABLE IF NOT EXISTS core_login_attempts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip_hash CHAR(64) NOT NULL,
            email_hash CHAR(64) NOT NULL,
            attempted_at DATETIME NOT NULL,
            KEY idx_core_login_attempts_ip_time (ip_hash,attempted_at),
            KEY idx_core_login_attempts_pair_time (ip_hash,email_hash,attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function hashes(string $remoteAddress, string $email): array
    {
        $ip = trim($remoteAddress);
        $normalizedEmail = strtolower(trim($email));
        return [
            hash('sha256', $ip !== '' ? $ip : 'unknown'),
            hash('sha256', $normalizedEmail),
        ];
    }

    public function isAllowed(string $remoteAddress, string $email): bool
    {
        $this->ensureTable();
        [$ipHash, $emailHash] = $this->hashes($remoteAddress, $email);
        $cutoff = gmdate('Y-m-d H:i:s', time() - self::WINDOW_SECONDS);
        $retention = gmdate('Y-m-d H:i:s', time() - 86400);

        $cleanup = $this->db->prepare('DELETE FROM core_login_attempts WHERE attempted_at < ?');
        $cleanup->execute([$retention]);

        $ipCount = $this->db->prepare('SELECT COUNT(*) FROM core_login_attempts WHERE ip_hash=? AND attempted_at>=?');
        $ipCount->execute([$ipHash, $cutoff]);
        if ((int)$ipCount->fetchColumn() >= self::MAX_IP_ATTEMPTS) return false;

        $pairCount = $this->db->prepare('SELECT COUNT(*) FROM core_login_attempts WHERE ip_hash=? AND email_hash=? AND attempted_at>=?');
        $pairCount->execute([$ipHash, $emailHash, $cutoff]);
        return (int)$pairCount->fetchColumn() < self::MAX_PAIR_ATTEMPTS;
    }

    public function recordFailure(string $remoteAddress, string $email): void
    {
        $this->ensureTable();
        [$ipHash, $emailHash] = $this->hashes($remoteAddress, $email);
        $stmt = $this->db->prepare('INSERT INTO core_login_attempts (ip_hash,email_hash,attempted_at) VALUES (?,?,?)');
        $stmt->execute([$ipHash, $emailHash, gmdate('Y-m-d H:i:s')]);
    }

    public function clearSuccessfulPair(string $remoteAddress, string $email): void
    {
        $this->ensureTable();
        [$ipHash, $emailHash] = $this->hashes($remoteAddress, $email);
        $stmt = $this->db->prepare('DELETE FROM core_login_attempts WHERE ip_hash=? AND email_hash=?');
        $stmt->execute([$ipHash, $emailHash]);
    }
}
