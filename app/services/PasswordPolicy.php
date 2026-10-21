<?php

declare(strict_types=1);

/**
 * Administrator password policy (used by the account-creation script and any
 * future password change/reset). The server is the only enforcement point.
 *
 * Rules: at least 12 characters, no composition rules (so long passphrases are
 * welcome), and at most 72 bytes because bcrypt, PHP's default algorithm, only
 * uses the first 72 bytes of a password.
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 12;
    public const MAX_BYTES = 72;

    /** @return list<string> human-readable problems; empty when the password is acceptable */
    public static function validate(string $password, string $email = ''): array
    {
        $errors = [];

        if (strlen($password) < self::MIN_LENGTH) {
            $errors[] = 'Use at least ' . self::MIN_LENGTH . ' characters.';
        }
        if (strlen($password) > self::MAX_BYTES) {
            $errors[] = 'Use at most ' . self::MAX_BYTES . ' bytes (about 72 characters).';
        }
        if (strlen($password) >= self::MIN_LENGTH && count(array_unique(str_split($password))) < 4) {
            $errors[] = 'Use a less repetitive password.';
        }
        if ($email !== '' && strcasecmp($password, $email) === 0) {
            $errors[] = 'The password must not be the email address.';
        }

        return $errors;
    }

    /** Feedback label only (never enforced): Weak, Fair, Good or Strong. */
    public static function strength(string $password): string
    {
        $length = strlen($password);
        if ($length < self::MIN_LENGTH) {
            return 'Weak';
        }

        $classes = 0;
        foreach (['/[a-z]/', '/[A-Z]/', '/[0-9]/', '/[^a-zA-Z0-9]/'] as $pattern) {
            $classes += preg_match($pattern, $password) === 1 ? 1 : 0;
        }

        if ($length >= 20 || ($length >= 16 && $classes >= 3)) {
            return 'Strong';
        }
        if ($length >= 16 || $classes >= 3) {
            return 'Good';
        }

        return 'Fair';
    }
}
