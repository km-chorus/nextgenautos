<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/CompanyContactService.php';

/**
 * The company contact and social media settings (spec 17): which keys exist, their category, and how each value is checked.
 * Used by the admin Settings page to validate input AND by CompanyContactService to re-check stored values before the public
 * site shows them (a value put into the database by other means is never trusted).
 */
final class ContactSettingsValidator
{
    public const MAX_HOURS = 500;
    public const MAX_URL = 300;

    /** key => [category, kind, label, allowed hosts (url only)] */
    public const FIELDS = [
        'company_phone'    => ['contact', 'phone', 'Company phone', []],
        'company_phone_2'  => ['contact', 'phone', 'Second phone', []],
        'whatsapp_number'  => ['contact', 'phone', 'WhatsApp number', []],
        'contact_email'    => ['contact', 'email', 'Email address', []],
        'opening_hours'    => ['contact', 'text',  'Opening hours', []],
        'office_address'   => ['contact', 'text',  'Office address', []],   // 2026-10-23: Contact page map (Google Maps, loaded on click)
        'social_facebook'  => ['social',  'url',   'Facebook',  ['facebook.com', 'fb.com']],
        'social_instagram' => ['social',  'url',   'Instagram', ['instagram.com']],
        'social_tiktok'    => ['social',  'url',   'TikTok',    ['tiktok.com']],
        'social_x'         => ['social',  'url',   'X',         ['x.com', 'twitter.com']],
        'social_youtube'   => ['social',  'url',   'YouTube',   ['youtube.com', 'youtu.be']],
        'social_linkedin'  => ['social',  'url',   'LinkedIn',  ['linkedin.com']],
        // Spec 15 §4 (email integration 2026-10-27): where new-enquiry alerts are emailed. Category 'company' is never read
        // by the public site (CompanyContactService reads 'contact' and 'social' only), so this address stays private.
        'notification_email' => ['company', 'email', 'Notification email', []],
    ];

    /** Every settings category the admin Settings form edits. */
    public const CATEGORIES = ['contact', 'social', 'company'];

    /**
     * @param array<string,mixed> $post
     * @return array{values:array<string,string>,errors:array<string,string>} values: key => cleaned value ('' = remove)
     */
    public static function validate(array $post): array
    {
        $values = [];
        $errors = [];
        foreach (self::FIELDS as $key => [, $kind, $label]) {
            $raw = $post[$key] ?? '';
            if (!is_string($raw)) {
                $errors[$key] = 'Enter a valid value.';
                $values[$key] = '';
                continue;
            }
            $value = $kind === 'text' ? trim(str_replace(["\r\n", "\r"], "\n", $raw)) : trim($raw);
            $values[$key] = $value;
            if ($value === '') {
                continue;
            }
            $error = self::problem($key, $value);
            if ($error !== null) {
                $errors[$key] = $error;
            }
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /** True when a stored value may be shown on the public site. */
    public static function isValid(string $key, string $value): bool
    {
        return isset(self::FIELDS[$key]) && trim($value) !== '' && self::problem($key, trim($value)) === null;
    }

    private static function problem(string $key, string $value): ?string
    {
        [, $kind, $label, $hosts] = self::FIELDS[$key];

        return match ($kind) {
            'phone' => CompanyContactService::normalizePhone($value) === null
                ? 'Enter a valid phone number, for example 024 123 4567 or +233 24 123 4567.' : null,
            'email' => strlen($value) > 255 || filter_var($value, FILTER_VALIDATE_EMAIL) === false
                ? 'Enter a valid email address.' : null,
            'text'  => mb_strlen($value) > self::MAX_HOURS || preg_match('/[^\P{Cc}\n\t]|[<>]/u', $value) === 1
                ? 'Use up to ' . self::MAX_HOURS . ' characters, without < or >.' : null,
            'url'   => self::urlProblem($value, $label, $hosts),
        };
    }

    /** @param list<string> $hosts */
    private static function urlProblem(string $url, string $label, array $hosts): ?string
    {
        $message = 'Enter the full https:// address of your ' . $label . ' page.';
        if (strlen($url) > self::MAX_URL || preg_match('/[\s"\'<>\\\\]/', $url) === 1 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return $message;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return $message;
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        foreach ($hosts as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return null;
            }
        }

        return 'This does not look like a ' . $label . ' address (' . implode(' or ', $hosts) . ').';
    }
}
