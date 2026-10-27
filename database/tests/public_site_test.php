<?php

declare(strict_types=1);

/**
 * Logic tests for the public website foundation (spec 09): router, metadata, image helper,
 * alerts/empty state, navigation and footer link integrity. No database, no web server.
 * Run: php database/tests/public_site_test.php
 */

require_once dirname(__DIR__, 2) . '/app/services/PublicRouter.php';
require_once dirname(__DIR__, 2) . '/app/helpers/public_ui.php';

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

/** @var array{routes:list<array<string,mixed>>,patterns:list<array<string,mixed>>,development:list<array<string,mixed>>} $config */
$config = require dirname(__DIR__, 2) . '/app/config/public_routes.php';
$prod = new PublicRouter($config, false);
$dev = new PublicRouter($config, true);

echo "Router\n";
$r = $prod->resolve('GET', '/');
$check('home resolves', $r['type'] === 'route' && $r['route']['name'] === 'home');
$check('HEAD allowed', $prod->resolve('HEAD', '/vehicles/')['type'] === 'route');
foreach (['POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'] as $m) {
    $check("$m is method_not_allowed", $prod->resolve($m, '/')['type'] === 'method_not_allowed');
}
$check('missing trailing slash redirects', $prod->resolve('GET', '/vehicles') === ['type' => 'redirect', 'to' => '/vehicles/']);
$check('uppercase redirects to lowercase', $prod->resolve('GET', '/Vehicles/') === ['type' => 'redirect', 'to' => '/vehicles/']);
$check('query string ignored', $prod->resolve('GET', '/vehicles/?a=1&b=<script>')['type'] === 'route');
$check('unknown path is not_found', $prod->resolve('GET', '/nothing/')['type'] === 'not_found');
$check('unknown path is never redirected', $prod->resolve('GET', '/Nothing')['type'] === 'not_found');
foreach (['/a//b/', '//evil.com/', '/../etc/passwd', '/vehicles/../admin/', '/vehicles%2f/', '/a\\b/', '/vehicles.php', "/vehicles/\0", '/' . str_repeat('a', 201) . '/', '/vehicles/ ', '/%00/'] as $bad) {
    $check('rejects ' . json_encode(substr($bad, 0, 30)), $prod->resolve('GET', $bad)['type'] === 'not_found');
}
$redir = $prod->resolve('GET', '//vehicles');
$check('protocol-relative path never produces a redirect', $redir['type'] !== 'redirect');
$b = $prod->resolve('GET', '/brands/toyota/');
$check('brand slug route', $b['type'] === 'route' && $b['slug'] === 'toyota' && $b['route']['parent'] === 'brands');
$v = $prod->resolve('GET', '/vehicle/2020-toyota-camry-1/');
$check('vehicle slug route', $v['type'] === 'route' && $v['slug'] === '2020-toyota-camry-1');
$check('slug with double hyphen rejected', $prod->resolve('GET', '/brands/a--b/')['type'] === 'not_found');
$check('slug with leading hyphen rejected', $prod->resolve('GET', '/brands/-a/')['type'] === 'not_found');
$check('brand slug over 100 chars rejected', $prod->resolve('GET', '/brands/' . str_repeat('a', 101) . '/')['type'] === 'not_found');
$check('brand slug of 100 chars accepted', $prod->resolve('GET', '/brands/' . str_repeat('a', 100) . '/')['type'] === 'route');
$check('vehicle slug over 160 chars rejected', $prod->resolve('GET', '/vehicle/' . str_repeat('a', 161) . '/')['type'] === 'not_found');
$check('uppercase slug redirects to lowercase', $prod->resolve('GET', '/brands/Toyota/') === ['type' => 'redirect', 'to' => '/brands/toyota/']);
$check('styleguide hidden in production', $prod->resolve('GET', '/styleguide/')['type'] === 'not_found');
$check('styleguide error preview hidden in production', $prod->resolve('GET', '/styleguide/error/')['type'] === 'not_found');
$check('styleguide available in development', $dev->resolve('GET', '/styleguide/')['type'] === 'route');

echo "\nRoute table\n";
$paths = array_column($config['routes'], 'path');
$check('route paths unique', count($paths) === count(array_unique($paths)));
$allOk = true;
foreach (array_merge($config['routes'], $config['development']) as $route) {
    if (preg_match('#^/[a-z0-9/-]*$#', $route['path']) !== 1 || !str_ends_with($route['path'], '/') || $route['path'] !== strtolower($route['path'])) {
        $allOk = false;
    }
}
$check('every route path is lowercase and ends with a slash', $allOk);
$check('admin paths are not public routes', array_filter($paths, static fn ($p) => str_starts_with($p, '/admin')) === []);

echo "\nNavigation and footer links\n";
$links = [];
$loginPath = null;
$navFlat = [];
foreach (public_nav_items(false, true) as $item) {   // with the accident page on, so every possible link is checked
    $navFlat[] = $item;
    foreach ($item['children'] ?? [] as $child) { $navFlat[] = $child; }
}
foreach ($navFlat as $item) {
    if ($item['route'] === 'login') {
        $loginPath = $item['path'];   // the existing administrator sign-in page, not a public route
        continue;
    }
    $links[] = $item['path'];
}
foreach (public_footer_groups() as $group) {
    foreach ($group['links'] as $link) {
        $links[] = $link['path'];
    }
}
$unresolved = array_filter($links, static fn ($p) => $prod->resolve('GET', $p)['type'] !== 'route');
$check('every header and footer link resolves to a route', $unresolved === [], implode(',', $unresolved));
$check('no public link points at the admin; Login opens the customer sign-in (spec 13), the admin sign-in stays a separate file', array_filter($links, static fn ($p) => str_contains($p, 'admin')) === [] && $loginPath === '/login/' && is_file(dirname(__DIR__, 2) . '/admin/login.php'));
$check('Login has an icon; no other item does', array_column(array_filter(public_nav_items(), static fn ($i) => isset($i['icon'])), 'route') === ['login']);
$check('nav items: Home, Vehicles (dropdown), About, Contact, Login (2026-10-26)', array_column(public_nav_items(), 'label') === ['Home', 'Vehicles', 'About', 'Contact', 'Login'] && !function_exists('public_cta'));
$vehiclesItem = public_nav_items(false, true)[1];
$check('under Vehicles: All Vehicles, Accident Vehicles (only when on), Track Vehicle', array_column($vehiclesItem['children'], 'label') === ['All Vehicles', 'Accident Vehicles', 'Track Vehicle']
    && array_column(public_nav_items(false, false)[1]['children'], 'label') === ['All Vehicles', 'Track Vehicle']);
$check('footer groups match the spec', array_column(public_footer_groups(), 'title') === ['Company', 'Vehicles', 'Support']);
$navRoutes = array_diff(array_column($navFlat, 'route'), ['login']);
$check('nav route names exist', array_filter($navRoutes, static fn ($n) => $prod->byName($n) === null) === []);

echo "\nMetadata\n";
$meta = public_meta(['title' => 'Vehicles', 'path' => '/vehicles/']);
$check('title has site name', str_contains($meta, '<title>Vehicles | Next Gen Autos and Parts</title>'));
$check('no description tag when none is given', !str_contains($meta, 'name="description"'));
$check('robots default', str_contains($meta, 'content="index,follow"'));
$xss = public_meta(['title' => '"><script>alert(1)</script>', 'description' => '<img onerror=x>', 'path' => '/vehicles/']);
$check('title and description escaped', !str_contains($xss, '<script>') && !str_contains($xss, '<img'));
$home = public_meta(['title' => 'Next Gen Autos and Parts', 'path' => '/']);
$check('home title is just the site name', str_contains($home, '<title>Next Gen Autos and Parts</title>'));
$_SERVER['HTTP_HOST'] = 'evil.example';
$check('canonical never uses the Host header', !str_contains(public_meta(['title' => 'x', 'path' => '/x/']), 'evil.example'));
$check('canonical ignores a non-route path', !str_contains(public_meta(['title' => 'x', 'path' => '/x/"><b>']), '<b>'));
$check('bad og:image path ignored', !str_contains(public_meta(['title' => 'x', 'path' => '/x/', 'image' => '/../secret.png']), 'og:image'));

echo "\nHelpers\n";
$hex = str_repeat('a', 32);
$img = public_image([['path' => "uploads/vehicles/$hex-t.webp", 'width' => 640], ['path' => "uploads/vehicles/$hex.webp", 'width' => 1600]], 'A "car"', 1600, 1000);
$check('image has srcset, dimensions, lazy loading, escaped alt', str_contains($img, 'srcset=') && str_contains($img, 'width="1600" height="1000"') && str_contains($img, 'loading="lazy"') && str_contains($img, 'alt="A &quot;car&quot;"'));
$check('priority image is not lazy', !str_contains(public_image([['path' => "uploads/team/$hex.webp", 'width' => 800]], 'x', 800, 600, ['priority' => true]), 'loading="lazy"'));
$check('image with outside URL is refused', public_image([['path' => 'https://evil.example/a.webp', 'width' => 100]], 'x', 100, 100) === '');
$check('image with traversal is refused', public_image([['path' => 'uploads/vehicles/../../.env', 'width' => 100]], 'x', 100, 100) === '');
$check('image with wrong extension is refused', public_image([['path' => "uploads/vehicles/$hex.php", 'width' => 100]], 'x', 100, 100) === '');
$check('decorative image has empty alt', str_contains(public_image([['path' => "uploads/team/$hex.webp", 'width' => 800]], 'ignored', 800, 600, ['decorative' => true]), 'alt=""'));
$check('alert escapes and labels type', str_contains(public_alert('error', '<b>x</b>'), '&lt;b&gt;') && str_contains(public_alert('error', 'x'), 'role="alert"') && str_contains(public_alert('success', 'x'), 'role="status"'));
$check('empty state refuses a non-local link', !str_contains(public_empty_state('t', 'x', ['label' => 'L', 'href' => 'https://evil.example/']), 'evil.example'));
$check('unknown icon renders nothing', public_icon('nope') === '');
$check('page header escapes', !str_contains(public_page_header('<i>x</i>'), '<i>'));

echo "\nResult: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
