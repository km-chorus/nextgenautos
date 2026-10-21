<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/repositories/AdminUserRepository.php';
require_once dirname(__DIR__) . '/repositories/AuditLogRepository.php';
require_once dirname(__DIR__) . '/repositories/PasswordResetRepository.php';
require_once __DIR__ . '/AuthService.php';
require_once __DIR__ . '/EmailService.php';
require_once __DIR__ . '/Transaction.php';

/**
 * Password reset by email.
 *
 *  - The browser never learns whether an email has an account: a request is
 *    accepted, counted and throttled identically either way.
 *  - Tokens are 256 random bits from random_bytes(); only their SHA-256 hash is
 *    stored. Each is single use and expires (PASSWORD_RESET_MINUTES, default 30).
 *    A new request replaces older links.
 *  - Completing a reset changes ONLY the password: two-factor authentication is
 *    untouched (a reset must never be a 2FA bypass), and every session created
 *    before the change is ended (password_changed_at).
 *  - Raw tokens, passwords and hashes are never logged.
 */
class PasswordResetService
{
    public function __construct(
        private PDO $pdo,
        private AdminUserRepository $users,
        private PasswordResetRepository $tokens,
        private AuditLogRepository $audit,
        private EmailService $email
    ) {
    }

    public static function create(): self
    {
        $pdo = Database::connection();

        return new self($pdo, new AdminUserRepository($pdo), new PasswordResetRepository($pdo), new AuditLogRepository($pdo), new EmailService());
    }

    /** True when an email could really be delivered from this environment (provider + public URL configured). */
    public static function isAvailable(): bool
    {
        return EmailService::isConfigured() && AppConfig::appUrl() !== null;
    }

    /**
     * Handles a reset request. Returns nothing: the caller shows the same message
     * in every case. At most one outstanding link per administrator.
     */
    public function requestReset(string $rawEmail, string $ip, string $userAgent): void
    {
        $email = AuthService::normalizeEmail($rawEmail);
        $identifier = AuthService::identifier(substr($email, 0, 255));

        $window = AppConfig::RESET_WINDOW_MINUTES;
        if ($this->audit->countRecent('ip', $ip, AuditLogRepository::RESET_REQUESTS, $window) >= AppConfig::RESET_MAX_REQUESTS_PER_IP
            || $this->audit->countRecent('identifier', $identifier, AuditLogRepository::RESET_REQUESTS, $window) >= AppConfig::RESET_MAX_REQUESTS_PER_ACCOUNT) {
            if (!$this->audit->recentEventExists('RATE_LIMIT_TRIGGERED', $ip, $window)) {
                $this->audit->record('RATE_LIMIT_TRIGGERED', null, AuditLogRepository::IDENTIFIER_TYPE, $identifier,
                    'Password reset request limit reached', $ip, $userAgent);
            }
            return; // silently: nothing is created or sent, and the response is the usual one
        }

        $user = (strlen($email) <= 255 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) ? $this->users->findByEmail($email) : null;

        // Every request is recorded and counted, whether or not the account exists.
        $this->audit->record('PASSWORD_RESET_REQUESTED', $user === null ? null : (int) $user['id'],
            AuditLogRepository::IDENTIFIER_TYPE, $identifier, 'Password reset requested', $ip, $userAgent);

        if ($user === null || $user['status'] !== 'active') {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $now = gmdate('Y-m-d H:i:s');
        $expires = gmdate('Y-m-d H:i:s', time() + AppConfig::passwordResetMinutes() * 60);

        Transaction::run($this->pdo, function () use ($user, $token, $now, $expires): void {
            $this->tokens->invalidateOutstanding((int) $user['id'], $now);
            $this->tokens->create((int) $user['id'], hash('sha256', $token), $expires);
        });

        $link = AppConfig::appUrl() . '/admin/reset-password.php?token=' . $token;
        $sent = $this->email->sendPasswordReset($email, (string) $user['name'], $link, AppConfig::passwordResetMinutes());

        if (!$sent) {
            $this->audit->record('PASSWORD_RESET_EMAIL_FAILED', (int) $user['id'], null, null, 'Reset email could not be sent', $ip, $userAgent);
        }
    }

    /** Too many bad link attempts from this address recently. */
    public function tokenAttemptsThrottled(string $ip): bool
    {
        return $this->audit->countRecent('ip', $ip, AuditLogRepository::RESET_TOKEN_FAILURES, AppConfig::RESET_TOKEN_FAILURE_WINDOW_MINUTES)
            >= AppConfig::RESET_MAX_TOKEN_FAILURES_PER_IP;
    }

    /** The token id for a valid, unused, unexpired link, or null (and the failure is logged). */
    public function validateToken(string $rawToken, string $ip, string $userAgent): ?int
    {
        $row = preg_match('/^[a-f0-9]{64}$/', $rawToken) === 1
            ? $this->tokens->findValidByHash(hash('sha256', $rawToken), gmdate('Y-m-d H:i:s'))
            : null;

        if ($row === null) {
            $this->audit->record('PASSWORD_RESET_FAILED', null, null, null, 'Invalid or expired reset link used', $ip, $userAgent);
            return null;
        }

        return $row['id'];
    }

    /**
     * Sets the new password (policy already checked by the caller). Re-validates the
     * link inside the transaction and burns it, so a link works exactly once.
     * Two-factor settings are deliberately not touched.
     */
    public function complete(int $tokenId, string $newPassword, string $ip, string $userAgent): bool
    {
        $now = gmdate('Y-m-d H:i:s');

        $done = Transaction::run($this->pdo, function () use ($tokenId, $newPassword, $now, $ip, $userAgent): bool {
            $row = $this->tokens->findValidById($tokenId, $now);
            // markUsed() succeeds for exactly one caller: a link works once, even under concurrency.
            if ($row === null || !$this->tokens->markUsed($tokenId, $now)) {
                return false; // nothing was written
            }

            $adminId = $row['admin_user_id'];
            $this->users->setPassword($adminId, password_hash($newPassword, PASSWORD_DEFAULT), $now);
            $this->tokens->invalidateOutstanding($adminId, $now);
            $this->audit->record('PASSWORD_RESET_COMPLETED', $adminId, null, null, 'Password reset completed', $ip, $userAgent);

            return true;
        });

        if (!$done) {
            $this->audit->record('PASSWORD_RESET_FAILED', null, null, null, 'Reset link no longer valid when submitting a new password', $ip, $userAgent);
        }

        return $done;
    }
}
