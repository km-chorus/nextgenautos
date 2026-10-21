<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/AuthService.php';
require_once dirname(__DIR__) . '/services/PasswordPolicy.php';
require_once dirname(__DIR__) . '/helpers/csrf.php';

/**
 * Account security page: two-factor authentication, recovery codes, password.
 * Every state-changing action: CSRF-checked, throttled, logged, and (for the
 * sensitive ones) re-authenticated with the current password and a current code.
 * Returns view data; the page renders it. Secrets and codes are never logged.
 */
final class SecurityController
{
    public const MESSAGE_INVALID = 'The password or verification code is not correct.';
    public const MESSAGE_THROTTLED = 'Too many attempts. Wait a few minutes and try again.';
    public const MESSAGE_CSRF = "We couldn't verify your request. Reload the page and try again.";
    public const MESSAGE_ERROR = 'Something went wrong. Please try again.';
    public const MESSAGE_CODE_INVALID = 'Invalid verification code.';

    public function __construct(private ?AuthService $auth = null)
    {
    }

    /**
     * Handles an optional POST and builds everything the page shows.
     *
     * @param array{id:int,name:string,role:string} $admin
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    public function handle(string $method, array $post, array $admin, string $ip, string $userAgent): array
    {
        $view = [
            'http'          => 200,
            'available'     => TwoFactorService::isAvailable(),
            'enabled'       => false,
            'confirmedAt'   => null,
            'remaining'     => 0,
            'setup'         => null,   // ['svg' => string, 'key' => string] while an enrollment is in progress
            'codes'         => null,   // recovery codes, shown ONCE right after they are created
            'notice'        => null,   // ['type' => string, 'text' => string]
            'errors'        => [],     // section => message
            'passwordErrors' => [],    // field => message
        ];

        try {
            $auth = $this->auth ?? AuthService::create();

            if ($method === 'POST') {
                $this->dispatch($auth, $post, $admin, $ip, $userAgent, $view);
            }

            $this->describeState($auth, $admin, $view);
        } catch (Throwable $e) {
            error_log('Security page failed: ' . get_class($e) . ': ' . $e->getMessage());
            $view['http'] = 500;
            $view['errors']['page'] = self::MESSAGE_ERROR;
        }

        return $view;
    }

    /** @param array<string,mixed> $post */
    private function dispatch(AuthService $auth, array $post, array $admin, string $ip, string $ua, array &$view): void
    {
        if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
            $auth->logSecurityEvent('CSRF_FAILURE', $admin['id'], 'Security settings request rejected: missing or invalid CSRF token', $ip, $ua);
            $view['http'] = 400;
            $view['errors']['page'] = self::MESSAGE_CSRF;
            return;
        }

        $str = static fn (string $key): string => is_string($post[$key] ?? null) ? $post[$key] : '';
        $adminId = $admin['id'];
        $sessions = $auth->sessions();

        switch ($str('action')) {
            case 'start_2fa':
                if (!TwoFactorService::isAvailable()) {
                    $view['errors']['twofactor'] = 'Two-factor authentication is unavailable: the application encryption key is not configured.';
                    return;
                }
                $secret = TwoFactorService::newSecret();
                $sessions->beginEnrollment(SecretBox::encrypt($secret));
                $auth->logSecurityEvent('2FA_SETUP_STARTED', $adminId, 'Two-factor setup started', $ip, $ua);
                return;

            case 'cancel_2fa_setup':
                $sessions->clearEnrollment();
                return;

            case 'confirm_2fa':
                $enrollment = $sessions->enrollment();
                if ($enrollment === null) {
                    $view['errors']['twofactor'] = 'The setup session expired. Start again.';
                    return;
                }
                $result = $auth->confirmEnrollment($adminId, SecretBox::decrypt($enrollment['secret']), $str('code'), $ip, $ua);
                if ($result['status'] === 'ok') {
                    $sessions->clearEnrollment();
                    $view['codes'] = $result['codes'];
                    $view['notice'] = ['type' => 'success', 'text' => 'Two-factor authentication is now enabled.'];
                } else {
                    $view['http'] = $result['status'] === AuthService::LOGIN_THROTTLED ? 429 : 200;
                    $view['errors']['twofactor'] = $result['status'] === AuthService::LOGIN_THROTTLED
                        ? self::MESSAGE_THROTTLED : self::MESSAGE_CODE_INVALID;
                }
                return;

            case 'disable_2fa':
                // The authenticator code wins; a recovery code is used only when no authenticator code was typed.
                $recovery = $str('code') === '' && $str('recovery_code') !== '';
                $status = $auth->disableTwoFactor($adminId, $str('current_password'), $str($recovery ? 'recovery_code' : 'code'), $recovery, $ip, $ua);
                if ($status === 'ok') {
                    $view['notice'] = ['type' => 'success', 'text' => 'Two-factor authentication has been turned off.'];
                } else {
                    $view['http'] = $status === AuthService::LOGIN_THROTTLED ? 429 : 200;
                    $view['errors']['disable'] = $status === AuthService::LOGIN_THROTTLED ? self::MESSAGE_THROTTLED : self::MESSAGE_INVALID;
                }
                return;

            case 'regenerate_codes':
                $result = $auth->regenerateRecoveryCodes($adminId, $str('current_password'), $str('code'), $ip, $ua);
                if ($result['status'] === 'ok') {
                    $view['codes'] = $result['codes'];
                    $view['notice'] = ['type' => 'success', 'text' => 'New recovery codes created. The old ones no longer work.'];
                } else {
                    $view['http'] = $result['status'] === AuthService::LOGIN_THROTTLED ? 429 : 200;
                    $view['errors']['regenerate'] = $result['status'] === AuthService::LOGIN_THROTTLED ? self::MESSAGE_THROTTLED : self::MESSAGE_INVALID;
                }
                return;

            case 'change_password':
                $this->changePassword($auth, $post, $adminId, $ip, $ua, $view);
                return;
        }
    }

    /** @param array<string,mixed> $post */
    private function changePassword(AuthService $auth, array $post, int $adminId, string $ip, string $ua, array &$view): void
    {
        $str = static fn (string $key): string => is_string($post[$key] ?? null) ? $post[$key] : '';
        $new = $str('new_password');
        $problems = PasswordPolicy::validate($new);

        if ($problems !== []) {
            $view['passwordErrors']['new_password'] = $problems[0];
            return;
        }
        if (!hash_equals($new, $str('confirm_password'))) {
            $view['passwordErrors']['confirm_password'] = 'The two passwords do not match.';
            return;
        }

        $row = $auth->twoFactorState($adminId);
        $status = $auth->confirmIdentity($adminId, $str('current_password'), $row['enabled'] ? $str('code') : null, false, $ip, $ua);
        if ($status !== 'ok') {
            $view['http'] = $status === AuthService::LOGIN_THROTTLED ? 429 : 200;
            $view['errors']['password'] = $status === AuthService::LOGIN_THROTTLED ? self::MESSAGE_THROTTLED : self::MESSAGE_INVALID;
            return;
        }

        $auth->changePassword($adminId, $new, $ip, $ua);
        $view['notice'] = ['type' => 'success', 'text' => 'Your password has been changed. Your other sessions were signed out.'];
    }

    /** Current 2FA state plus the enrollment panel (QR + manual key) when one is in progress. */
    private function describeState(AuthService $auth, array $admin, array &$view): void
    {
        $state = $auth->twoFactorState($admin['id']);
        $view['enabled'] = $state['enabled'];
        $view['confirmedAt'] = $state['confirmedAt'];
        $view['email'] = $state['email'];
        $view['remaining'] = $state['enabled'] ? $auth->twoFactor()->remainingRecoveryCodes($admin['id']) : 0;

        if (!$state['enabled'] && $view['available'] && $view['codes'] === null) {
            $enrollment = $auth->sessions()->enrollment();
            if ($enrollment !== null) {
                $secret = SecretBox::decrypt($enrollment['secret']);
                $view['setup'] = [
                    'svg' => TwoFactorService::qrSvg(TwoFactorService::provisioningUri($secret, $state['email'])),
                    'key' => trim(chunk_split($secret, 4, ' ')),
                ];
            }
        }
    }
}
