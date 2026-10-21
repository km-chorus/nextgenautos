<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/PasswordResetService.php';
require_once dirname(__DIR__) . '/services/PasswordPolicy.php';
require_once dirname(__DIR__) . '/helpers/csrf.php';
require_once dirname(__DIR__) . '/helpers/auth.php';

/**
 * Forgot-password and reset-password request handling. Returns view data.
 * User-facing wording is fixed here so nothing about accounts, tokens or
 * internals can leak.
 */
final class PasswordResetController
{
    public const MESSAGE_REQUESTED = 'If an account exists for that email, a password reset link will be sent.';
    public const MESSAGE_UNAVAILABLE = 'Password reset by email is not available on this server because email delivery has not been configured. Contact the person who manages the website.';
    public const MESSAGE_LINK_INVALID = 'This password reset link is invalid or has expired.';
    public const MESSAGE_THROTTLED = 'Too many attempts. Wait a few minutes and try again.';
    public const MESSAGE_CSRF = "We couldn't verify your request. Reload the page and try again.";
    public const MESSAGE_ERROR = 'Something went wrong. Please try again.';

    public function __construct(private ?PasswordResetService $service = null)
    {
    }

    /**
     * Forgot-password POST. The outcome is the same fixed message whether or not the
     * email has an account, and the request always takes at least RESET_MIN_RESPONSE_MS
     * so the response time does not reveal it either.
     *
     * @param array<string,mixed> $post
     * @return array{status:string,http:int,message:?string,email:string,fieldError:?string}
     */
    public function submitForgot(array $post, string $ip, string $userAgent): array
    {
        $email = is_string($post['email'] ?? null) ? trim($post['email']) : '';
        $view = fn (string $status, int $http, ?string $message, ?string $fieldError = null): array => [
            'status' => $status, 'http' => $http, 'message' => $message, 'email' => substr($email, 0, 255), 'fieldError' => $fieldError,
        ];

        if (!PasswordResetService::isAvailable()) {
            return $view('unavailable', 503, self::MESSAGE_UNAVAILABLE);
        }
        if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
            return $view('csrf', 400, self::MESSAGE_CSRF);
        }
        if ($email === '') {
            return $view('invalid_input', 200, null, 'Enter your email address.');
        }

        $started = hrtime(true);
        $status = 'requested';
        try {
            ($this->service ?? PasswordResetService::create())->requestReset($email, $ip, $userAgent);
        } catch (Throwable $e) {
            error_log('Password reset request failed: ' . get_class($e) . ': ' . $e->getMessage());
            $status = 'error';
        }

        $this->padResponseTime($started);

        return $status === 'error'
            ? $view('error', 500, self::MESSAGE_ERROR)
            : $view('requested', 200, self::MESSAGE_REQUESTED);
    }

    /**
     * Reset link opened (GET ?token=...). A valid link is bound to the session and the
     * visitor is sent to the same page WITHOUT the token, so it does not linger in the
     * address bar, history or Referer headers.
     *
     * @return array{status:string,http:int,message:?string}  status: bound | invalid | throttled | error
     */
    public function openLink(string $token, string $ip, string $userAgent): array
    {
        try {
            $service = $this->service ?? PasswordResetService::create();
            if ($service->tokenAttemptsThrottled($ip)) {
                return ['status' => 'throttled', 'http' => 429, 'message' => self::MESSAGE_THROTTLED];
            }

            $tokenId = $service->validateToken($token, $ip, $userAgent);
            if ($tokenId === null) {
                return ['status' => 'invalid', 'http' => 200, 'message' => self::MESSAGE_LINK_INVALID];
            }

            $sessions = new SessionService();
            $sessions->start();
            $sessions->bindPasswordReset($tokenId);
        } catch (Throwable $e) {
            error_log('Reset link failed to process: ' . get_class($e) . ': ' . $e->getMessage());
            return ['status' => 'error', 'http' => 500, 'message' => self::MESSAGE_ERROR];
        }

        return ['status' => 'bound', 'http' => 303, 'message' => null];
    }

    /** Is a validated link bound to this session (so the new-password form may be shown)? */
    public function hasBoundLink(): bool
    {
        try {
            return (new SessionService())->boundPasswordReset() !== null;
        } catch (Throwable $e) {
            error_log('Reset page: could not read the session: ' . get_class($e) . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * New-password POST.
     *
     * @param array<string,mixed> $post
     * @return array{status:string,http:int,message:?string,fieldErrors:array<string,string>}
     *   status: done | invalid_link | invalid_input | csrf | throttled | error
     */
    public function submitReset(array $post, string $ip, string $userAgent): array
    {
        $view = fn (string $status, int $http, ?string $message, array $fieldErrors = []): array => [
            'status' => $status, 'http' => $http, 'message' => $message, 'fieldErrors' => $fieldErrors,
        ];

        if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
            return $view('csrf', 400, self::MESSAGE_CSRF);
        }

        try {
            $sessions = new SessionService();
            $tokenId = $sessions->boundPasswordReset();
            if ($tokenId === null) {
                return $view('invalid_link', 200, self::MESSAGE_LINK_INVALID);
            }

            $password = is_string($post['new_password'] ?? null) ? $post['new_password'] : '';
            $confirm = is_string($post['confirm_password'] ?? null) ? $post['confirm_password'] : '';
            $problems = PasswordPolicy::validate($password);
            $fieldErrors = [];
            if ($problems !== []) {
                $fieldErrors['new_password'] = $problems[0];
            } elseif (!hash_equals($password, $confirm)) {
                $fieldErrors['confirm_password'] = 'The two passwords do not match.';
            }
            if ($fieldErrors !== []) {
                return $view('invalid_input', 200, null, $fieldErrors);
            }

            $service = $this->service ?? PasswordResetService::create();
            if (!$service->complete($tokenId, $password, $ip, $userAgent)) {
                $sessions->clearPasswordReset();
                return $view('invalid_link', 200, self::MESSAGE_LINK_INVALID);
            }

            $sessions->destroy(); // nothing from before the reset survives in this browser
        } catch (Throwable $e) {
            error_log('Password reset failed to process: ' . get_class($e) . ': ' . $e->getMessage());
            return $view('error', 500, self::MESSAGE_ERROR);
        }

        return $view('done', 303, null);
    }

    /** Sleeps until at least RESET_MIN_RESPONSE_MS have passed since $startedNs (hrtime). */
    private function padResponseTime(int $startedNs): void
    {
        $remainingUs = AppConfig::RESET_MIN_RESPONSE_MS * 1000 - (int) ((hrtime(true) - $startedNs) / 1000);
        if ($remainingUs > 0) {
            usleep($remainingUs);
        }
    }
}
