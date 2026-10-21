<?php

declare(strict_types=1);

/**
 * Data access for the existing `admin_users` table (Specification 02 schema,
 * extended by migration 001). Prepared statements only. Emails are expected
 * already normalized by the caller (the column is case-insensitive and UNIQUE:
 * the final guard against duplicates).
 */
final class AdminUserRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** Full row including the password hash (used only for credential verification). */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, password_hash, role, status, two_factor_enabled
             FROM admin_users WHERE email = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** Everything credential and second-factor checks need (includes hash and encrypted TOTP secret). */
    public function findFullById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, password_hash, role, status, two_factor_enabled,
                    two_factor_secret_encrypted, two_factor_confirmed_at, two_factor_last_step
             FROM admin_users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Session re-validation: no password hash, fresh status/role/name from the database.
     * password_changed_ts is the UTC epoch of the last password change (null if never).
     */
    public function findSummaryById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, role, status, two_factor_enabled, two_factor_confirmed_at,
                    UNIX_TIMESTAMP(password_changed_at) AS password_changed_ts
             FROM admin_users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** Replaces an outdated hash of the SAME password with the current algorithm/cost. Not a password change. */
    public function upgradePasswordHash(int $id, string $newPasswordHash): void
    {
        $stmt = $this->pdo->prepare('UPDATE admin_users SET password_hash = :hash WHERE id = :id');
        $stmt->execute(['id' => $id, 'hash' => $newPasswordHash]);
    }

    /** Records a successful login (UTC); optionally upgrades the stored hash. */
    public function recordLogin(int $id, ?string $newPasswordHash = null): void
    {
        if ($newPasswordHash === null) {
            $stmt = $this->pdo->prepare('UPDATE admin_users SET last_login_at = UTC_TIMESTAMP() WHERE id = :id');
            $stmt->execute(['id' => $id]);
            return;
        }

        // A transparent hash upgrade is not a password change: sessions stay valid.
        $stmt = $this->pdo->prepare(
            'UPDATE admin_users SET last_login_at = UTC_TIMESTAMP(), password_hash = :hash WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'hash' => $newPasswordHash]);
    }

    /**
     * Sets a new password and marks the change time, which ends every session that
     * was authenticated before it. $changedAtUtc comes from the application clock
     * (same clock as the session timestamps), format "Y-m-d H:i:s".
     */
    public function setPassword(int $id, string $passwordHash, string $changedAtUtc): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE admin_users SET password_hash = :hash, password_changed_at = :changed WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'hash' => $passwordHash, 'changed' => $changedAtUtc]);
    }

    /**
     * Accepts a TOTP time-step only if it is newer than the last accepted one
     * (a code can be used once). Atomic: returns false when another request got there first.
     */
    public function acceptTotpStep(int $id, int $step): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE admin_users SET two_factor_last_step = :step
             WHERE id = :id AND (two_factor_last_step IS NULL OR two_factor_last_step < :step_check)'
        );
        $stmt->bindValue('step', $step, PDO::PARAM_INT);
        $stmt->bindValue('step_check', $step, PDO::PARAM_INT);
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() === 1;
    }

    /** Turns 2FA on (called only after a valid code confirmed the enrollment). */
    public function enableTwoFactor(int $id, string $encryptedSecret, int $step): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE admin_users
             SET two_factor_enabled = 1, two_factor_secret_encrypted = :secret,
                 two_factor_confirmed_at = UTC_TIMESTAMP(), two_factor_last_step = :step
             WHERE id = :id'
        );
        $stmt->bindValue('secret', $encryptedSecret);
        $stmt->bindValue('step', $step, PDO::PARAM_INT);
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function disableTwoFactor(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE admin_users
             SET two_factor_enabled = 0, two_factor_secret_encrypted = NULL,
                 two_factor_confirmed_at = NULL, two_factor_last_step = NULL
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public function create(string $name, string $email, string $passwordHash, string $role): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO admin_users (name, email, password_hash, role, status)
             VALUES (:name, :email, :hash, :role, :status)'
        );
        $stmt->execute(['name' => $name, 'email' => $email, 'hash' => $passwordHash, 'role' => $role, 'status' => 'active']);

        return (int) $this->pdo->lastInsertId();
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    }
}
