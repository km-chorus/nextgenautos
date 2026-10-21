<?php

declare(strict_types=1);

/**
 * Data access for `admin_recovery_codes`. Only keyed hashes of codes are stored.
 * Callers wrap replaceAll() in a transaction together with the change that needs it.
 */
final class RecoveryCodeRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** Replaces ALL of an admin's codes (old ones stop working immediately). */
    public function replaceAll(int $adminId, array $codeHashes): void
    {
        $this->deleteAll($adminId);

        $stmt = $this->pdo->prepare('INSERT INTO admin_recovery_codes (admin_user_id, code_hash) VALUES (:admin, :hash)');
        foreach ($codeHashes as $hash) {
            $stmt->execute(['admin' => $adminId, 'hash' => $hash]);
        }
    }

    public function deleteAll(int $adminId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM admin_recovery_codes WHERE admin_user_id = :admin');
        $stmt->execute(['admin' => $adminId]);
    }

    /** Marks a code used. Atomic: true for exactly one caller, false if unknown or already used. */
    public function consume(int $adminId, string $codeHash): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE admin_recovery_codes SET used_at = UTC_TIMESTAMP()
             WHERE admin_user_id = :admin AND code_hash = :hash AND used_at IS NULL'
        );
        $stmt->execute(['admin' => $adminId, 'hash' => $codeHash]);

        return $stmt->rowCount() === 1;
    }

    public function countUnused(int $adminId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM admin_recovery_codes WHERE admin_user_id = :admin AND used_at IS NULL');
        $stmt->execute(['admin' => $adminId]);

        return (int) $stmt->fetchColumn();
    }
}
