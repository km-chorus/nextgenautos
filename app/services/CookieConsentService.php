<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

/**
 * The visitor's cookie choice (spec 18_spec_cookie_consent.md).
 *
 * The site has only essential cookies, so the one optional choice is "browsing activity": whether the visitor's
 * browser may keep copies of the pages they visit. The choice lives in one first-party cookie that holds no personal
 * data: "<version>.<activity 0|1>.<unix time>". Anything else in that cookie counts as "no choice yet".
 *
 * Raise VERSION when what the site stores changes (for example if analytics are ever approved): every visitor is then
 * asked again.
 */
final class CookieConsentService
{
    public const COOKIE = 'vip_cookie_consent';
    public const VERSION = 1;
    public const LIFETIME_DAYS = 180;

    /** @return array{chosen:bool,activity:bool} the choice in this request's cookie; no valid cookie = not chosen, activity off */
    public static function current(): array
    {
        $value = $_COOKIE[self::COOKIE] ?? null;
        if (!is_string($value) || preg_match('/^([0-9]{1,3})\.([01])\.([0-9]{1,12})$/D', $value, $m) !== 1
            || (int) $m[1] !== self::VERSION || (int) $m[3] > time() + 86400) {
            return ['chosen' => false, 'activity' => false];
        }

        return ['chosen' => true, 'activity' => $m[2] === '1'];
    }

    /** Stores the choice in the browser (and in $_COOKIE, so the rest of this request sees it). */
    public static function save(bool $activity, ?int $now = null): void
    {
        $now ??= time();
        $value = self::VERSION . '.' . ($activity ? '1' : '0') . '.' . $now;
        if (!headers_sent()) {
            setcookie(self::COOKIE, $value, [
                'expires'  => $now + self::LIFETIME_DAYS * 86400,
                'path'     => '/',
                'secure'   => AppConfig::cookieSecure(),
                'httponly' => true,   // only the server reads it: it decides whether the banner is shown
                'samesite' => 'Lax',
            ]);
            if (!$activity) {
                header('Clear-Site-Data: "cache"');   // remove the page copies the browser already kept
            }
        }
        $_COOKIE[self::COOKIE] = $value;
    }

    /**
     * The choice a submitted settings form asks for, or null when the form is not one of ours.
     * 'all' = activity on, 'essential' = off, 'custom' = the "activity" checkbox.
     */
    public static function choiceFromForm(array $post): ?bool
    {
        return match ($post['consent'] ?? null) {
            'all'       => true,
            'essential' => false,
            'custom'    => ($post['activity'] ?? null) === '1',
            default     => null,
        };
    }

    /**
     * False when the browser reports that the request comes from another site, so that site cannot change a visitor's
     * choice. Browsers that send neither header are accepted (the cookie is low-risk and SameSite=Lax).
     *
     * @param array<string,mixed> $server $_SERVER
     */
    public static function isSameOrigin(array $server): bool
    {
        $site = $server['HTTP_SEC_FETCH_SITE'] ?? null;
        if (is_string($site) && !in_array($site, ['same-origin', 'none'], true)) {
            return false;
        }
        $origin = $server['HTTP_ORIGIN'] ?? null;
        if (is_string($origin) && $origin !== '') {
            $host = parse_url($origin, PHP_URL_HOST);
            $port = parse_url($origin, PHP_URL_PORT);
            $originHost = is_string($host) ? strtolower($host) . ($port !== null ? ':' . $port : '') : null;

            return $originHost !== null && $originHost === strtolower((string) ($server['HTTP_HOST'] ?? ''));
        }

        return true;
    }

    /** A local address to go back to after saving: a path with an optional simple query, never another site. Anything else is "/". */
    public static function safeReturn(mixed $value): string
    {
        if (!is_string($value) || strlen($value) > 512
            || preg_match('#^/(?!/)[A-Za-z0-9\-._~/]*(\?[A-Za-z0-9\-._~=&%+]*)?$#D', $value) !== 1) {
            return '/';
        }

        return $value;
    }
}
