<?php

declare(strict_types=1);

/**
 * Data access for `password_reset_tokens`. Only SHA-256 hashes of tokens are stored.
 * Timestamps passed in are UTC strings ("Y-m-d H:i:s") from the application clock.
 */
final class PasswordResetRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(int $adminId, string $tokenHash, string $expiresAtUtc): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO password_reset_tokens (admin_user_id, token_hash, expires_at) VALUES (:admin, :hash, :expires)'
        );
        $stmt->execute(['admin' => $adminId, 'hash' => $tokenHash, 'expires' => $expiresAtUtc]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Ends every unused link of this administrator (a new request replaces older links). */
    public function invalidateOutstanding(int $adminId, string $nowUtc): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE password_reset_tokens SET used_at = :now WHERE admin_user_id = :admin AND used_at IS NULL'
        );
        $stmt->execute(['now' => $nowUtc, 'admin' => $adminId]);
    }

    /** @return array{id:int,admin_user_id:int}|null a token that exists, is unused and not expired */
    public function findValidByHash(string $tokenHash, string $nowUtc): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, admin_user_id FROM password_reset_tokens
             WHERE token_hash = :hash AND used_at IS NULL AND expires_at > :now LIMIT 1'
        );
        $stmt->execute(['hash' => $tokenHash, 'now' => $nowUtc]);
        $row = $stmt->fetch();

        return $row === false ? null : ['id' => (int) $row['id'], 'admin_user_id' => (int) $row['admin_user_id']];
    }

    /** @return array{id:int,admin_user_id:int}|null */
    public function findValidById(int $id, string $nowUtc): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, admin_user_id FROM password_reset_tokens
             WHERE id = :id AND used_at IS NULL AND expires_at > :now LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'now' => $nowUtc]);
        $row = $stmt->fetch();

        return $row === false ? null : ['id' => (int) $row['id'], 'admin_user_id' => (int) $row['admin_user_id']];
    }

    /** Single use: true for exactly one caller. */
    public function markUsed(int $id, string $nowUtc): bool
    {
        $stmt = $this->pdo->prepare('UPDATE password_reset_tokens SET used_at = :now WHERE id = :id AND used_at IS NULL');
        $stmt->execute(['now' => $nowUtc, 'id' => $id]);

        return $stmt->rowCount() === 1;
    }
}
