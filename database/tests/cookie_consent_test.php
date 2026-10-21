<?php

declare(strict_types=1);

/**
 * Logic tests for cookie consent (spec 18): cookie parsing and tampering, form choices, cross-site detection,
 * return-address validation, and the /cookies/ route. No database, no web server.
 * Run: php database/tests/cookie_consent_test.php
 */

require_once dirname(__DIR__, 2) . '/app/services/CookieConsentService.php';
require_once dirname(__DIR__, 2) . '/app/services/PublicRouter.php';

$pass = 0;
$fail = 0;
$check = static function (string $name, bool $condition, string $detail = '') use (&$pass, &$fail): void {
    if ($condition) {
        $pass++;
        echo "  PASS  $name\n";
    } else {
        $fail++;
        echo "  FAIL  $name" . ($detail !== '' ? "  -> $detail" : '') . "\n";
    }
};
$none = ['chosen' => false, 'activity' => false];

echo "Consent cookie\n";
unset($_COOKIE[CookieConsentService::COOKIE]);
$check('no cookie = not chosen, activity off', CookieConsentService::current() === $none);
$now = time();
$_COOKIE[CookieConsentService::COOKIE] = '1.1.' . $now;
$check('valid "on" cookie', CookieConsentService::current() === ['chosen' => true, 'activity' => true]);
$_COOKIE[CookieConsentService::COOKIE] = '1.0.' . $now;
$check('valid "off" cookie', CookieConsentService::current() === ['chosen' => true, 'activity' => false]);
foreach (['', '1', '1.1', '1.2.' . $now, '2.1.' . $now, '0.1.' . $now, '1.1.' . ($now + 86400 * 30), '1.1.abc', " 1.1.$now", "1.1.$now\n", '1.1.1.1', '<script>', 'true'] as $bad) {
    $_COOKIE[CookieConsentService::COOKIE] = $bad;
    $check('tampered cookie ignored: ' . json_encode($bad), CookieConsentService::current() === $none);
}
$_COOKIE[CookieConsentService::COOKIE] = ['1.1.' . $now];
$check('array cookie ignored', CookieConsentService::current() === $none);
CookieConsentService::save(true, $now);   // headers are already sent by the CLI output: only $_COOKIE is updated
$check('save() updates this request', CookieConsentService::current() === ['chosen' => true, 'activity' => true]);
CookieConsentService::save(false, $now);
$check('save(false) updates this request', CookieConsentService::current() === ['chosen' => true, 'activity' => false]);

echo "\nForm choices\n";
$check('Accept all = on', CookieConsentService::choiceFromForm(['consent' => 'all']) === true);
$check('Essential only = off', CookieConsentService::choiceFromForm(['consent' => 'essential', 'activity' => '1']) === false);
$check('custom with box ticked = on', CookieConsentService::choiceFromForm(['consent' => 'custom', 'activity' => '1']) === true);
$check('custom with box empty = off', CookieConsentService::choiceFromForm(['consent' => 'custom']) === false);
$check('custom with odd value = off', CookieConsentService::choiceFromForm(['consent' => 'custom', 'activity' => 'yes']) === false);
$check('unknown consent = refused', CookieConsentService::choiceFromForm(['consent' => 'maybe']) === null);
$check('missing consent = refused', CookieConsentService::choiceFromForm([]) === null);
$check('array consent = refused', CookieConsentService::choiceFromForm(['consent' => ['all']]) === null);

echo "\nCross-site protection\n";
$host = ['HTTP_HOST' => 'example.test'];
$check('no headers = accepted', CookieConsentService::isSameOrigin($host));
$check('same-origin fetch = accepted', CookieConsentService::isSameOrigin($host + ['HTTP_SEC_FETCH_SITE' => 'same-origin', 'HTTP_ORIGIN' => 'https://example.test']));
$check('typed / bookmarked (none) = accepted', CookieConsentService::isSameOrigin($host + ['HTTP_SEC_FETCH_SITE' => 'none']));
$check('cross-site fetch = refused', !CookieConsentService::isSameOrigin($host + ['HTTP_SEC_FETCH_SITE' => 'cross-site']));
$check('same-site (subdomain) = refused', !CookieConsentService::isSameOrigin($host + ['HTTP_SEC_FETCH_SITE' => 'same-site']));
$check('other Origin = refused', !CookieConsentService::isSameOrigin($host + ['HTTP_ORIGIN' => 'https://evil.example']));
$check('look-alike Origin = refused', !CookieConsentService::isSameOrigin($host + ['HTTP_ORIGIN' => 'https://example.test.evil.example']));
$check('Origin "null" = refused', !CookieConsentService::isSameOrigin($host + ['HTTP_ORIGIN' => 'null']));
$check('Origin with port matches host:port', CookieConsentService::isSameOrigin(['HTTP_HOST' => '127.0.0.1:8765', 'HTTP_ORIGIN' => 'http://127.0.0.1:8765']));
$check('Origin with other port = refused', !CookieConsentService::isSameOrigin(['HTTP_HOST' => '127.0.0.1:8765', 'HTTP_ORIGIN' => 'http://127.0.0.1:9999']));

echo "\nReturn address\n";
foreach (['/', '/vehicles/', '/vehicles/?make=toyota&page=2', '/vehicle/2019-toyota-camry/', '/cookies/'] as $ok) {
    $check('kept: ' . $ok, CookieConsentService::safeReturn($ok) === $ok);
}
foreach (['', 'https://evil.example/', '//evil.example/', '/\\evil.example', '\\\\evil', 'javascript:alert(1)', '/a b/', "/a\r\nLocation: x", "/vehicles/\n",'/a#frag', '/?q=<x>', '/' . str_repeat('a', 600), null, ['/']] as $bad) {
    $check('replaced by "/": ' . json_encode($bad), CookieConsentService::safeReturn($bad) === '/');
}

echo "\nRoute\n";
$config = require dirname(__DIR__, 2) . '/app/config/public_routes.php';
$router = new PublicRouter($config, false);
$r = $router->resolve('GET', '/cookies/');
$check('/cookies/ is a live route', $r['type'] === 'route' && $r['route']['name'] === 'cookies' && $r['route']['status'] === 'live');
$check('/cookies/ accepts POST', $router->resolve('POST', '/cookies/')['type'] === 'route');
$check('/cookies/ refuses PUT', $router->resolve('PUT', '/cookies/')['type'] === 'method_not_allowed');
$check('the view file exists', is_file(dirname(__DIR__, 2) . '/public/views/cookies.php'));

echo "\nResult: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
