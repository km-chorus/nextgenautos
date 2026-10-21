<?php

declare(strict_types=1);

/**
 * Data access for the `customers` table (spec 13_spec_customer_auth.md). PDO prepared statements only.
 * Emails are stored lowercase (the service normalizes them); the UNIQUE key is the final guard against duplicates.
 */
final class CustomerRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string,mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM customers WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM customers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public function findByGoogleId(string $googleId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM customers WHERE google_id = :gid LIMIT 1');
        $stmt->execute(['gid' => $googleId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** Attaches a Google account to an existing customer (only ever called for an email Google has verified). */
    public function linkGoogle(int $id, string $googleId): void
    {
        $stmt = $this->pdo->prepare('UPDATE customers SET google_id = :gid WHERE id = :id AND google_id IS NULL');
        $stmt->execute(['gid' => $googleId, 'id' => $id]);
    }

    /** @return int|null the new id, or null when the email or Google account is already registered (race-safe) */
    public function createFromGoogle(string $name, string $email, string $googleId): ?int
    {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO customers (name, email, password_hash, google_id) VALUES (:name, :email, NULL, :gid)');
            $stmt->execute(['name' => $name, 'email' => $email, 'gid' => $googleId]);
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                return null;
            }
            throw $e;
        }

        return (int) $this->pdo->lastInsertId();
    }

    /** @return int|null the new id, or null when the email is already registered (race-safe: the UNIQUE key decides) */
    public function create(string $name, string $email, string $passwordHash): ?int
    {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO customers (name, email, password_hash) VALUES (:name, :email, :hash)');
            $stmt->execute(['name' => $name, 'email' => $email, 'hash' => $passwordHash]);
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                return null;
            }
            throw $e;
        }

        return (int) $this->pdo->lastInsertId();
    }

    public function recordLogin(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE customers SET last_login_at = UTC_TIMESTAMP() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function upgradePasswordHash(int $id, string $hash): void
    {
        $stmt = $this->pdo->prepare('UPDATE customers SET password_hash = :hash WHERE id = :id');
        $stmt->execute(['hash' => $hash, 'id' => $id]);
    }
}
