<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/repositories/SettingsRepository.php';
require_once dirname(__DIR__) . '/repositories/AuditLogRepository.php';
require_once dirname(__DIR__) . '/validation/ContactSettingsValidator.php';
require_once __DIR__ . '/Transaction.php';

/**
 * Admin Settings (spec 17): company contact details and social media links, stored in the existing `settings` table.
 * Saving validates every field, writes ONLY the keys whose value changed (an empty field removes the setting) in one
 * transaction, and records SETTINGS_UPDATED with the names of the changed keys (never their values).
 */
final class SettingsService
{
    public const RESULT_OK = 'ok';
    public const RESULT_UNCHANGED = 'unchanged';
    public const RESULT_INVALID = 'invalid';

    public function __construct(private PDO $pdo, private SettingsRepository $settings, private AuditLogRepository $audit)
    {
    }

    public static function create(): self
    {
        $pdo = Database::connection();

        return new self($pdo, new SettingsRepository($pdo), new AuditLogRepository($pdo));
    }

    /** @return array<string,string> every contact/social key => its stored value ('' when not set) */
    public function current(): array
    {
        $stored = $this->settings->valuesIn(ContactSettingsValidator::CATEGORIES);
        $out = [];
        foreach (array_keys(ContactSettingsValidator::FIELDS) as $key) {
            $out[$key] = $stored[$key] ?? '';
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $post
     * @return array{status:string,values:array<string,string>,errors:array<string,string>,changed:list<string>}
     */
    public function save(array $post, int $adminId, string $ip, string $userAgent): array
    {
        $checked = ContactSettingsValidator::validate($post);
        if ($checked['errors'] !== []) {
            return ['status' => self::RESULT_INVALID, 'values' => $checked['values'], 'errors' => $checked['errors'], 'changed' => []];
        }

        $current = $this->current();
        $changed = [];
        foreach ($checked['values'] as $key => $value) {
            if ($value !== $current[$key]) {
                $changed[] = $key;
            }
        }
        if ($changed === []) {
            return ['status' => self::RESULT_UNCHANGED, 'values' => $checked['values'], 'errors' => [], 'changed' => []];
        }

        Transaction::run($this->pdo, function () use ($changed, $checked, $adminId, $ip, $userAgent): void {
            foreach ($changed as $key) {
                $category = ContactSettingsValidator::FIELDS[$key][0];
                $value = $checked['values'][$key];
                $value === '' ? $this->settings->remove($key, $category) : $this->settings->put($key, $category, $value);
            }
            $this->audit->record('SETTINGS_UPDATED', $adminId, 'settings', null, 'Changed: ' . implode(', ', $changed), $ip, $userAgent);
        });

        return ['status' => self::RESULT_OK, 'values' => $checked['values'], 'errors' => [], 'changed' => $changed];
    }
}
