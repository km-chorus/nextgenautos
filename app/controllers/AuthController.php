<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/AuthService.php';
require_once dirname(__DIR__) . '/helpers/csrf.php';
require_once dirname(__DIR__) . '/helpers/auth.php';

/**
 * Login/logout request handling. Returns view data; the pages render it.
 *
 * HTTP status of the re-rendered form: an ordinary wrong password or empty field is
 * a normal user flow and answers 200 (a 4xx page makes browsers print a console
 * error for every mistyped password). Abnormal cases keep honest statuses: CSRF
 * failure 400, throttled 429 (+ Retry-After), internal failure 500.
 *
 * Request flow for login: method -> CSRF -> input validation -> AuthService
 * (throttle, credentials, account status, session). Whatever goes wrong, the
 * user only ever sees one of four fixed messages below.
 */
final class AuthController
{
    public const MESSAGE_INVALID = 'Invalid email or password.';
    public const MESSAGE_THROTTLED = 'Too many sign-in attempts. Wait a few minutes and try again.';
    public const MESSAGE_CSRF = "We couldn't verify your request. Reload the page and try again.";
    public const MESSAGE_ERROR = 'Something went wrong. Please try again.';
    public const MESSAGE_CODE_INVALID = 'Invalid verification code.';

    public function __construct(private ?AuthService $auth = null)
    {
    }

    /**
     * GET: prepare the login form (starts the session that holds the CSRF token).
     *
     * @param array<string,mixed> $query
     * @return array{redirect:bool,notice:?array{type:string,title:string}}
     */
    public function show(array $query, string $ip, string $userAgent): array
    {
        $notice = null;

        try {
            $auth = $this->auth ?? AuthService::create();
            $state = $auth->resolveSession($ip, $userAgent);
            if ($state['status'] === AuthService::SESSION_OK) {
                return ['redirect' => true, 'notice' => null];
            }
        } catch (Throwable $e) {
            error_log('Login page: could not check the session: ' . get_class($e) . ': ' . $e->getMessage());
        }

        // Fixed allow-list: the query value is never echoed.
        $reason = $query['reason'] ?? null;
        if ($reason === 'timeout') {
            $notice = ['type' => 'info', 'title' => 'Your session expired after a period of inactivity. Sign in again to continue.'];
        } elseif ($reason === 'signedout') {
            $notice = ['type' => 'success', 'title' => 'You have been signed out.'];
        } elseif ($reason === 'reset') {
            $notice = ['type' => 'success', 'title' => 'Your password has been changed. Sign in with your new password.'];
        } elseif ($reason === 'twofactor') {
            $notice = ['type' => 'info', 'title' => 'Your sign-in timed out before the verification code was entered. Enter your email and password again.'];
        }

        csrf_token(); // make sure the session and token exist for the form

        return ['redirect' => false, 'notice' => $notice];
    }

    /**
     * POST: attempt to sign in.
     *
     * @param array<string,mixed> $post
     * @return array{status:string,http:int,message:?string,email:string,fieldErrors:array<string,string>}
     */
    public function submit(array $post, string $ip, string $userAgent): array
    {
        $email = is_string($post['email'] ?? null) ? trim($post['email']) : '';
        $password = is_string($post['password'] ?? null) ? $post['password'] : '';
        $view = fn (string $status, int $http, ?string $message, array $fieldErrors = []): array => [
            'status' => $status, 'http' => $http, 'message' => $message,
            'email' => substr($email, 0, 255), 'fieldErrors' => $fieldErrors,
        ];

        try {
            $auth = $this->auth ?? AuthService::create();

            if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                $auth->logSecurityEvent('CSRF_FAILURE', null, 'Sign-in request rejected: missing or invalid CSRF token', $ip, $userAgent);
                return $view('csrf', 400, self::MESSAGE_CSRF);
            }

            $fieldErrors = [];
            if ($email === '') {
                $fieldErrors['email'] = 'Enter your email address.';
            }
            if ($password === '') {
                $fieldErrors['password'] = 'Enter your password.';
            }
            if ($fieldErrors !== []) {
                return $view('invalid_input', 200, null, $fieldErrors);
            }

            $result = $auth->attempt($email, $password, $ip, $userAgent);
        } catch (Throwable $e) {
            error_log('Login failed to process: ' . get_class($e) . ': ' . $e->getMessage());
            return $view('error', 500, self::MESSAGE_ERROR);
        }

        return match ($result['status']) {
            AuthService::LOGIN_OK        => $view('success', 303, null),
            AuthService::LOGIN_TWO_FACTOR => $view('two_factor', 303, null),
            AuthService::LOGIN_THROTTLED => $view('throttled', 429, self::MESSAGE_THROTTLED),
            default                      => $view('invalid', 200, self::MESSAGE_INVALID),
        };
    }

    /**
     * Second step of sign-in: is a password-verified login waiting for a code?
     * (No pending state means the visitor must start again at the login page.)
     */
    public function twoFactorPending(): bool
    {
        try {
            return ($this->auth ?? AuthService::create())->sessions()->pendingTwoFactor() !== null;
        } catch (Throwable $e) {
            error_log('Two-factor page: could not read the session: ' . get_class($e) . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * POST: authenticator code or recovery code.
     *
     * @param array<string,mixed> $post
     * @return array{status:string,http:int,message:?string,mode:string}
     *   status: success | expired | invalid | throttled | csrf | error | invalid_input
     */
    public function submitTwoFactor(array $post, string $ip, string $userAgent): array
    {
        $mode = ($post['mode'] ?? '') === 'recovery' ? 'recovery' : 'code';
        $input = is_string($post[$mode === 'recovery' ? 'recovery_code' : 'code'] ?? null)
            ? trim($post[$mode === 'recovery' ? 'recovery_code' : 'code'])
            : '';
        $view = fn (string $status, int $http, ?string $message): array => [
            'status' => $status, 'http' => $http, 'message' => $message, 'mode' => $mode,
        ];

        try {
            $auth = $this->auth ?? AuthService::create();

            if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                $auth->logSecurityEvent('CSRF_FAILURE', null, 'Second-factor request rejected: missing or invalid CSRF token', $ip, $userAgent);
                return $view('csrf', 400, self::MESSAGE_CSRF);
            }
            if ($input === '' || strlen($input) > 64) {
                return $view('invalid_input', 200, self::MESSAGE_CODE_INVALID);
            }

            $result = $auth->verifySecondFactor($input, $mode === 'recovery', $ip, $userAgent);
        } catch (Throwable $e) {
            error_log('Second factor failed to process: ' . get_class($e) . ': ' . $e->getMessage());
            return $view('error', 500, self::MESSAGE_ERROR);
        }

        return match ($result['status']) {
            AuthService::LOGIN_OK                  => $view('success', 303, null),
            AuthService::SECOND_FACTOR_EXPIRED     => $view('expired', 303, null),
            AuthService::LOGIN_THROTTLED           => $view('throttled', 429, self::MESSAGE_THROTTLED),
            default                                => $view('invalid', 200, self::MESSAGE_CODE_INVALID),
        };
    }

    /**
     * POST /admin/logout. Returns the URL to redirect to.
     *
     * @param array<string,mixed> $post
     */
    public function logout(string $method, array $post, string $ip, string $userAgent): string
    {
        if ($method !== 'POST') {
            return 'dashboard.php'; // no state change on GET; the guard sends signed-out visitors to login
        }

        try {
            $auth = $this->auth ?? AuthService::create();
            $session = $auth->sessions()->current();

            if ($session === null) {
                return 'login.php';
            }
            if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                $auth->logSecurityEvent('CSRF_FAILURE', $session['admin_id'], 'Sign-out request rejected: missing or invalid CSRF token', $ip, $userAgent);
                return 'dashboard.php';
            }

            $auth->logout($session['admin_id'], $ip, $userAgent);
        } catch (Throwable $e) {
            error_log('Logout failed to log the event: ' . get_class($e) . ': ' . $e->getMessage());
            // Never leave the user signed in because logging failed.
            (new SessionService())->destroy();
        }

        return 'login.php?reason=signedout';
    }
}
