<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

/**
 * Server-side PHP session handling for the admin area.
 *
 * Authentication state exists only in the server-side session; the browser
 * holds just an opaque, HttpOnly session id cookie. The session stores the
 * minimum: admin id, role, authentication time, last activity and the CSRF
 * token. Never passwords, hashes, or any secret.
 */
class SessionService
{
    public const COOKIE_NAME = 'vip_admin_session';

    /** Cookie attributes for the current request (HttpOnly always; Secure per AppConfig; SameSite=Lax). */
    public static function cookieParams(): array
    {
        return [
            'lifetime' => 0, // browser-session cookie; inactivity is enforced server-side
            'path'     => '/',
            'secure'   => AppConfig::cookieSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');   // never accept a session id the server did not issue
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        // The garbage collector must not delete a session before our own timeout would.
        $needed = AppConfig::sessionTimeoutSeconds() + 60;
        if ((int) ini_get('session.gc_maxlifetime') < $needed) {
            ini_set('session.gc_maxlifetime', (string) $needed);
        }

        session_name(self::COOKIE_NAME);
        session_set_cookie_params(self::cookieParams());
        session_start();
    }

    /** New authenticated session for this administrator. Regenerates the id (session fixation defence). */
    public function establish(array $admin): void
    {
        $this->start();
        session_regenerate_id(true);

        $now = time();
        $_SESSION = [
            'admin_id'         => (int) $admin['id'],
            'role'             => (string) $admin['role'],
            'authenticated_at' => $now,
            'last_activity'    => $now,
            'csrf_token'       => bin2hex(random_bytes(32)), // fresh token for the new session
        ];
    }

    /**
     * "Password verified, second factor still required". This is NOT an authenticated
     * session: it contains no admin_id, so the guard treats the visitor as signed out.
     * It expires quickly and the session id is regenerated when it starts.
     */
    public function beginTwoFactor(int $adminId): void
    {
        $this->start();
        session_regenerate_id(true);
        $_SESSION = [
            'pending_2fa'  => ['admin_id' => $adminId, 'started' => time()],
            'csrf_token'   => bin2hex(random_bytes(32)),
        ];
    }

    /** @return array{admin_id:int,started:int}|null the pending second-factor state, if present and fresh */
    public function pendingTwoFactor(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !isset($_COOKIE[self::COOKIE_NAME])) {
            return null;
        }
        $this->start();

        $pending = $_SESSION['pending_2fa'] ?? null;
        if (!is_array($pending) || !isset($pending['admin_id'], $pending['started'])) {
            return null;
        }
        if (time() - (int) $pending['started'] > AppConfig::TWO_FACTOR_PENDING_SECONDS) {
            unset($_SESSION['pending_2fa']);
            return null;
        }

        return ['admin_id' => (int) $pending['admin_id'], 'started' => (int) $pending['started']];
    }

    public function clearPendingTwoFactor(): void
    {
        $this->start();
        unset($_SESSION['pending_2fa']);
    }

    /** Unconfirmed authenticator enrollment: the secret is held ENCRYPTED, server-side only. */
    public function beginEnrollment(string $encryptedSecret): void
    {
        $this->start();
        $_SESSION['enrollment'] = ['secret' => $encryptedSecret, 'started' => time()];
    }

    /** @return array{secret:string}|null */
    public function enrollment(): ?array
    {
        $this->start();
        $e = $_SESSION['enrollment'] ?? null;
        if (!is_array($e) || !isset($e['secret'], $e['started'])) {
            return null;
        }
        if (time() - (int) $e['started'] > AppConfig::TWO_FACTOR_ENROLLMENT_SECONDS) {
            unset($_SESSION['enrollment']);
            return null;
        }

        return ['secret' => (string) $e['secret']];
    }

    public function clearEnrollment(): void
    {
        $this->start();
        unset($_SESSION['enrollment']);
    }

    /** Binds a validated reset link (by token id) to this browser session, so the token can leave the URL. */
    public function bindPasswordReset(int $tokenId): void
    {
        $this->start();
        $_SESSION['password_reset'] = ['token_id' => $tokenId, 'started' => time()];
    }

    public function boundPasswordReset(): ?int
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !isset($_COOKIE[self::COOKIE_NAME])) {
            return null;
        }
        $this->start();
        $r = $_SESSION['password_reset'] ?? null;
        if (!is_array($r) || !isset($r['token_id'], $r['started'])
            || time() - (int) $r['started'] > AppConfig::passwordResetMinutes() * 60) {
            unset($_SESSION['password_reset']);
            return null;
        }

        return (int) $r['token_id'];
    }

    public function clearPasswordReset(): void
    {
        $this->start();
        unset($_SESSION['password_reset']);
    }

    /**
     * After an administrator changes their own password: keep THIS session valid
     * (every other session is ended by password_changed_at) and rotate its id.
     */
    public function refreshAuthentication(): void
    {
        $this->start();
        session_regenerate_id(true);
        // One second ahead of the password_changed_at just written (same second), so this
        // session survives the "authenticated before the change" rule while all others end.
        $_SESSION['authenticated_at'] = time() + 1;
        $_SESSION['last_activity'] = time();
    }

    /** @return array{admin_id:int,role:string,authenticated_at:int,last_activity:int}|null */
    public function current(): ?array
    {
        // A visitor without a session cookie is not signed in: do not create a
        // server-side session just because someone requested a protected URL.
        if (session_status() !== PHP_SESSION_ACTIVE && !isset($_COOKIE[self::COOKIE_NAME])) {
            return null;
        }

        $this->start();
        if (!isset($_SESSION['admin_id'], $_SESSION['last_activity'], $_SESSION['authenticated_at'])) {
            return null;
        }

        return [
            'admin_id'         => (int) $_SESSION['admin_id'],
            'role'             => (string) ($_SESSION['role'] ?? ''),
            'authenticated_at' => (int) $_SESSION['authenticated_at'],
            'last_activity'    => (int) $_SESSION['last_activity'],
        ];
    }

    public function isExpired(array $session, ?int $now = null): bool
    {
        return (($now ?? time()) - $session['last_activity']) > AppConfig::sessionTimeoutSeconds();
    }

    public function touch(string $role): void
    {
        $_SESSION['last_activity'] = time();
        $_SESSION['role'] = $role;
    }

    /** Clears state, destroys the session and expires the cookie. */
    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];

        if (!headers_sent()) {
            $params = self::cookieParams();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'secure'   => $params['secure'],
                'httponly' => true,
                'samesite' => $params['samesite'],
            ]);
        }

        session_destroy();
    }
}
