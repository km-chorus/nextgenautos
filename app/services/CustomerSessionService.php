<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

/**
 * Server-side session for CUSTOMERS (spec 13_spec_customer_auth.md).
 *
 * Completely separate from the administrator session: its own cookie name, its own session data and its own CSRF
 * token. A customer session holds only `customer_id` and timestamps, so it can never satisfy the admin guard
 * (which looks for `admin_id` in a differently named session), and an admin session never signs anyone in here.
 */
class CustomerSessionService
{
    public const COOKIE_NAME = 'vip_customer_session';
    public const CSRF_FIELD = 'csrf_token';

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        $needed = AppConfig::sessionTimeoutSeconds() + 60;
        if ((int) ini_get('session.gc_maxlifetime') < $needed) {
            ini_set('session.gc_maxlifetime', (string) $needed);
        }

        session_name(self::COOKIE_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => AppConfig::cookieSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /**
     * New signed-in session. Regenerates the id (session fixation defence) and issues a fresh CSRF token. $welcome is
     * shown once as a toast on the next page (owner request 2026-10-22): 'back' after a sign-in, 'new' for a new account.
     */
    public function establish(int $customerId, string $welcome = 'back'): void
    {
        $this->start();
        session_regenerate_id(true);
        $now = time();
        $returnTo = $_SESSION['return_to'] ?? null;   // survives the new session: it is how /track/ gets the customer back after sign-in
        $_SESSION = [
            'return_to'        => is_string($returnTo) ? $returnTo : null,
            'customer_id'      => $customerId,
            'authenticated_at' => $now,
            'last_activity'    => $now,
            'csrf_token'       => bin2hex(random_bytes(32)),
            'welcome'          => in_array($welcome, ['back', 'new'], true) ? $welcome : 'back',
        ];
    }

    /** The address typed on "Forgot your password?", so the code form can fill it in (null clears it). */
    public function rememberResetEmail(?string $email): void
    {
        $this->start();
        if ($email === null) {
            unset($_SESSION['reset_email']);
        } else {
            $_SESSION['reset_email'] = substr($email, 0, 255);
        }
    }

    public function resetEmail(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !isset($_COOKIE[self::COOKIE_NAME])) {
            return null;
        }
        $this->start();

        return is_string($_SESSION['reset_email'] ?? null) ? $_SESSION['reset_email'] : null;
    }

    /** When this session signed in (Unix time), or null. Compared with customers.password_changed_at. */
    public function authenticatedAt(): ?int
    {
        return session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['authenticated_at']) ? (int) $_SESSION['authenticated_at'] : null;
    }

    /** The welcome to show once after signing in ('back' | 'new'), or null; it is removed when read. */
    public function consumeWelcome(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !isset($_COOKIE[self::COOKIE_NAME])) {
            return null;
        }
        $this->start();
        $welcome = $_SESSION['welcome'] ?? null;
        unset($_SESSION['welcome']);

        return in_array($welcome, ['back', 'new'], true) ? $welcome : null;
    }

    /** @return int|null the signed-in customer's id, or null (no cookie, no session, or inactivity timeout; an expired session is destroyed) */
    public function currentCustomerId(): ?int
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !isset($_COOKIE[self::COOKIE_NAME])) {
            return null;   // do not create a session for a visitor who has none
        }
        $this->start();
        if (!isset($_SESSION['customer_id'], $_SESSION['last_activity'])) {
            return null;
        }
        if ((time() - (int) $_SESSION['last_activity']) > AppConfig::sessionTimeoutSeconds()) {
            $this->destroy();

            return null;
        }
        $_SESSION['last_activity'] = time();

        return (int) $_SESSION['customer_id'];
    }

    /** Pages a signed-out visitor may be sent back to after signing in. A fixed list: the value never comes from the request. */
    public const RETURN_TARGETS = ['/', '/track/', '/account/', '/account/settings/', '/vehicles/'];

    /**
     * Where a customer may be sent back to after signing in: the fixed pages above, or one vehicle page (spec 22: a guest
     * who presses "Save" on a vehicle returns to it). Strict pattern: never another site, never anything else.
     */
    public static function isReturnTarget(string $path): bool
    {
        return in_array($path, self::RETURN_TARGETS, true) || preg_match('#^/vehicle/[a-z0-9]+(?:-[a-z0-9]+)*/$#D', $path) === 1 && strlen($path) <= 180;
    }

    public function setReturnTo(string $path): void
    {
        if (self::isReturnTarget($path)) {
            $this->start();
            $_SESSION['return_to'] = $path;
        }
    }

    /** @return string|null the remembered page (once), or null */
    public function consumeReturnTo(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !isset($_COOKIE[self::COOKIE_NAME])) {
            return null;
        }
        $this->start();
        $path = $_SESSION['return_to'] ?? null;
        unset($_SESSION['return_to']);

        return is_string($path) && self::isReturnTarget($path) ? $path : null;
    }

    /** Remembers the OAuth state and nonce of a Google sign-in that is in progress (one use, short-lived). */
    public function beginGoogle(string $state, string $nonce): void
    {
        $this->start();
        $_SESSION['google'] = ['state' => $state, 'nonce' => $nonce, 'started' => time()];
    }

    /** @return string|null the nonce when $state matches the one issued less than 10 minutes ago; the pending state is always consumed */
    public function consumeGoogle(string $state): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !isset($_COOKIE[self::COOKIE_NAME])) {
            return null;
        }
        $this->start();
        $pending = $_SESSION['google'] ?? null;
        unset($_SESSION['google']);

        if (!is_array($pending) || !isset($pending['state'], $pending['nonce'], $pending['started'])
            || time() - (int) $pending['started'] > 600 || $state === '' || !hash_equals((string) $pending['state'], $state)) {
            return null;
        }

        return (string) $pending['nonce'];
    }

    public function csrfToken(): string
    {
        $this->start();
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public function verifyCsrf(mixed $submitted): bool
    {
        if (!is_string($submitted) || $submitted === '') {
            return false;
        }
        $this->start();
        $expected = $_SESSION['csrf_token'] ?? '';

        return is_string($expected) && $expected !== '' && hash_equals($expected, $submitted);
    }

    /** Clears state, destroys the session and expires the cookie. */
    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];
        if (!headers_sent()) {
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => '/',
                'secure'   => AppConfig::cookieSecure(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
    }
}
