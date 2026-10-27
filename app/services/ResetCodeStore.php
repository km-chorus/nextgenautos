<?php

declare(strict_types=1);

require_once __DIR__ . '/SecretBox.php';

/**
 * Short-lived cache for customer password resets (owner request 2026-10-22: reset links and codes must NOT be stored in
 * the database; they are kept for a limited time and then destroyed).
 *
 * One small file per customer in storage/password_resets/ (outside the database; the folder is denied to web visitors
 * by its own .htaccess, and the deployment checklist denies /storage/ as a whole). Even so, nothing in it can be used:
 *   - the file name is an HMAC of the customer id (it reveals no id and cannot be guessed);
 *   - the content is only the SHA-256 of the link token, the HMAC of the code, a counter of wrong codes and the expiry.
 * An entry is DELETED when it is used, cancelled (too many wrong codes), replaced by a newer request, or found expired;
 * every reset request and reset attempt also sweeps away all expired entries. Writes are atomic (temp file + rename) and
 * read-modify-write is done under an exclusive lock.
 */
final class ResetCodeStore
{
    public function __construct(private string $dir, private string $key)
    {
    }

    public static function create(): self
    {
        return new self(dirname(__DIR__, 2) . '/storage/password_resets', SecretBox::derive('customer-password-reset-store'));
    }

    /** Stores (or replaces) the customer's reset. A newer request therefore cancels the older link and code. */
    public function put(int $customerId, string $tokenHash, string $codeHash, int $ttlSeconds): void
    {
        $this->ensureDir();
        $this->write($customerId, ['token' => $tokenHash, 'code' => $codeHash, 'attempts' => 0, 'expires' => time() + $ttlSeconds]);
    }

    /**
     * The customer's reset while it is valid, else null (an expired entry is deleted on the spot).
     *
     * @return array{token:string,code:string,attempts:int,expires:int}|null
     */
    public function get(int $customerId): ?array
    {
        $file = $this->file($customerId);
        $raw = is_file($file) ? @file_get_contents($file) : false;
        $entry = $raw === false ? null : json_decode($raw, true);
        if (!is_array($entry) || !isset($entry['token'], $entry['code'], $entry['attempts'], $entry['expires'])) {
            return null;
        }
        if ((int) $entry['expires'] <= time()) {
            $this->delete($customerId);

            return null;
        }

        return ['token' => (string) $entry['token'], 'code' => (string) $entry['code'], 'attempts' => (int) $entry['attempts'], 'expires' => (int) $entry['expires']];
    }

    /**
     * The entry even when expired (for telling "expired" from "invalid" AFTER the token has been checked), or null.
     *
     * @return array{token:string,code:string,attempts:int,expires:int}|null
     */
    public function peek(int $customerId): ?array
    {
        $file = $this->file($customerId);
        $entry = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;

        return is_array($entry) && isset($entry['token'], $entry['code'], $entry['attempts'], $entry['expires'])
            ? ['token' => (string) $entry['token'], 'code' => (string) $entry['code'], 'attempts' => (int) $entry['attempts'], 'expires' => (int) $entry['expires']] : null;
    }

    /** One more wrong code (under a lock); returns the new count, or 0 when there is no valid entry. */
    public function addAttempt(int $customerId): int
    {
        $file = $this->file($customerId);
        $handle = is_file($file) ? @fopen($file, 'c+') : false;
        if ($handle === false) {
            return 0;
        }
        try {
            flock($handle, LOCK_EX);
            $entry = json_decode((string) stream_get_contents($handle), true);
            if (!is_array($entry) || (int) ($entry['expires'] ?? 0) <= time()) {
                return 0;
            }
            $entry['attempts'] = (int) $entry['attempts'] + 1;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode($entry));
            fflush($handle);

            return $entry['attempts'];
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Destroys the entry and reports whether THIS call removed it. Used to claim a reset: when two requests use the same
     * link at the same moment, only the one whose delete succeeds may change the password.
     */
    public function claim(int $customerId): bool
    {
        $file = $this->file($customerId);

        return is_file($file) && @unlink($file);
    }

    /** Destroys the customer's reset (used, cancelled or replaced). */
    public function delete(int $customerId): void
    {
        $file = $this->file($customerId);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /** Deletes every expired entry (and stray temp files). Returns how many files were removed. */
    public function purgeExpired(): int
    {
        $removed = 0;
        foreach (glob($this->dir . '/*.json') ?: [] as $file) {
            $entry = json_decode((string) @file_get_contents($file), true);
            if (!is_array($entry) || (int) ($entry['expires'] ?? 0) <= time()) {
                $removed += @unlink($file) ? 1 : 0;
            }
        }
        foreach (glob($this->dir . '/*.tmp') ?: [] as $file) {
            if (@filemtime($file) < time() - 60) {
                $removed += @unlink($file) ? 1 : 0;
            }
        }

        return $removed;
    }

    private function file(int $customerId): string
    {
        return $this->dir . '/' . hash_hmac('sha256', 'reset|' . $customerId, $this->key) . '.json';
    }

    private function write(int $customerId, array $entry): void
    {
        $target = $this->file($customerId);
        $tmp = $target . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (@file_put_contents($tmp, (string) json_encode($entry), LOCK_EX) === false || !@rename($tmp, $target)) {
            @unlink($tmp);
            throw new RuntimeException('The password reset cache could not be written.');
        }
        @chmod($target, 0600);
    }

    private function ensureDir(): void
    {
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0700, true) && !is_dir($this->dir)) {
            throw new RuntimeException('The password reset cache folder could not be created.');
        }
        $deny = $this->dir . '/.htaccess';
        if (!is_file($deny)) {
            @file_put_contents($deny, "# Password reset cache: never served to visitors.\nRequire all denied\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
        }
    }
}
