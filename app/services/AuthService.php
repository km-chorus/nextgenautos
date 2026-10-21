<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/repositories/AdminUserRepository.php';
require_once dirname(__DIR__) . '/repositories/AuditLogRepository.php';
require_once dirname(__DIR__) . '/repositories/RecoveryCodeRepository.php';
require_once __DIR__ . '/SessionService.php';
require_once __DIR__ . '/AuthorizationService.php';
require_once __DIR__ . '/TwoFactorService.php';
require_once __DIR__ . '/Transaction.php';

/**
 * Admin authentication: credential verification, second factor, throttling,
 * session resolution and logout. Returns plain data (no markup).
 *
 * Login flow: normalize input -> throttle check -> find admin -> verify password
 * (always exactly one password_verify, against a dummy hash when the account does
 * not exist, so timing does not reveal which emails are real) -> reject disabled
 * accounts -> if the account has two-factor authentication: store a short-lived
 * "password verified" state that grants NOTHING and wait for the second factor;
 * otherwise (or after the second factor succeeds) update last_login_at and log in
 * one transaction, then regenerate the session id and create the session.
 *
 * Every failure looks identical to the caller; the audit log records the real
 * reason. Nothing here ever logs or returns a password, a hash, a TOTP code or
 * secret, a recovery code, a session id or a CSRF token.
 */
class AuthService
{
    public const LOGIN_OK = 'ok';
    public const LOGIN_INVALID = 'invalid';
    public const LOGIN_THROTTLED = 'throttled';
    public const LOGIN_TWO_FACTOR = 'two_factor'; // password correct, second factor still required

    public const SESSION_OK = 'ok';
    public const SESSION_NONE = 'none';        // not signed in
    public const SESSION_EXPIRED = 'expired';  // inactivity timeout (session destroyed)
    public const SESSION_INVALID = 'invalid';  // account gone/disabled/not permitted/password changed (session destroyed)

    public const SECOND_FACTOR_EXPIRED = 'expired'; // no pending login, or it timed out

    /**
     * Verified against when no real hash applies, so the unknown-email path costs
     * the same as the wrong-password path. Same algorithm/cost as PASSWORD_DEFAULT
     * (regenerate if PHP's default cost changes). It is a hash of random data
     * that nobody knows: it cannot authenticate anything.
     */
    private const DUMMY_HASH = '$2y$12$fjdaje6J1EqtyCXEf2FaDu0cIGj3cw0S3hev2KUdUKT5RUh9v6/N.';

    private const MAX_EMAIL_LENGTH = 255;
    private const MAX_PASSWORD_BYTES = 1024; // bounds work done for absurd inputs

    public function __construct(
        private PDO $pdo,
        private AdminUserRepository $users,
        private AuditLogRepository $audit,
        private SessionService $sessions,
        private ?TwoFactorService $twoFactor = null
    ) {
    }

    public static function create(): self
    {
        $pdo = Database::connection();
        $users = new AdminUserRepository($pdo);

        return new self(
            $pdo,
            $users,
            new AuditLogRepository($pdo),
            new SessionService(),
            new TwoFactorService($pdo, $users, new RecoveryCodeRepository($pdo))
        );
    }

    public function sessions(): SessionService
    {
        return $this->sessions;
    }

    public function twoFactor(): TwoFactorService
    {
        return $this->twoFactor ??= new TwoFactorService($this->pdo, $this->users, new RecoveryCodeRepository($this->pdo));
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    /** One-way 60-bit fingerprint of a normalized email, for throttling without storing the email. */
    public static function identifier(string $normalizedEmail): int
    {
        return (int) hexdec(substr(hash('sha256', 'login-identifier:' . $normalizedEmail), 0, 15));
    }

    /**
     * @return array{status:string,admin:?array}
     * @throws Throwable on infrastructure failure (the caller must treat that as "not signed in")
     */
    public function attempt(string $rawEmail, string $password, string $ip, string $userAgent): array
    {
        $email = self::normalizeEmail($rawEmail);
        $identifier = self::identifier(substr($email, 0, self::MAX_EMAIL_LENGTH));

        if ($this->isThrottled($ip, $identifier)) {
            $this->logRateLimitOnce($ip, $identifier, $userAgent, 'Login rate limit reached');
            return ['status' => self::LOGIN_THROTTLED, 'admin' => null];
        }

        $wellFormed = strlen($email) <= self::MAX_EMAIL_LENGTH
            && strlen($password) <= self::MAX_PASSWORD_BYTES
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;

        $user = $wellFormed ? $this->users->findByEmail($email) : null;

        // Exactly one password verification on every path.
        $verified = password_verify($wellFormed ? $password : '', $user['password_hash'] ?? self::DUMMY_HASH);

        if ($user !== null && $user['status'] !== 'active') {
            $this->audit->record('ACCOUNT_DISABLED_LOGIN_ATTEMPT', (int) $user['id'], AuditLogRepository::IDENTIFIER_TYPE,
                $identifier, 'Sign-in attempt on a disabled account', $ip, $userAgent);
            return ['status' => self::LOGIN_INVALID, 'admin' => null];
        }

        if ($user === null || !$verified) {
            $this->audit->record('LOGIN_FAILURE', $user === null ? null : (int) $user['id'], AuditLogRepository::IDENTIFIER_TYPE,
                $identifier, $user === null ? 'Failed sign-in (no matching account)' : 'Failed sign-in (wrong password)',
                $ip, $userAgent);
            return ['status' => self::LOGIN_INVALID, 'admin' => null];
        }

        // Password is correct. Quietly upgrade an outdated hash (this is not a password change).
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $this->users->upgradePasswordHash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        if ((int) $user['two_factor_enabled'] === 1) {
            // NOT signed in yet: a restricted, short-lived state that unlocks nothing.
            $this->sessions->beginTwoFactor((int) $user['id']);
            return ['status' => self::LOGIN_TWO_FACTOR, 'admin' => null];
        }

        $admin = ['id' => (int) $user['id'], 'name' => (string) $user['name'], 'role' => (string) $user['role']];
        $this->completeLogin($admin, $identifier, $ip, $userAgent, 'Administrator signed in');

        return ['status' => self::LOGIN_OK, 'admin' => $admin];
    }

    /**
     * Second step of a two-factor login: an authenticator code, or (when $recovery) a
     * one-time recovery code. Failures count against a shared limit; the pending state
     * is the only thing that ties this request to the password step.
     *
     * @return array{status:string,admin:?array}  status: ok | invalid | throttled | expired
     */
    public function verifySecondFactor(string $input, bool $recovery, string $ip, string $userAgent): array
    {
        $pending = $this->sessions->pendingTwoFactor();
        if ($pending === null) {
            return ['status' => self::SECOND_FACTOR_EXPIRED, 'admin' => null];
        }
        $adminId = $pending['admin_id'];

        if ($this->secondFactorThrottled($adminId, $ip)) {
            $this->logRateLimitOnce($ip, null, $userAgent, 'Second-factor rate limit reached', $adminId);
            return ['status' => self::LOGIN_THROTTLED, 'admin' => null];
        }

        $row = $this->users->findFullById($adminId);
        if ($row === null || $row['status'] !== 'active' || (int) $row['two_factor_enabled'] !== 1) {
            $this->sessions->clearPendingTwoFactor();
            return ['status' => self::SECOND_FACTOR_EXPIRED, 'admin' => null];
        }

        $accepted = $recovery
            ? $this->twoFactor()->consumeRecoveryCode($adminId, $input)
            : $this->twoFactor()->verifyLoginCode($row, $input);

        if (!$accepted) {
            $this->audit->record('2FA_FAILURE', $adminId, null, null,
                $recovery ? 'Invalid recovery code' : 'Invalid verification code', $ip, $userAgent);
            return ['status' => self::LOGIN_INVALID, 'admin' => null];
        }

        $identifier = self::identifier(self::normalizeEmail((string) $row['email']));
        $admin = ['id' => $adminId, 'name' => (string) $row['name'], 'role' => (string) $row['role']];
        $this->completeLogin($admin, $identifier, $ip, $userAgent,
            $recovery ? 'Administrator signed in with a recovery code' : 'Administrator signed in with two-factor authentication',
            $recovery);

        return ['status' => self::LOGIN_OK, 'admin' => $admin];
    }

    /**
     * Sensitive actions (disable 2FA, regenerate recovery codes, change password)
     * require the CURRENT password and, when 2FA is on and $requireSecondFactor, a
     * current authenticator code or recovery code. Failures are logged and throttled.
     *
     * @return string 'ok' | 'invalid' | 'throttled'
     */
    public function confirmIdentity(int $adminId, string $password, ?string $secondFactor, bool $isRecoveryCode, string $ip, string $userAgent): string
    {
        if ($this->secondFactorThrottled($adminId, $ip)) {
            $this->logRateLimitOnce($ip, null, $userAgent, 'Re-authentication rate limit reached', $adminId);
            return self::LOGIN_THROTTLED;
        }

        $row = $this->users->findFullById($adminId);
        if ($row === null || $row['status'] !== 'active') {
            return self::LOGIN_INVALID;
        }

        if (strlen($password) > self::MAX_PASSWORD_BYTES || !password_verify($password, $row['password_hash'])) {
            $this->audit->record('REAUTH_FAILURE', $adminId, null, null, 'Wrong current password for a sensitive action', $ip, $userAgent);
            return self::LOGIN_INVALID;
        }

        if ($secondFactor !== null && (int) $row['two_factor_enabled'] === 1) {
            $accepted = $isRecoveryCode
                ? $this->twoFactor()->consumeRecoveryCode($adminId, $secondFactor)
                : $this->twoFactor()->verifyLoginCode($row, $secondFactor);
            if (!$accepted) {
                $this->audit->record('2FA_FAILURE', $adminId, null, null,
                    $isRecoveryCode ? 'Invalid recovery code (sensitive action)' : 'Invalid verification code (sensitive action)', $ip, $userAgent);
                return self::LOGIN_INVALID;
            }
            if ($isRecoveryCode) {
                $this->audit->record('RECOVERY_CODE_USED', $adminId, null, null, 'Recovery code used for a sensitive action', $ip, $userAgent);
            }
        }

        return 'ok';
    }

    /** Two-factor status of an administrator, for the security page. @return array{enabled:bool,confirmedAt:?string,email:string} */
    public function twoFactorState(int $adminId): array
    {
        $row = $this->users->findFullById($adminId);

        return [
            'enabled'     => $row !== null && (int) $row['two_factor_enabled'] === 1,
            'confirmedAt' => $row['two_factor_confirmed_at'] ?? null,
            'email'       => (string) ($row['email'] ?? ''),
        ];
    }

    /**
     * Confirms an authenticator enrollment with the first code. 2FA is enabled ONLY
     * when the code is valid. A wrong code is logged and counts toward the same limit
     * as other second-factor failures.
     *
     * @return array{status:string,codes:list<string>}  status: ok | invalid | throttled
     */
    public function confirmEnrollment(int $adminId, string $secret, string $code, string $ip, string $userAgent): array
    {
        if ($this->secondFactorThrottled($adminId, $ip)) {
            $this->logRateLimitOnce($ip, null, $userAgent, 'Two-factor setup rate limit reached', $adminId);
            return ['status' => self::LOGIN_THROTTLED, 'codes' => []];
        }

        $codes = $this->twoFactor()->confirmEnrollment($adminId, $secret, $code);
        if ($codes === null) {
            $this->audit->record('2FA_FAILURE', $adminId, null, null, 'Invalid verification code during setup', $ip, $userAgent);
            return ['status' => self::LOGIN_INVALID, 'codes' => []];
        }

        $this->audit->record('2FA_SETUP_COMPLETED', $adminId, null, null, 'Two-factor authentication enabled', $ip, $userAgent);

        return ['status' => 'ok', 'codes' => $codes];
    }

    /** Turns 2FA off. Needs the current password AND a current authenticator or recovery code. */
    public function disableTwoFactor(int $adminId, string $password, string $secondFactor, bool $isRecoveryCode, string $ip, string $userAgent): string
    {
        $status = $this->confirmIdentity($adminId, $password, $secondFactor, $isRecoveryCode, $ip, $userAgent);
        if ($status !== 'ok') {
            return $status;
        }

        $this->twoFactor()->disable($adminId);
        $this->audit->record('2FA_DISABLED', $adminId, null, null, 'Two-factor authentication disabled', $ip, $userAgent);

        return 'ok';
    }

    /**
     * Replaces all recovery codes (the old ones stop working at once). Needs the
     * current password and a current authenticator code.
     *
     * @return array{status:string,codes:list<string>}
     */
    public function regenerateRecoveryCodes(int $adminId, string $password, string $code, string $ip, string $userAgent): array
    {
        $status = $this->confirmIdentity($adminId, $password, $code, false, $ip, $userAgent);
        if ($status !== 'ok') {
            return ['status' => $status, 'codes' => []];
        }

        $codes = $this->twoFactor()->regenerateRecoveryCodes($adminId);
        $this->audit->record('RECOVERY_CODES_REGENERATED', $adminId, null, null, 'Recovery codes regenerated', $ip, $userAgent);

        return ['status' => 'ok', 'codes' => $codes];
    }

    /** Changes an administrator's own password (identity already confirmed by the caller). */
    public function changePassword(int $adminId, string $newPassword, string $ip, string $userAgent): void
    {
        Transaction::run($this->pdo, function () use ($adminId, $newPassword, $ip, $userAgent): void {
            $this->users->setPassword($adminId, password_hash($newPassword, PASSWORD_DEFAULT), gmdate('Y-m-d H:i:s'));
            $this->audit->record('PASSWORD_CHANGED', $adminId, null, null, 'Administrator changed their password', $ip, $userAgent);
        });

        $this->sessions->refreshAuthentication(); // this session continues; every other one ends
    }

    /**
     * Validates the current session on every protected request: timeout, then
     * the account's current state in the database (so disabling an admin takes
     * effect immediately), then the role's permission, then whether the password
     * changed after this session was created.
     *
     * @return array{status:string,admin:?array}
     */
    public function resolveSession(string $ip, string $userAgent, string $permission = AuthorizationService::PERMISSION_ADMIN_ACCESS): array
    {
        $session = $this->sessions->current();
        if ($session === null) {
            return ['status' => self::SESSION_NONE, 'admin' => null];
        }

        if ($this->sessions->isExpired($session)) {
            $this->audit->record('SESSION_TIMEOUT', $session['admin_id'], null, null, 'Session expired after inactivity', $ip, $userAgent);
            $this->sessions->destroy();
            return ['status' => self::SESSION_EXPIRED, 'admin' => null];
        }

        $row = $this->users->findSummaryById($session['admin_id']);
        if ($row === null || $row['status'] !== 'active' || !AuthorizationService::can((string) $row['role'], $permission)) {
            $this->sessions->destroy();
            return ['status' => self::SESSION_INVALID, 'admin' => null];
        }

        // A password reset/change ends every session created before it, INCLUDING one created in the
        // same second (<=). The administrator's own session after a self-service change is stamped
        // one second later (SessionService::refreshAuthentication) so it is not caught by this rule.
        if ($row['password_changed_ts'] !== null && $session['authenticated_at'] <= (int) $row['password_changed_ts']) {
            $this->sessions->destroy();
            return ['status' => self::SESSION_INVALID, 'admin' => null];
        }

        $this->sessions->touch((string) $row['role']);

        return ['status' => self::SESSION_OK, 'admin' => [
            'id' => (int) $row['id'], 'name' => (string) $row['name'], 'role' => (string) $row['role'],
            'two_factor_enabled' => (int) $row['two_factor_enabled'] === 1,
        ]];
    }

    public function logout(?int $adminId, string $ip, string $userAgent): void
    {
        if ($adminId !== null) {
            $this->audit->record('LOGOUT', $adminId, null, null, 'Administrator signed out', $ip, $userAgent);
        }
        $this->sessions->destroy();
    }

    /** Security event with no credentials involved (for example a rejected CSRF token). */
    public function logSecurityEvent(string $eventType, ?int $adminId, string $description, string $ip, string $userAgent): void
    {
        $this->audit->record($eventType, $adminId, null, null, $description, $ip, $userAgent);
    }

    /** Last step of a successful sign-in: database writes first (atomically), then the session. */
    private function completeLogin(array $admin, int $identifier, string $ip, string $userAgent, string $description, bool $recoveryCodeUsed = false): void
    {
        // Own the transaction unless a caller already opened one (then it decides when to commit).
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $this->users->recordLogin($admin['id']);
            $this->audit->record('LOGIN_SUCCESS', $admin['id'], AuditLogRepository::IDENTIFIER_TYPE, $identifier, $description, $ip, $userAgent);
            if ($recoveryCodeUsed) {
                $remaining = $this->twoFactor()->remainingRecoveryCodes($admin['id']);
                $this->audit->record('RECOVERY_CODE_USED', $admin['id'], null, null, "Recovery code used to sign in ({$remaining} remaining)", $ip, $userAgent);
            }
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $this->sessions->establish($admin);
    }

    private function isThrottled(string $ip, int $identifier): bool
    {
        $counts = $this->audit->failureCounts($ip, $identifier, AppConfig::LOGIN_WINDOW_MINUTES, $this->audit->lastSuccessId($identifier));

        return $counts['ip'] >= AppConfig::LOGIN_MAX_FAILURES_PER_IP
            || $counts['ipAccount'] >= AppConfig::LOGIN_MAX_FAILURES_PER_IP_ACCOUNT
            || $counts['account'] >= AppConfig::LOGIN_MAX_FAILURES_PER_ACCOUNT;
    }

    /** Second-factor / re-authentication failures for this admin (since their last sign-in) or from this IP. */
    private function secondFactorThrottled(int $adminId, string $ip): bool
    {
        $window = AppConfig::LOGIN_WINDOW_MINUTES;
        $afterId = $this->audit->lastEventIdForAdmin('LOGIN_SUCCESS', $adminId);

        return $this->audit->countRecent('admin', $adminId, AuditLogRepository::SECOND_FACTOR_FAILURES, $window, $afterId) >= AppConfig::SECOND_FACTOR_MAX_FAILURES_PER_ADMIN
            || $this->audit->countRecent('ip', $ip, AuditLogRepository::SECOND_FACTOR_FAILURES, $window) >= AppConfig::SECOND_FACTOR_MAX_FAILURES_PER_IP;
    }

    private function logRateLimitOnce(string $ip, ?int $identifier, string $userAgent, string $description, ?int $adminId = null): void
    {
        if (!$this->audit->recentEventExists('RATE_LIMIT_TRIGGERED', $ip, AppConfig::LOGIN_WINDOW_MINUTES)) {
            $this->audit->record('RATE_LIMIT_TRIGGERED', $adminId, $identifier === null ? null : AuditLogRepository::IDENTIFIER_TYPE,
                $identifier, $description, $ip, $userAgent);
        }
    }
}
