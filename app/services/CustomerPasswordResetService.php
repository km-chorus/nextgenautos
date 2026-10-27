<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/repositories/CustomerRepository.php';
require_once __DIR__ . '/ResetCodeStore.php';
require_once dirname(__DIR__) . '/repositories/AuditLogRepository.php';
require_once __DIR__ . '/CustomerAuthService.php';
require_once __DIR__ . '/PasswordPolicy.php';
require_once __DIR__ . '/SecretBox.php';
require_once __DIR__ . '/EmailService.php';
require_once __DIR__ . '/Transaction.php';

/**
 * Customer password recovery (owner request 2026-10-22).
 *
 * "Forgot your password?" sends ONE email with a link (/reset-password/?t=<customer id>.<token>) and a 6-digit code; either
 * lets the customer set a new password. The link and code are NEVER stored in the database (owner request): only their
 * hashes are kept, for 30 minutes, in a short-lived file cache (ResetCodeStore), and deleted when used, cancelled, replaced
 * or expired. Rules:
 *   - the answer to a request is always the same, whether or not an account exists (no way to discover customers);
 *   - at most 3 emails per email address per hour and 10 requests per IP per hour (then "too many requests");
 *   - a reset expires after 30 minutes, works once, and a newer request cancels older ones;
 *   - the cache holds only hashes: SHA-256 of the 32-byte link token, HMAC-SHA-256 (purpose key) of "<customer id>|<code>";
 *   - 5 wrong codes cancel the reset; 10 failed reset attempts per IP in 15 minutes are refused ("too many attempts");
 *   - success: the password is replaced (PasswordPolicy), the email counts as confirmed (the customer proved they read
 *     it), every OTHER session of the account ends (customers.password_changed_at), the cached reset is deleted; audited.
 * Audit descriptions never contain the email, token, code or password.
 */
final class CustomerPasswordResetService
{
    public const VALID_MINUTES = 30;
    public const MAX_EMAILS_PER_ADDRESS = 3;
    public const MAX_REQUESTS_PER_IP = 10;
    public const REQUEST_WINDOW_MINUTES = 60;
    public const MAX_CODE_ATTEMPTS = 5;
    public const MAX_FAILURES_PER_IP = 10;
    public const FAILURE_WINDOW_MINUTES = 15;

    public function __construct(
        private PDO $pdo,
        private CustomerRepository $customers,
        private ResetCodeStore $store,
        private AuditLogRepository $audit,
        private EmailService $email
    ) {
    }

    public static function create(): self
    {
        $pdo = Database::connection();

        return new self($pdo, new CustomerRepository($pdo), ResetCodeStore::create(), new AuditLogRepository($pdo), new EmailService());
    }

    /**
     * "Forgot your password?". Returns 'sent' (shown as "if an account exists, we sent..."), 'invalid' (not an email
     * address) or 'throttled'. 'sent' is also returned when no account exists or the email could not be sent (logged).
     */
    public function request(mixed $rawEmail, string $ip, string $userAgent): string
    {
        $email = is_string($rawEmail) ? CustomerAuthService::normalizeEmail($rawEmail) : '';
        if ($email === '' || strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return 'invalid';
        }
        $fingerprint = CustomerAuthService::identifier($email);
        $events = ['CUSTOMER_PASSWORD_RESET_REQUESTED'];
        if ($this->audit->countRecent('identifier', $fingerprint, $events, self::REQUEST_WINDOW_MINUTES) >= self::MAX_EMAILS_PER_ADDRESS
            || $this->audit->countRecent('ip', $ip, $events, self::REQUEST_WINDOW_MINUTES) >= self::MAX_REQUESTS_PER_IP) {
            return 'throttled';
        }
        $this->audit->record('CUSTOMER_PASSWORD_RESET_REQUESTED', null, AuditLogRepository::IDENTIFIER_TYPE, $fingerprint, 'Password reset requested', $ip, $userAgent);
        $this->store->purgeExpired();

        $customer = $this->customers->findByEmail($email);
        if ($customer === null || $customer['status'] !== 'active') {
            return 'sent';   // same answer: nothing reveals whether the address has an account
        }
        $token = bin2hex(random_bytes(32));
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $id = (int) $customer['id'];
        $this->store->put($id, hash('sha256', $token), self::codeHash($id, $code), self::VALID_MINUTES * 60);   // replaces any older reset
        $link = AppConfig::linkUrl() . '/reset-password/?t=' . $id . '.' . $token;
        $sent = false;
        try {
            $sent = $this->email->sendTemplate((string) $customer['email'], (string) $customer['name'], self::emailContent((string) $customer['name'], $link, $code));
        } catch (Throwable $e) {
            error_log('Password reset email failed: ' . get_class($e));
        }
        $this->audit->record($sent ? 'CUSTOMER_PASSWORD_RESET_SENT' : 'CUSTOMER_PASSWORD_RESET_EMAIL_FAILED', null, 'customer', (int) $customer['id'],
            $sent ? 'Password reset email sent' : 'Password reset email could not be sent', $ip, $userAgent);

        return 'sent';
    }

    /** Whether a reset link can be used, before the form is shown: 'ok' | 'expired' | 'invalid' | 'throttled'. */
    public function checkLink(mixed $raw, string $ip): string
    {
        if ($this->ipThrottled($ip)) {
            return 'throttled';
        }
        $state = $this->tokenCustomer($raw);   // first: an expired link must still be recognised as "expired"
        $this->store->purgeExpired();

        return $state;
    }

    /**
     * Sets the new password with the link token or with email + code.
     *
     * @param array<string,mixed> $post t (link) | email + code; new_password, new_password_confirm
     * @return array{status:'ok'|'invalid'|'expired'|'throttled',errors:array<string,string>}
     */
    public function reset(array $post, string $ip, string $userAgent): array
    {
        if ($this->ipThrottled($ip)) {
            return ['status' => 'throttled', 'errors' => []];
        }
        $str = static fn (mixed $v, int $max): string => is_string($v) && strlen($v) <= $max ? $v : '';
        $new = $str($post['new_password'] ?? null, 1024);
        $confirm = $str($post['new_password_confirm'] ?? null, 1024);
        $viaLink = isset($post['t']);

        $result = Transaction::run($this->pdo, function () use ($post, $str, $new, $confirm, $viaLink, $ip, $userAgent): array {
            // 1. Which reset?
            if ($viaLink) {
                $state = $this->tokenCustomer($post['t']);
                if ($state !== 'ok') {
                    if ($state === 'invalid') {
                        $this->fail(null, 'unknown or used link', $ip, $userAgent);
                    }

                    return ['status' => $state, 'errors' => []];
                }
                $customerId = (int) explode('.', (string) $post['t'])[0];
            } else {
                $email = CustomerAuthService::normalizeEmail($str($post['email'] ?? null, 255));
                $code = preg_replace('/\s+/', '', $str($post['code'] ?? null, 20)) ?? '';
                $errors = [];
                if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    $errors['email'] = 'Enter the email address of your account.';
                }
                if (preg_match('/^[0-9]{6}$/D', $code) !== 1) {
                    $errors['code'] = 'Enter the 6-digit code from the email.';
                }
                if ($errors !== []) {
                    return ['status' => 'invalid', 'errors' => $errors + self::passwordErrors($new, $confirm, $email)];
                }
                $customer = $this->customers->findByEmail($email);
                $reset = $customer === null ? null : $this->store->get((int) $customer['id']);
                if ($reset === null || !hash_equals($reset['code'], self::codeHash((int) $customer['id'], $code))) {
                    if ($reset !== null && $this->store->addAttempt((int) $customer['id']) >= self::MAX_CODE_ATTEMPTS) {
                        $this->store->delete((int) $customer['id']);   // too many wrong codes: this reset is destroyed
                    }
                    $this->fail($customer === null ? null : (int) $customer['id'], 'wrong or expired code', $ip, $userAgent);

                    return ['status' => 'invalid', 'errors' => ['code' => 'The code is not correct or has expired. Check the latest email, or request a new code.']];
                }
            }

            // 2. The new password
            $customer = $this->customers->findById($viaLink ? $customerId : (int) $customer['id']);
            if ($customer === null || $customer['status'] !== 'active') {
                return ['status' => 'invalid', 'errors' => []];
            }
            $errors = self::passwordErrors($new, $confirm, (string) $customer['email']);
            if ($errors !== []) {
                return ['status' => 'invalid', 'errors' => $errors];   // the reset stays open: the customer can try another password
            }

            // 3. Apply: the cached reset is destroyed first (single use, even for two simultaneous submissions), then the new
            //    password, the email confirmed, every other session ended.
            $id = (int) $customer['id'];
            if (!$this->store->claim($id)) {
                return ['status' => 'invalid', 'errors' => []];
            }
            $this->customers->setPassword($id, password_hash($new, PASSWORD_DEFAULT));
            $this->pdo->prepare('UPDATE customers SET email_verified_at = COALESCE(email_verified_at, UTC_TIMESTAMP()) WHERE id = ?')->execute([$id]);
            $this->audit->record('CUSTOMER_PASSWORD_RESET_DONE', null, 'customer', $id, 'Password reset completed (' . ($viaLink ? 'link' : 'code') . ')', $ip, $userAgent);

            return ['status' => 'ok', 'errors' => [], 'customer' => $id];
        });
        $this->store->purgeExpired();
        unset($result['customer']);

        return $result;
    }

    /** HMAC of "<customer id>|<code>" with a purpose key: even a copy of the cache does not reveal codes. */
    public static function codeHash(int $customerId, string $code): string
    {
        return hash_hmac('sha256', $customerId . '|' . $code, SecretBox::derive('customer-password-reset-code'));
    }

    /** @return array<string,string> */
    private static function passwordErrors(string $new, string $confirm, string $email): array
    {
        if ($new === '') {
            return ['new_password' => 'Enter a new password.'];
        }
        $problems = PasswordPolicy::validate($new, $email);
        if ($problems !== []) {
            return ['new_password' => $problems[0]];
        }

        return hash_equals($new, $confirm) ? [] : ['new_password_confirm' => 'The two passwords do not match.'];
    }

    /** "<customer id>.<64 hex>" → 'ok' when the cached token matches (constant-time), 'expired' or 'invalid'. */
    private function tokenCustomer(mixed $raw): string
    {
        if (!is_string($raw) || preg_match('/^([1-9][0-9]{0,9})\.([a-f0-9]{64})$/D', $raw, $m) !== 1) {
            return 'invalid';
        }
        $reset = $this->store->peek((int) $m[1]);
        if ($reset === null || !hash_equals($reset['token'], hash('sha256', $m[2]))) {
            return 'invalid';   // nothing is revealed about a link whose secret part is wrong
        }
        if ($reset['expires'] <= time()) {
            $this->store->delete((int) $m[1]);

            return 'expired';
        }

        return 'ok';
    }

    private function ipThrottled(string $ip): bool
    {
        return $this->audit->countRecent('ip', $ip, ['CUSTOMER_PASSWORD_RESET_FAILED'], self::FAILURE_WINDOW_MINUTES) >= self::MAX_FAILURES_PER_IP;
    }

    private function fail(?int $customerId, string $why, string $ip, string $userAgent): void
    {
        $this->audit->record('CUSTOMER_PASSWORD_RESET_FAILED', null, $customerId === null ? null : 'customer', $customerId, 'Password reset refused: ' . $why, $ip, $userAgent);
    }

    /** The branded email: one link and the same reset as a 6-digit code. @return array<string,mixed> */
    private static function emailContent(string $name, string $link, string $code): array
    {
        $brand = AppConfig::mailFromName();

        return [
            'subject'    => 'Reset your ' . $brand . ' password',
            'preheader'  => 'Your code is ' . $code . '. It expires in ' . self::VALID_MINUTES . ' minutes.',
            'eyebrow'    => 'Account security',
            'title'      => 'Reset your password',
            'greeting'   => 'Hello ' . ($name !== '' ? $name : 'there') . ',',
            'paragraphs' => ['We received a request to reset the password of your ' . $brand . ' account. Press the button to choose a new password, or enter this code on the reset page:'],
            'details'    => [['Your code', $code], ['Valid for', self::VALID_MINUTES . ' minutes, once']],
            'button'     => ['label' => 'Set a new password', 'url' => $link],
            'note'       => "If you did not ask for this, ignore this email: your password stays the same.\nNever share this code with anyone. Our team will never ask you for it.",
            'reason'     => 'You received this email because a password reset was requested for your account on the ' . $brand . ' website.',
        ];
    }
}
