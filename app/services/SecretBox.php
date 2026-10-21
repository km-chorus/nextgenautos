<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

/**
 * Authenticated encryption for secrets stored in the database (the TOTP secret),
 * using libsodium's secretbox (XSalsa20-Poly1305) with a fresh random nonce per
 * value. The key is APP_KEY from the environment: it is never stored in the
 * database or Git. Tampered or wrongly keyed data fails to decrypt (it never
 * yields garbage). If the key is missing, callers get a clear exception: there
 * is no plaintext fallback.
 */
final class SecretBox
{
    private const PREFIX = 'v1:';

    public static function isAvailable(): bool
    {
        return function_exists('sodium_crypto_secretbox') && AppConfig::appKey() !== null;
    }

    public static function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, self::key());

        return self::PREFIX . base64_encode($nonce . $cipher);
    }

    public static function decrypt(string $stored): string
    {
        if (!str_starts_with($stored, self::PREFIX)) {
            throw new RuntimeException('Unrecognized secret format.');
        }
        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Unrecognized secret format.');
        }

        $plain = sodium_crypto_secretbox_open(
            substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            self::key()
        );
        if ($plain === false) {
            throw new RuntimeException('Secret could not be decrypted (wrong key or altered data).');
        }

        return $plain;
    }

    /** Purpose-specific subkey (so one key never serves two jobs). Binary string. */
    public static function derive(string $purpose): string
    {
        return hash_hmac('sha256', 'vip:' . $purpose, self::key(), true);
    }

    private static function key(): string
    {
        $key = AppConfig::appKey();
        if ($key === null) {
            throw new RuntimeException('APP_KEY is not configured.');
        }

        return $key;
    }
}
