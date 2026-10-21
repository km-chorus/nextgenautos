<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/CustomerAuthService.php';
require_once dirname(__DIR__) . '/validation/CustomerValidator.php';

/**
 * Customer sign-in, registration and account pages (spec 13_spec_customer_auth.md). Returns view data; the
 * public views render it and PublicController sends it.
 *
 * Every method returns an array with:
 *   redirect  string|null  a fixed, internal path to redirect to (303 after a POST); null = render the page
 *   http      int          status of the rendered page (a wrong password is an ordinary 200; CSRF 400; throttled 429)
 *   message   string|null  an error for the top of the form
 *   notice    array|null   ['type','text'] for the top of the form
 *   values    array        what to put back in the form (never a password)
 *   errors    array        field => message
 *   csrf      string       the CSRF token for the form
 *
 * Customers can sign in either with Google (when configured) or with email and password (owner decision 2026-10-11,
 * which replaced the earlier "Google only" decision).
 *
 * Request flow: method -> CSRF -> validation -> CustomerAuthService. The visitor only ever sees the fixed messages
 * below; the technical reason goes to the server log and the audit log. There is no "return to" parameter, so a
 * sign-in can never be turned into an open redirect.
 */
final class CustomerAuthController
{
    public const MESSAGE_INVALID = 'Invalid email or password.';
    public const MESSAGE_THROTTLED = 'Too many attempts. Wait a few minutes and try again.';
    public const MESSAGE_CSRF = "We couldn't verify your request. Reload the page and try again.";
    public const MESSAGE_ERROR = 'Something went wrong. Please try again.';
    public const MESSAGE_EXISTS = 'An account with this email address already exists. Sign in instead.';

    public function __construct(private ?CustomerAuthService $auth = null)
    {
    }

    private function auth(): CustomerAuthService
    {
        return $this->auth ??= CustomerAuthService::create();
    }

    /**
     * @param array<string,mixed> $post
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public function login(string $method, array $post, array $query, string $ip, string $userAgent): array
    {
        $view = self::view();

        try {
            $auth = $this->auth();
            if ($auth->currentCustomer() !== null) {
                return ['redirect' => $auth->sessions()->consumeReturnTo() ?? '/account/'] + $view;
            }

            if ($method === 'POST') {
                $email = is_string($post['email'] ?? null) ? trim($post['email']) : '';
                $password = is_string($post['password'] ?? null) ? $post['password'] : '';
                $view['values'] = ['email' => substr($email, 0, 255)];

                if (!$auth->sessions()->verifyCsrf($post[CustomerSessionService::CSRF_FIELD] ?? null)) {
                    $view['http'] = 400;
                    $view['message'] = self::MESSAGE_CSRF;
                } else {
                    if ($email === '') {
                        $view['errors']['email'] = 'Enter your email address.';
                    }
                    if ($password === '') {
                        $view['errors']['password'] = 'Enter your password.';
                    }
                    if ($view['errors'] === []) {
                        $result = $auth->attempt($email, $password, $ip, $userAgent)['status'];
                        if ($result === CustomerAuthService::LOGIN_OK) {
                            return ['redirect' => $auth->sessions()->consumeReturnTo() ?? '/account/'] + $view;
                        }
                        if ($result === CustomerAuthService::LOGIN_THROTTLED) {
                            $view['http'] = 429;
                            $view['message'] = self::MESSAGE_THROTTLED;
                        } else {
                            $view['message'] = self::MESSAGE_INVALID;
                        }
                    }
                }
            } else {
                // Fixed allow-list: the query value is never echoed.
                $reason = $query['reason'] ?? null;
                if ($reason === 'signedout') {
                    $view['notice'] = ['type' => 'success', 'text' => 'You have been signed out.'];
                } elseif ($reason === 'track') {
                    $view['notice'] = ['type' => 'info', 'text' => 'Sign in to track your vehicle.'];
                } elseif ($reason === 'google') {
                    $view['message'] = 'Google sign-in could not be completed. Please try again, or sign in with your email address.';
                }
            }

            $view['csrf'] = $auth->sessions()->csrfToken();
        } catch (Throwable $e) {
            error_log('Customer sign-in failed to process: ' . get_class($e) . ': ' . $e->getMessage());

            return self::failure($view);
        }

        return $view;
    }

    /**
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    public function register(string $method, array $post, string $ip, string $userAgent): array
    {
        $view = self::view();

        try {
            $auth = $this->auth();
            if ($auth->currentCustomer() !== null) {
                return ['redirect' => $auth->sessions()->consumeReturnTo() ?? '/account/'] + $view;
            }

            if ($method === 'POST') {
                $checked = CustomerValidator::registration($post);
                $view['values'] = ['name' => $checked['values']['name'], 'email' => substr($checked['values']['email'], 0, 255)];

                if (!$auth->sessions()->verifyCsrf($post[CustomerSessionService::CSRF_FIELD] ?? null)) {
                    $view['http'] = 400;
                    $view['message'] = self::MESSAGE_CSRF;
                } elseif ($checked['errors'] !== []) {
                    $view['errors'] = $checked['errors'];
                } else {
                    $v = $checked['values'];
                    $result = $auth->register($v['name'], $v['email'], $v['password'], $ip, $userAgent)['status'];
                    if ($result === CustomerAuthService::REGISTER_OK) {
                        return ['redirect' => $auth->sessions()->consumeReturnTo() ?? '/account/'] + $view;
                    }
                    if ($result === CustomerAuthService::REGISTER_THROTTLED) {
                        $view['http'] = 429;
                        $view['message'] = self::MESSAGE_THROTTLED;
                    } else {
                        $view['errors']['email'] = self::MESSAGE_EXISTS;
                    }
                }
            }

            $view['csrf'] = $auth->sessions()->csrfToken();
        } catch (Throwable $e) {
            error_log('Customer registration failed to process: ' . get_class($e) . ': ' . $e->getMessage());

            return self::failure($view);
        }

        return $view;
    }

    /**
     * GET: the account page (signed-in customers only; others are sent to the sign-in page).
     * POST: sign out.
     *
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    public function account(string $method, array $post, string $ip, string $userAgent): array
    {
        $view = self::view();

        try {
            $auth = $this->auth();
            $customer = $auth->currentCustomer();
            if ($customer === null) {
                return ['redirect' => '/login/'] + $view;
            }

            if ($method === 'POST') {
                if (!$auth->sessions()->verifyCsrf($post[CustomerSessionService::CSRF_FIELD] ?? null)) {
                    return ['redirect' => '/account/'] + $view;   // no state change without a valid token
                }
                $auth->logout($customer['id'], $ip, $userAgent);

                return ['redirect' => '/login/?reason=signedout'] + $view;
            }

            $view['customer'] = $customer;
            $view['csrf'] = $auth->sessions()->csrfToken();
        } catch (Throwable $e) {
            error_log('Customer account page failed to process: ' . get_class($e) . ': ' . $e->getMessage());

            return self::failure($view);
        }

        return $view;
    }

    /**
     * Gate for pages that need a signed-in customer (/track/). The session is checked against the database on every
     * request (an expired, disabled or deleted account is refused), and the check fails CLOSED: if it cannot be made,
     * nothing is shown. A signed-out visitor is sent to sign-in and brought back to $returnTo afterwards.
     *
     * @return array{customer:?array{id:int,name:string,email:string},redirect:?string}
     * @throws Throwable on infrastructure failure (the caller shows the generic error page)
     */
    public function requireCustomer(string $returnTo): array
    {
        $auth = $this->auth();
        $customer = $auth->currentCustomer();
        if ($customer !== null) {
            return ['customer' => $customer, 'redirect' => null];
        }
        $auth->sessions()->setReturnTo($returnTo);

        return ['customer' => null, 'redirect' => '/login/?reason=track'];
    }

    /**
     * /auth/google/ : where to send the browser to start Google sign-in (an absolute Google address), or a local
     * fallback path when Google sign-in is unavailable.
     */
    public function googleStart(string $ip, string $userAgent): string
    {
        try {
            if ($this->auth()->currentCustomer() !== null) {
                return '/account/';
            }

            return $this->auth()->googleStart($ip, $userAgent) ?? '/login/?reason=google';
        } catch (Throwable $e) {
            error_log('Google sign-in could not start: ' . get_class($e) . ': ' . $e->getMessage());

            return '/login/?reason=google';
        }
    }

    /**
     * /auth/google/callback/ : always answers with a fixed local path. Anything wrong (a denied consent screen,
     * a missing or reused state, a failed token exchange) ends at the sign-in page with one generic message.
     *
     * @param array<string,mixed> $query
     */
    public function googleCallback(array $query, string $ip, string $userAgent): string
    {
        $state = is_string($query['state'] ?? null) ? $query['state'] : '';
        $code = is_string($query['code'] ?? null) && !isset($query['error']) ? $query['code'] : '';

        try {
            $auth = $this->auth();

            return $auth->googleFinish($state, $code, $ip, $userAgent) ? ($auth->sessions()->consumeReturnTo() ?? '/account/') : '/login/?reason=google';
        } catch (Throwable $e) {
            error_log('Google sign-in failed to process: ' . get_class($e) . ': ' . $e->getMessage());

            return '/login/?reason=google';
        }
    }

    /** @return array<string,mixed> */
    private static function view(): array
    {
        return ['google' => AppConfig::googleConfigured(), 'redirect' => null, 'http' => 200, 'message' => null, 'notice' => null,
            'values' => [], 'errors' => [], 'csrf' => '', 'customer' => null, 'unavailable' => false];
    }

    /** @param array<string,mixed> $view @return array<string,mixed> */
    private static function failure(array $view): array
    {
        $view['http'] = 500;
        $view['message'] = self::MESSAGE_ERROR;
        $view['unavailable'] = true;   // no usable form without a session/database

        return $view;
    }
}
