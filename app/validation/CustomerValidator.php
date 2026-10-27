<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/PasswordPolicy.php';

/**
 * Validation of the customer registration form (spec 13_spec_customer_auth.md). The server is the only enforcement point;
 * the browser's own checks are a convenience. Returns cleaned values plus field errors; never throws.
 */
final class CustomerValidator
{
    public const MAX_NAME = 100;
    public const MAX_EMAIL = 255;

    /**
     * @param array<string,mixed> $post
     * @return array{values:array{name:string,email:string,password:string},errors:array<string,string>}
     */
    public static function registration(array $post): array
    {
        $name = is_string($post['name'] ?? null) ? trim(preg_replace('/\s+/u', ' ', $post['name']) ?? '') : '';
        $email = is_string($post['email'] ?? null) ? strtolower(trim($post['email'])) : '';
        $password = is_string($post['password'] ?? null) ? $post['password'] : '';
        $confirm = is_string($post['password_confirm'] ?? null) ? $post['password_confirm'] : '';

        $errors = [];
        $nameError = self::name($post['name'] ?? null)['error'];
        if ($nameError !== null) {
            $errors['name'] = $nameError;
        }

        if ($email === '') {
            $errors['email'] = 'Enter your email address.';
        } elseif (strlen($email) > self::MAX_EMAIL || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email address.';
        }

        if ($password === '') {
            $errors['password'] = 'Create a password.';
        } else {
            $problems = PasswordPolicy::validate($password, $email);
            if ($problems !== []) {
                $errors['password'] = $problems[0];
            }
        }

        if ($password !== '' && !isset($errors['password']) && !hash_equals($password, $confirm)) {
            $errors['password_confirm'] = 'The two passwords do not match.';
        }

        return ['values' => ['name' => $name, 'email' => $email, 'password' => $password], 'errors' => $errors];
    }

    /**
     * A customer's full name (registration and account settings): spaces collapsed, up to MAX_NAME characters, no
     * control characters or angle brackets.
     *
     * @return array{value:string,error:?string}
     */
    public static function name(mixed $raw): array
    {
        $name = is_string($raw) && strlen($raw) <= 2000 && mb_check_encoding($raw, 'UTF-8') ? trim(preg_replace('/\s+/u', ' ', $raw) ?? '') : '';
        if ($name === '') {
            return ['value' => '', 'error' => 'Enter your full name.'];
        }
        if (mb_strlen($name) > self::MAX_NAME || preg_match('/[\p{Cc}<>]/u', $name) === 1) {
            return ['value' => $name, 'error' => 'Use up to ' . self::MAX_NAME . ' characters, without special symbols.'];
        }

        return ['value' => $name, 'error' => null];
    }
}
