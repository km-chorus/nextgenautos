<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/SettingsService.php';
require_once dirname(__DIR__) . '/services/AuthorizationService.php';
require_once dirname(__DIR__) . '/helpers/csrf.php';
require_once dirname(__DIR__) . '/helpers/flash.php';

/**
 * Admin Settings controller (spec 17), following the Brand Management pattern: permission check, CSRF on POST,
 * validation and saving in SettingsService, a view model for the page (or a redirect after a successful save).
 * View model: 'http', 'problem' (null | forbidden | csrf | error), values, errors, formMessage, flash.
 */
final class SettingsController
{
    public const PERMISSION = 'settings.manage';

    public const MESSAGE_FORBIDDEN = 'Your account does not have permission to change the settings.';
    public const MESSAGE_CSRF = "We couldn't verify your request. Reload the page and try again.";
    public const MESSAGE_ERROR = 'Something went wrong. Please try again.';
    public const MESSAGE_INVALID_FORM = 'Please correct the highlighted fields.';

    public function __construct(private ?SettingsService $service = null)
    {
    }

    /**
     * @param array<string,mixed> $admin
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    public function edit(array $admin, string $method, array $post, string $ip, string $userAgent): array
    {
        if (!AuthorizationService::can((string) ($admin['role'] ?? ''), self::PERMISSION)) {
            return ['http' => 403, 'problem' => 'forbidden', 'message' => self::MESSAGE_FORBIDDEN];
        }

        try {
            $service = $this->service ?? SettingsService::create();
            $view = ['values' => $service->current(), 'errors' => [], 'formMessage' => null, 'flash' => flash_take(), 'http' => 200, 'problem' => null];
            if ($method !== 'POST') {
                return $view;
            }
            if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                try {
                    (new AuditLogRepository(Database::connection()))->record('CSRF_FAILURE', (int) $admin['id'], null, null,
                        'Settings request rejected: missing or invalid CSRF token', $ip, $userAgent);
                } catch (Throwable $e) {
                    error_log('Settings: could not log CSRF failure: ' . get_class($e));
                }

                return ['http' => 400, 'problem' => 'csrf', 'formMessage' => self::MESSAGE_CSRF] + $view;
            }

            $result = $service->save($post, (int) $admin['id'], $ip, $userAgent);
            if ($result['status'] === SettingsService::RESULT_INVALID) {
                return ['values' => $result['values'], 'errors' => $result['errors'], 'formMessage' => self::MESSAGE_INVALID_FORM] + $view;
            }
            flash_set($result['status'] === SettingsService::RESULT_OK ? 'success' : 'info',
                $result['status'] === SettingsService::RESULT_OK ? 'Settings saved. The website now shows the new details.' : 'No changes were made.');

            return ['redirect' => 'settings.php'];
        } catch (Throwable $e) {
            error_log('Settings failed: ' . get_class($e) . ': ' . $e->getMessage());

            return ['http' => 500, 'problem' => 'error', 'message' => self::MESSAGE_ERROR];
        }
    }
}
