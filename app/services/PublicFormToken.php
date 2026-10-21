<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once __DIR__ . '/SecretBox.php';

/**
 * CSRF protection for public forms that need no sign-in (spec 14: vehicle enquiry and reservation), WITHOUT a session.
 *
 * Why not the customer session: the header shows "Account" whenever the customer-session cookie exists, so starting that
 * session for every visitor of a vehicle page would make the site look signed in for everyone.
 *
 * How: the browser gets a random, HttpOnly, SameSite=Lax cookie (`vip_form`). The form carries
 * "<issued unix time>.<HMAC(APP_KEY-derived key, cookie | form purpose | issued time)>". A cross-site attacker can read
 * neither the cookie nor the HMAC, so cannot forge the field. The issued time also gives the spam checks: a form sent
 * less than MIN_SECONDS after it was rendered, or older than MAX_SECONDS, is refused.
 */
final class PublicFormToken
{
    public const COOKIE = 'vip_form';
    public const FIELD = 'form_token';
    public const MIN_SECONDS = 3;
    public const MAX_SECONDS = 7200;

    /** The token for a form (sets the browser cookie when needed). Null when APP_KEY is not configured: the form must then be refused. */
    public static function issue(string $purpose, ?int $now = null): ?string
    {
        if (AppConfig::appKey() === null) {
            return null;
        }
        $nonce = self::cookieValue();
        if ($nonce === null) {
            $nonce = bin2hex(random_bytes(16));
            if (!headers_sent()) {
                setcookie(self::COOKIE, $nonce, ['expires' => 0, 'path' => '/', 'secure' => AppConfig::cookieSecure(), 'httponly' => true, 'samesite' => 'Lax']);
            }
            $_COOKIE[self::COOKIE] = $nonce;
        }
        $issued = $now ?? time();

        return $issued . '.' . self::sign($nonce, $purpose, $issued);
    }

    /** 'ok', 'invalid' (missing, forged, other browser, expired) or 'too_fast' (sent within MIN_SECONDS: almost certainly a bot). */
    public static function check(mixed $submitted, string $purpose, ?int $now = null): string
    {
        $nonce = self::cookieValue();
        if ($nonce === null || AppConfig::appKey() === null || !is_string($submitted)
            || preg_match('/^([0-9]{1,12})\.([a-f0-9]{64})$/', $submitted, $m) !== 1) {
            return 'invalid';
        }
        $issued = (int) $m[1];
        if (!hash_equals(self::sign($nonce, $purpose, $issued), $m[2])) {
            return 'invalid';
        }
        $age = ($now ?? time()) - $issued;
        if ($age < 0 || $age > self::MAX_SECONDS) {
            return 'invalid';
        }

        return $age < self::MIN_SECONDS ? 'too_fast' : 'ok';
    }

    private static function cookieValue(): ?string
    {
        $value = $_COOKIE[self::COOKIE] ?? null;

        return is_string($value) && preg_match('/^[a-f0-9]{32}$/', $value) === 1 ? $value : null;
    }

    private static function sign(string $nonce, string $purpose, int $issued): string
    {
        return hash_hmac('sha256', $nonce . '|' . $purpose . '|' . $issued, SecretBox::derive('public-form'));
    }
}
