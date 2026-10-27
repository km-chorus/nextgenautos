<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/repositories/CustomerRepository.php';
require_once dirname(__DIR__) . '/repositories/AuditLogRepository.php';
require_once __DIR__ . '/CustomerSessionService.php';
require_once __DIR__ . '/GoogleOAuthService.php';

/**
 * Customer registration, sign-in, sign-out and session resolution (spec 13_spec_customer_auth.md).
 * Returns plain data (no markup). Customers and administrators never share a table, a session or a permission.
 *
 * Sign-in: throttle check -> find the customer -> exactly one password_verify (against a dummy hash when the
 * email is unknown, so timing does not reveal which emails exist) -> reject disabled accounts -> new session.
 * Every failure looks identical to the visitor; the audit log records the real reason. Throttling counts
 * audit_logs events (the same mechanism the administrator sign-in uses), keyed by IP and by a one-way
 * fingerprint of the email, so raw emails of failed attempts are never stored.
 */
final class CustomerAuthService
{
    public const LOGIN_OK = 'ok';
    public const LOGIN_INVALID = 'invalid';
    public const LOGIN_THROTTLED = 'throttled';

    public const REGISTER_OK = 'ok';
    public const REGISTER_EXISTS = 'exists';
    public const REGISTER_THROTTLED = 'throttled';

    public const WINDOW_MINUTES = 15;
    public const MAX_LOGIN_FAILURES_PER_IP = 10;
    public const MAX_LOGIN_FAILURES_PER_ACCOUNT = 3;   // owner request 2026-10-22: "try again later" after 3 wrong passwords
    public const REGISTER_WINDOW_MINUTES = 60;
    public const MAX_REGISTRATIONS_PER_IP = 5;

    private const MAX_PASSWORD_BYTES = 1024;

    /** Verified against when the email is unknown. A bcrypt (cost 12) hash of random data nobody knows: it cannot authenticate anything. */
    private const DUMMY_HASH = '$2y$12$bhbJ8p.TNRBTpyc2A7xaGe2WB/wSJk3VdVzysWH106sYqQ5E1IeFa';

    public function __construct(
        private CustomerRepository $customers,
        private AuditLogRepository $audit,
        private CustomerSessionService $sessions,
        private ?GoogleOAuthService $google = null
    ) {
    }

    public static function create(): self
    {
        $pdo = Database::connection();

        return new self(new CustomerRepository($pdo), new AuditLogRepository($pdo), new CustomerSessionService());
    }

    /**
     * Starts a Google sign-in: returns the Google address to send the browser to, or null when Google sign-in
     * is not configured or this IP has started too many.
     */
    public function googleStart(string $ip, string $userAgent): ?string
    {
        if (!AppConfig::googleConfigured() || $this->googleThrottled($ip)) {
            return null;
        }
        $this->audit->record('CUSTOMER_GOOGLE_START', null, null, null, 'Google sign-in started', $ip, $userAgent);

        $state = bin2hex(random_bytes(24));
        $nonce = bin2hex(random_bytes(24));
        $this->sessions->beginGoogle($state, $nonce);

        return ($this->google ??= new GoogleOAuthService())->authorizationUrl($state, $nonce);
    }

    /**
     * Finishes a Google sign-in. Finds the customer by Google id; otherwise links the Google account to the customer
     * with the same (Google-verified) email; otherwise creates the customer. Then signs them in.
     *
     * @return bool true when the customer is now signed in
     * @throws Throwable on infrastructure failure
     */
    public function googleFinish(string $state, string $code, string $ip, string $userAgent): bool
    {
        $nonce = $this->sessions->consumeGoogle($state);
        if ($nonce === null || $code === '' || strlen($code) > 2048 || !AppConfig::googleConfigured()) {
            return false;
        }

        $identity = ($this->google ??= new GoogleOAuthService())->identityFromCode($code, $nonce);
        if ($identity === null) {
            $this->audit->record('CUSTOMER_GOOGLE_FAILURE', null, null, null, 'Google sign-in could not be completed', $ip, $userAgent);

            return false;
        }

        $customer = $this->customers->findByGoogleId($identity['sub']);
        if ($customer === null) {
            $customer = $this->customers->findByEmail($identity['email']);
            if ($customer !== null) {
                if ($customer['google_id'] !== null && $customer['google_id'] !== $identity['sub']) {
                    return false;   // the email belongs to a customer linked to a DIFFERENT Google account: never take it over
                }
                $this->customers->linkGoogle((int) $customer['id'], $identity['sub']);
            } else {
                $id = $this->customers->createFromGoogle($identity['name'], $identity['email'], $identity['sub']);
                $created = $id !== null;
                $customer = $id === null ? null : $this->customers->findById($id);
                if ($customer !== null) {
                    $this->audit->record('CUSTOMER_REGISTERED', null, 'customer', $id, 'Customer account created with Google', $ip, $userAgent);
                }
            }
        }

        if ($customer === null || $customer['status'] !== 'active') {
            $this->audit->record('CUSTOMER_GOOGLE_FAILURE', null, null, null, 'Google sign-in refused (no usable account)', $ip, $userAgent);

            return false;
        }

        $this->customers->recordLogin((int) $customer['id']);
        $this->audit->record('CUSTOMER_LOGIN_SUCCESS', null, 'customer', (int) $customer['id'], 'Customer signed in with Google', $ip, $userAgent);
        $this->sessions->establish((int) $customer['id'], !empty($created) ? 'new' : 'back');
        try {
            require_once __DIR__ . '/CustomerVerificationService.php';
            CustomerVerificationService::create()->linkWaitingShipments((int) $customer['id']);   // Google verified this email
        } catch (Throwable $e) {
            error_log('Customer shipments could not be linked after Google sign-in: ' . get_class($e));
        }

        return true;
    }

    public function sessions(): CustomerSessionService
    {
        return $this->sessions;
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    /** One-way 60-bit fingerprint of a normalized email (a different namespace from the administrator one). */
    public static function identifier(string $normalizedEmail): int
    {
        return (int) hexdec(substr(hash('sha256', 'customer-login-identifier:' . $normalizedEmail), 0, 15));
    }

    /**
     * @return array{status:string}
     * @throws Throwable on infrastructure failure (the caller treats it as "not signed in")
     */
    public function attempt(string $rawEmail, string $password, string $ip, string $userAgent): array
    {
        $email = self::normalizeEmail($rawEmail);
        $fingerprint = self::identifier(substr($email, 0, 255));

        if ($this->loginThrottled($ip, $fingerprint)) {
            if (!$this->audit->recentEventExists('CUSTOMER_RATE_LIMIT', $ip, self::WINDOW_MINUTES)) {
                $this->audit->record('CUSTOMER_RATE_LIMIT', null, AuditLogRepository::IDENTIFIER_TYPE, $fingerprint, 'Customer sign-in rate limit reached', $ip, $userAgent);
            }

            return ['status' => self::LOGIN_THROTTLED];
        }

        $wellFormed = strlen($email) <= 255 && strlen($password) <= self::MAX_PASSWORD_BYTES
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        $customer = $wellFormed ? $this->customers->findByEmail($email) : null;

        $verified = password_verify($wellFormed ? $password : '', $customer['password_hash'] ?? self::DUMMY_HASH);

        if ($customer === null || !$verified || $customer['status'] !== 'active') {
            $this->audit->record('CUSTOMER_LOGIN_FAILURE', null, AuditLogRepository::IDENTIFIER_TYPE, $fingerprint,
                $customer === null ? 'Failed customer sign-in (no matching account)'
                    : ($customer['status'] !== 'active' ? 'Customer sign-in on a disabled account' : 'Failed customer sign-in (wrong password)'),
                $ip, $userAgent);

            return ['status' => self::LOGIN_INVALID];
        }

        if (password_needs_rehash($customer['password_hash'], PASSWORD_DEFAULT)) {
            $this->customers->upgradePasswordHash((int) $customer['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        $this->customers->recordLogin((int) $customer['id']);
        $this->audit->record('CUSTOMER_LOGIN_SUCCESS', null, AuditLogRepository::IDENTIFIER_TYPE, $fingerprint, 'Customer signed in', $ip, $userAgent);
        $this->sessions->establish((int) $customer['id']);

        return ['status' => self::LOGIN_OK];
    }

    /**
     * Creates the account and signs the customer in. The password policy is checked by CustomerValidator before this.
     *
     * @return array{status:string}
     * @throws Throwable on infrastructure failure
     */
    public function register(string $name, string $email, string $password, string $ip, string $userAgent): array
    {
        $email = self::normalizeEmail($email);
        $fingerprint = self::identifier($email);

        // Every submitted registration counts (success or not), which also limits probing for existing emails.
        if ($this->audit->countRecent('ip', $ip, ['CUSTOMER_REGISTER_ATTEMPT'], self::REGISTER_WINDOW_MINUTES) >= self::MAX_REGISTRATIONS_PER_IP) {
            return ['status' => self::REGISTER_THROTTLED];
        }
        $this->audit->record('CUSTOMER_REGISTER_ATTEMPT', null, AuditLogRepository::IDENTIFIER_TYPE, $fingerprint, 'Customer registration submitted', $ip, $userAgent);

        $id = $this->customers->create($name, $email, password_hash($password, PASSWORD_DEFAULT));
        if ($id === null) {
            return ['status' => self::REGISTER_EXISTS];
        }

        $this->audit->record('CUSTOMER_REGISTERED', null, 'customer', $id, 'Customer account created', $ip, $userAgent);
        $this->sessions->establish($id, 'new');

        return ['status' => self::REGISTER_OK];
    }

    /**
     * The signed-in customer, or null. A session whose account no longer exists or was disabled is destroyed.
     *
     * @return array{id:int,name:string,email:string,verified:bool}|null
     * @throws Throwable on infrastructure failure
     */
    public function currentCustomer(): ?array
    {
        $id = $this->sessions->currentCustomerId();
        if ($id === null) {
            return null;
        }
        $customer = $this->customers->findById($id);
        // A session that signed in before the password was changed or reset ends here (2026-10-22): a reset locks out
        // whoever else was signed in to the account.
        $changed = ($customer['password_changed_at'] ?? null) !== null
            ? (DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $customer['password_changed_at'], new DateTimeZone('UTC')) ?: null)?->getTimestamp() : null;
        $signedIn = $this->sessions->authenticatedAt();
        if ($customer === null || $customer['status'] !== 'active' || ($changed !== null && $signedIn !== null && $signedIn + 2 < $changed)) {
            $this->sessions->destroy();

            return null;
        }

        return ['id' => (int) $customer['id'], 'name' => (string) $customer['name'], 'email' => (string) $customer['email'],
            'verified' => ($customer['email_verified_at'] ?? null) !== null];
    }

    public function logout(int $customerId, string $ip, string $userAgent): void
    {
        $this->audit->record('CUSTOMER_LOGOUT', null, 'customer', $customerId, 'Customer signed out', $ip, $userAgent);
        $this->sessions->destroy();
    }

    public const MAX_GOOGLE_STARTS_PER_IP = 10;

    /** Too many Google sign-ins started from this IP in 15 minutes (2026-10-22: every sign-in route is rate limited). */
    public function googleThrottled(string $ip): bool
    {
        return $this->audit->countRecent('ip', $ip, ['CUSTOMER_GOOGLE_START'], self::WINDOW_MINUTES) >= self::MAX_GOOGLE_STARTS_PER_IP;
    }

    private function loginThrottled(string $ip, int $fingerprint): bool
    {
        $events = ['CUSTOMER_LOGIN_FAILURE'];

        return $this->audit->countRecent('ip', $ip, $events, self::WINDOW_MINUTES) >= self::MAX_LOGIN_FAILURES_PER_IP
            || $this->audit->countRecent('identifier', $fingerprint, $events, self::WINDOW_MINUTES) >= self::MAX_LOGIN_FAILURES_PER_ACCOUNT;
    }
}
