<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/repositories/CustomerRepository.php';
require_once dirname(__DIR__) . '/repositories/AuditLogRepository.php';
require_once dirname(__DIR__) . '/validation/CustomerValidator.php';
require_once __DIR__ . '/PasswordPolicy.php';
require_once __DIR__ . '/CustomerSessionService.php';

/**
 * Account settings for a signed-in customer (owner request 2026-10-22): change the name and the password. The customer id
 * always comes from the server-side session (the caller), never from the form.
 *
 * Rules:
 *   - name: same rules as registration;
 *   - password: the CURRENT password is required when the account has one (a Google-only account may set its first
 *     password); the new one follows PasswordPolicy and must be typed twice; wrong current passwords are throttled
 *     (5 per 15 minutes per account); after a change the session id is regenerated;
 *   - the email address is NOT changed here: shipments are linked to the confirmed email, so a change needs our team.
 * Every change is audited (no values, no passwords).
 */
final class CustomerAccountService
{
    public const MAX_PASSWORD_FAILURES = 5;
    public const WINDOW_MINUTES = 15;
    private const MAX_PASSWORD_BYTES = 1024;

    public function __construct(private CustomerRepository $customers, private AuditLogRepository $audit, private CustomerSessionService $sessions)
    {
    }

    public static function create(): self
    {
        $pdo = Database::connection();

        return new self(new CustomerRepository($pdo), new AuditLogRepository($pdo), new CustomerSessionService());
    }

    /** Whether the account has a password (false = it signs in with Google only). */
    public function hasPassword(int $customerId): bool
    {
        $row = $this->customers->findById($customerId);

        return $row !== null && ($row['password_hash'] ?? null) !== null;
    }

    /**
     * @param array<string,mixed> $post
     * @return array{status:'ok'|'unchanged'|'invalid',errors:array<string,string>,value:string}
     */
    public function changeName(int $customerId, array $post, string $ip, string $userAgent): array
    {
        $checked = CustomerValidator::name($post['name'] ?? null);
        if ($checked['error'] !== null) {
            return ['status' => 'invalid', 'errors' => ['name' => $checked['error']], 'value' => $checked['value']];
        }
        $row = $this->customers->findById($customerId);
        if ($row !== null && (string) $row['name'] === $checked['value']) {
            return ['status' => 'unchanged', 'errors' => [], 'value' => $checked['value']];
        }
        $this->customers->updateName($customerId, $checked['value']);
        $this->audit->record('CUSTOMER_NAME_CHANGED', null, 'customer', $customerId, 'Customer changed their name', $ip, $userAgent);

        return ['status' => 'ok', 'errors' => [], 'value' => $checked['value']];
    }

    /**
     * @param array<string,mixed> $post current_password (when the account has one), new_password, new_password_confirm
     * @return array{status:'ok'|'invalid'|'throttled',errors:array<string,string>}
     */
    public function changePassword(int $customerId, array $post, string $ip, string $userAgent): array
    {
        $row = $this->customers->findById($customerId);
        if ($row === null) {
            return ['status' => 'invalid', 'errors' => ['new_password' => 'Your password could not be changed. Please sign in again.']];
        }
        $str = static fn (mixed $v): string => is_string($v) && strlen($v) <= self::MAX_PASSWORD_BYTES ? $v : '';
        $current = $str($post['current_password'] ?? null);
        $new = $str($post['new_password'] ?? null);
        $confirm = $str($post['new_password_confirm'] ?? null);
        $hasPassword = ($row['password_hash'] ?? null) !== null;

        if ($hasPassword && $this->audit->countRecent('customer', $customerId, ['CUSTOMER_PASSWORD_CHANGE_FAILED'], self::WINDOW_MINUTES) >= self::MAX_PASSWORD_FAILURES) {
            return ['status' => 'throttled', 'errors' => []];
        }

        $errors = [];
        if ($hasPassword && $current === '') {
            $errors['current_password'] = 'Enter your current password.';
        }
        if ($new === '') {
            $errors['new_password'] = 'Enter a new password.';
        } else {
            $problems = PasswordPolicy::validate($new, (string) $row['email']);
            if ($problems !== []) {
                $errors['new_password'] = $problems[0];
            } elseif (!hash_equals($new, $confirm)) {
                $errors['new_password_confirm'] = 'The two passwords do not match.';
            }
        }
        if ($errors === [] && $hasPassword && !password_verify($current, (string) $row['password_hash'])) {
            $this->audit->record('CUSTOMER_PASSWORD_CHANGE_FAILED', null, 'customer', $customerId, 'Password change refused: wrong current password', $ip, $userAgent);
            $errors['current_password'] = 'Your current password is not correct.';
        }
        if ($errors !== []) {
            return ['status' => 'invalid', 'errors' => $errors];
        }

        $this->customers->setPassword($customerId, password_hash($new, PASSWORD_DEFAULT));   // also ends other sessions
        $this->audit->record('CUSTOMER_PASSWORD_CHANGED', null, 'customer', $customerId, $hasPassword ? 'Customer changed their password' : 'Customer set a password (Google account)', $ip, $userAgent);
        $this->sessions->establish($customerId);   // new session id after a credential change
        $this->sessions->consumeWelcome();         // no "welcome back" toast for this

        return ['status' => 'ok', 'errors' => []];
    }
}
