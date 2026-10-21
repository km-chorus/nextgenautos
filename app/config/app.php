<?php

declare(strict_types=1);

/**
 * Application configuration.
 *
 * Environment-specific values come from real environment variables or from the
 * git-ignored `.env` file in the project root (real variables win). No secrets
 * belong in this file.
 *
 * Variables:
 *   APP_ENV                  development | production (default: production, the safe choice)
 *   SESSION_TIMEOUT_SECONDS  inactivity timeout (default 1800 = 30 minutes).
 *                            Values below 300 are honored only when APP_ENV=development
 *                            (so a short testing value cannot weaken production).
 */
final class AppConfig
{
    public const DEFAULT_SESSION_TIMEOUT = 1800;

    /** Login throttling: failures counted inside a sliding window. */
    public const LOGIN_WINDOW_MINUTES = 15;
    public const LOGIN_MAX_FAILURES_PER_IP = 10;            // any account, one IP
    public const LOGIN_MAX_FAILURES_PER_IP_ACCOUNT = 5;     // one account, one IP
    public const LOGIN_MAX_FAILURES_PER_ACCOUNT = 30;       // one account, all IPs (distributed attack)

    /** Second factor (TOTP / recovery code) and re-authentication failures, same sliding window. */
    public const SECOND_FACTOR_MAX_FAILURES_PER_ADMIN = 5;
    public const SECOND_FACTOR_MAX_FAILURES_PER_IP = 10;
    /** Seconds the "password verified, code still needed" state stays valid. */
    public const TWO_FACTOR_PENDING_SECONDS = 300;
    /** Seconds an unconfirmed authenticator enrollment stays valid. */
    public const TWO_FACTOR_ENROLLMENT_SECONDS = 900;
    public const TWO_FACTOR_ISSUER = 'Amponsah Autos';
    public const RECOVERY_CODE_COUNT = 10;

    /** Password reset throttling (requests are counted whether or not the email exists). */
    public const RESET_WINDOW_MINUTES = 60;
    public const RESET_MAX_REQUESTS_PER_IP = 5;
    public const RESET_MAX_REQUESTS_PER_ACCOUNT = 3;
    public const RESET_TOKEN_FAILURE_WINDOW_MINUTES = 15;
    public const RESET_MAX_TOKEN_FAILURES_PER_IP = 10;
    /** A reset request always takes at least this long, so response time does not reveal whether the email exists. */
    public const RESET_MIN_RESPONSE_MS = 1200;

    /** @var array<string,string>|null */
    private static ?array $fileValues = null;

    /** Environment value: real environment variable first, then .env, then default. */
    public static function env(string $key, string $default = ''): string
    {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }

        return self::fileValues()[$key] ?? $default;
    }

    public static function environment(): string
    {
        $value = strtolower(self::env('APP_ENV', 'production'));

        return $value === 'development' ? 'development' : 'production';
    }

    public static function isProduction(): bool
    {
        return self::environment() === 'production';
    }

    /** True when the current request arrived over HTTPS (direct, not via a proxy header). */
    public static function isHttps(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';

        return ($https !== '' && strtolower((string) $https) !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }

    /**
     * Session cookie "Secure" flag: always in production (HTTPS is mandatory there),
     * and in development whenever the request itself is HTTPS, so plain
     * http://localhost keeps working.
     */
    public static function cookieSecure(): bool
    {
        return self::isProduction() || self::isHttps();
    }

    public static function sessionTimeoutSeconds(): int
    {
        $configured = (int) self::env('SESSION_TIMEOUT_SECONDS', '0');
        $minimum = self::isProduction() ? 300 : 5;

        if ($configured < $minimum) {
            return self::DEFAULT_SESSION_TIMEOUT;
        }

        return min($configured, 86400);
    }

    /**
     * Application encryption key (32 random bytes, "base64:..." form) used to encrypt
     * TOTP secrets and to key recovery-code hashes. It lives only in the environment
     * (never in the database or Git). Returns null when missing or malformed:
     * features that need it are then unavailable, never silently weakened.
     */
    public static function appKey(): ?string
    {
        $value = self::env('APP_KEY');
        if (!str_starts_with($value, 'base64:')) {
            return null;
        }
        $raw = base64_decode(substr($value, 7), true);

        return ($raw !== false && strlen($raw) === 32) ? $raw : null;
    }

    /** Google OAuth client id (customer "Continue with Google"). Empty when Google sign-in is not configured. */
    public static function googleClientId(): string
    {
        return trim(self::env('GOOGLE_CLIENT_ID'));
    }

    /** Google OAuth client secret. Server-side only: never sent to the browser, never committed. */
    public static function googleClientSecret(): string
    {
        return trim(self::env('GOOGLE_CLIENT_SECRET'));
    }

    /** Exact callback address registered in Google Cloud Console; built from APP_URL, never from the request. */
    public static function googleRedirectUri(): ?string
    {
        $base = self::appUrl();

        return $base === null ? null : $base . '/auth/google/callback/';
    }

    /** Google sign-in is offered only when the client id, the secret and a valid APP_URL are all configured. */
    public static function googleConfigured(): bool
    {
        return self::googleClientId() !== '' && self::googleClientSecret() !== '' && self::googleRedirectUri() !== null;
    }

    /**
     * Public base URL of the site (for links in emails), without trailing slash.
     * It is configuration, never derived from the request: a forged Host header
     * must not be able to redirect a reset link to another site. Production
     * requires https. Returns null when missing or unacceptable.
     */
    public static function appUrl(): ?string
    {
        $url = rtrim(self::env('APP_URL'), '/');
        $parts = parse_url($url);
        if ($url === '' || $parts === false || !isset($parts['scheme'], $parts['host'])
            || isset($parts['user']) || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'https' && !($scheme === 'http' && !self::isProduction())) {
            return null;
        }

        return $url;
    }

    /** Password reset link lifetime in minutes (default 30, allowed 5 to 120). */
    public static function passwordResetMinutes(): int
    {
        $minutes = (int) self::env('PASSWORD_RESET_MINUTES', '30');

        return ($minutes >= 5 && $minutes <= 120) ? $minutes : 30;
    }

    public static function brevoApiKey(): string
    {
        return self::env('BREVO_API_KEY');
    }

    public static function mailFromEmail(): string
    {
        return self::env('MAIL_FROM_EMAIL');
    }

    public static function mailFromName(): string
    {
        return self::env('MAIL_FROM_NAME', 'Amponsah Autos');
    }

    /** Brevo API base URL. A non-https base (a local test double) is honored only in development. */
    public static function brevoApiBase(): string
    {
        $base = rtrim(self::env('BREVO_API_BASE', 'https://api.brevo.com'), '/');
        if (!str_starts_with($base, 'https://') && self::isProduction()) {
            return 'https://api.brevo.com';
        }

        return $base;
    }

    /**
     * Error visibility: in production PHP errors are logged but never shown to the
     * browser (no paths, SQL or stack traces). In development PHP's own settings are
     * left alone so problems stay visible to the developer.
     */
    public static function applyErrorSettings(): void
    {
        ini_set('log_errors', '1');
        if (self::isProduction()) {
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
        }
    }

    /** @return array<string,string> */
    private static function fileValues(): array
    {
        if (self::$fileValues !== null) {
            return self::$fileValues;
        }

        self::$fileValues = [];
        $path = dirname(__DIR__, 2) . '/.env';
        if (!is_file($path) || !is_readable($path)) {
            return self::$fileValues;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            self::$fileValues[trim($key)] = trim(trim($value), "\"'");
        }

        return self::$fileValues;
    }
}

AppConfig::applyErrorSettings();
