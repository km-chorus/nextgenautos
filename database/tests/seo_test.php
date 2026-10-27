<?php

declare(strict_types=1);

/**
 * Technical SEO (2026-10-27): sitemap.xml, robots.txt, page metadata, structured data and the domain redirects. CLI:
 *   php database/tests/seo_test.php
 * Database rows are created inside one transaction that is rolled back.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);
require_once $root . '/app/services/SitemapService.php';
require_once $root . '/app/services/StructuredData.php';
require_once $root . '/app/helpers/public_ui.php';

$passed = 0;
$failed = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$passed, &$failed): void {
    $ok ? $passed++ : $failed++;
    echo '  ' . ($ok ? 'PASS' : 'FAIL') . "  $label" . (!$ok && $detail !== '' ? " -> $detail" : '') . "\n";
};
$site = 'https://nextgenautosandparts.com';

echo "Official address\n";
$check('SITE_URL is the production domain, HTTPS, no www, no trailing slash', AppConfig::SITE_URL === $site);

echo "\nsitemap.xml\n";
$routes = require $root . '/app/config/public_routes.php';
// Every live, non-private route must be a deliberate choice: in the sitemap, or knowingly left out here.
$leftOut = ['google-start', 'google-callback'];
foreach ($routes['routes'] as $r) {
    if (($r['status'] ?? '') === 'live' && empty($r['private']) && !in_array($r['name'], SitemapService::INDEXABLE, true) && !in_array($r['name'], $leftOut, true)) {
        $check('every public route is either in the sitemap or knowingly left out: ' . $r['name'], false, 'add it to SitemapService::INDEXABLE or to this list');
    }
}
$pdo = Database::connection();
$pdo->beginTransaction();
try {
    $brand = (int) $pdo->query("SELECT id FROM brands WHERE slug = 'toyota'")->fetchColumn();
    $ins = $pdo->prepare("INSERT INTO vehicles (brand_id, year, model, price, currency, availability_status, is_published, slug, is_accident, damage_description) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $ins->execute([$brand, 2020, 'ZZSEO', 1000, 'GHS', 'available', 1, 'zzseo-public', 0, null]);
    $ins->execute([$brand, 2020, 'ZZSEO', 1000, 'GHS', 'available', 0, 'zzseo-unpublished', 0, null]);
    $ins->execute([$brand, 2020, 'ZZSEO', 1000, 'GHS', 'sold', 1, 'zzseo-sold', 0, null]);
    $ins->execute([$brand, 2020, 'ZZSEO', 1000, 'GHS', 'reserved', 1, 'zzseo-reserved', 0, null]);
    $ins->execute([$brand, 2020, 'ZZSEO', 1000, 'GHS', 'available', 1, 'zzseo-archived', 0, null]);
    $pdo->exec("UPDATE vehicles SET deleted_at = UTC_TIMESTAMP() WHERE slug = 'zzseo-archived'");

    $on = new SitemapService($routes, new VehicleRepository($pdo), true);
    $off = new SitemapService($routes, new VehicleRepository($pdo), false);
    $locs = array_column($on->entries(), 'loc');
    $check('homepage, Vehicles, Parts, About, Contact, Privacy and Cookies are listed', array_diff(array_map(static fn ($p) => $site . $p, ['/', '/vehicles/', '/parts/', '/about/', '/contact/', '/privacy/', '/cookies/']), $locs) === []);
    $check('a published, available or reserved vehicle is listed', in_array("$site/vehicle/zzseo-public/", $locs, true) && in_array("$site/vehicle/zzseo-reserved/", $locs, true));
    $check('unpublished, sold and archived vehicles are not listed', array_intersect(["$site/vehicle/zzseo-unpublished/", "$site/vehicle/zzseo-sold/", "$site/vehicle/zzseo-archived/"], $locs) === []);
    $private = ['/login/', '/register/', '/forgot-password/', '/reset-password/', '/track/', '/account/', '/account/settings/', '/favourites/', '/verify-email/', '/assistant/', '/auth/google/', '/brands/', '/team/', '/styleguide/'];
    $check('no private, sign-in, tracking, "coming soon" or development page; no admin', array_intersect(array_map(static fn ($p) => $site . $p, $private), $locs) === []
        && array_filter($locs, static fn (string $l): bool => str_contains($l, '/admin')) === []);
    $check('every address uses the production domain, HTTPS, ends with a slash, no query', array_filter($locs, static fn (string $l): bool => !str_starts_with($l, $site . '/') || str_contains($l, '?') || !str_ends_with($l, '/')) === []);
    $check('no address is listed twice', count($locs) === count(array_unique($locs)));
    $check('Accident Vehicles only while the page is switched on', in_array("$site/accident-vehicles/", $locs, true) && !in_array("$site/accident-vehicles/", array_column($off->entries(), 'loc'), true));
    $entries = array_column($on->entries(), 'lastmod', 'loc');
    $check('lastmod only on vehicle pages, as a real date', $entries["$site/"] === null && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $entries["$site/vehicle/zzseo-public/"]) === 1);
    $xml = $on->xml();
    $doc = new DOMDocument();
    $check('the XML parses', @$doc->loadXML($xml) === true);
    $check('sitemaps.org namespace; only url, loc and lastmod; no priority or changefreq', $doc->documentElement->namespaceURI === 'http://www.sitemaps.org/schemas/sitemap/0.9'
        && !str_contains($xml, '<priority>') && !str_contains($xml, '<changefreq>') && $doc->getElementsByTagName('url')->length === count($locs));
} finally {
    $pdo->rollBack();
}

echo "\nrobots.txt\n";
$robots = (string) file_get_contents($root . '/robots.txt');
$check('declares the sitemap at the production address', str_contains($robots, "Sitemap: $site/sitemap.xml"));
$check('blocks nothing (no Disallow rule)', preg_match('/^\s*Disallow:\s*\S/mi', $robots) !== 1 && str_contains($robots, 'User-agent: *'));
$check('exactly one robots.txt (none in other folders)', !is_file($root . '/public/robots.txt') && !is_file($root . '/assets/robots.txt'));
$check('exactly one sitemap source (no static sitemap file)', !is_file($root . '/sitemap.xml') && !is_file($root . '/public/sitemap.xml'));

echo "\nPage metadata\n";
$meta = public_meta(['title' => 'Vehicles', 'path' => '/vehicles/', 'robots' => 'index,follow', 'description' => 'D']);
$check('indexable page: canonical and og:url on the production domain', str_contains($meta, '<link rel="canonical" href="' . $site . '/vehicles/">') && str_contains($meta, '<meta property="og:url" content="' . $site . '/vehicles/">'));
$check('one title, one robots, one description; Twitter card', substr_count($meta, '<title>') === 1 && substr_count($meta, 'name="robots"') === 1 && substr_count($meta, 'name="description"') === 1 && str_contains($meta, 'twitter:card'));
$check('noindex page: no canonical link', !str_contains(public_meta(['title' => 'Sign In', 'path' => '/login/', 'robots' => 'noindex,nofollow']), 'canonical'));
$check('homepage: brand-first full title kept as given', str_contains(public_meta(['title' => 'X', 'fullTitle' => 'Brand | Y', 'path' => '/']), '<title>Brand | Y</title>'));
$check('share image: production address; summary_large_image', str_contains(public_meta(['path' => '/', 'image' => '/assets/images/hero/hero-1-desktop.webp']), '<meta property="og:image" content="' . $site . '/assets/images/hero/hero-1-desktop.webp">')
    && str_contains(public_meta(['path' => '/', 'image' => '/a.webp']), 'summary_large_image'));
$check('an odd image path or a path with ".." is ignored', !str_contains(public_meta(['path' => '/', 'image' => '/../x.webp']), 'og:image') && !str_contains(public_meta(['path' => '/', 'image' => 'https://evil.example/x.jpg']), 'og:image'));
$check('home description is under 160 characters and states real services', ($d = (string) $routes['routes'][0]['description']) !== '' && mb_strlen($d) <= 160 && str_contains($d, 'Canada') && str_contains($d, 'Ghana'));
foreach ($routes['routes'] as $r) {
    if (in_array($r['name'], SitemapService::INDEXABLE, true) && $r['name'] !== 'contact' && mb_strlen((string) ($r['description'] ?? '')) > 160) {
        $check('description too long for search results: ' . $r['name'], false);
    }
}

echo "\nStructured data\n";
$ld = public_json_ld(['@type' => 'Thing', 'name' => '</script><script>alert(1)</script> & "q"']);
$check('JSON-LD cannot close its element early (<, >, & and quotes are escaped)', substr_count($ld, '</script>') === 1 && !str_contains($ld, '<script>alert') && json_decode(substr($ld, 35, -9), true)['name'] === '</script><script>alert(1)</script> & "q"');
$contact = ['phone' => ['display' => '+1 416-577-3963', 'tel' => '+14165773963'], 'phone2' => null, 'email' => null, 'address' => 'Kumasi-Adum, Agyekum Building', 'socials' => [['url' => 'https://facebook.com/x'], ['url' => 'http://insecure.example']]];
$dealer = StructuredData::autoDealer($contact);
$check('AutoDealer: only the entered phone, address and HTTPS profiles; nothing invented (no country, hours, rating)', $dealer['telephone'] === '+14165773963' && $dealer['address']['streetAddress'] === 'Kumasi-Adum, Agyekum Building'
    && $dealer['sameAs'] === ['https://facebook.com/x'] && !isset($dealer['email'], $dealer['address']['addressCountry'], $dealer['openingHours'], $dealer['aggregateRating']));
$check('no contact details at all: just name, address of the site and logo', array_keys(StructuredData::organization(null)) === ['@type', '@id', 'name', 'url', 'logo']);
$vehicle = ['title' => '2020 Toyota Corolla', 'url' => '/vehicle/2020-toyota-corolla/', 'brand' => 'Toyota', 'year' => '2020', 'reserved' => true, 'description' => '',
    'photos' => [['large' => ['url' => '/uploads/vehicles/' . str_repeat('a', 32) . '.webp']], ['large' => ['url' => '/evil/x.webp']]]];
$car = StructuredData::car($vehicle, ['price' => '15000.00', 'currency' => 'GHS', 'mileage' => null, 'mileage_unit' => null, 'fuel' => '', 'transmission' => null, 'model' => 'Corolla']);
$check('Car: the visible price, own photos only, empty fields left out, reserved = no availability claimed', $car['offers']['price'] === '15000.00' && $car['offers']['priceCurrency'] === 'GHS'
    && count($car['image']) === 1 && !isset($car['fuelType'], $car['mileageFromOdometer'], $car['description'], $car['offers']['availability']));
$check('an available vehicle is "InStock"', StructuredData::car(['reserved' => false] + $vehicle, ['price' => '1', 'currency' => 'GHS', 'mileage' => 100, 'mileage_unit' => 'km', 'fuel' => 'Petrol', 'transmission' => 'Automatic', 'model' => 'C'])['offers']['availability'] === 'https://schema.org/InStock');

echo "\nDomain redirects (.htaccess)\n";
$ht = (string) file_get_contents($root . '/.htaccess');
$check('www goes to the official address (301)', str_contains($ht, 'RewriteCond %{HTTP_HOST} ^www\.nextgenautosandparts\.com$ [NC]') && str_contains($ht, 'RewriteRule ^ https://nextgenautosandparts.com%{REQUEST_URI} [R=301,L,NE]'));
$check('http goes to https on the official domain only (local copies unaffected)', str_contains($ht, 'RewriteCond %{HTTP_HOST} ^nextgenautosandparts\.com$ [NC]') && str_contains($ht, 'RewriteCond %{HTTPS} !=on'));
$check('certificate renewal (/.well-known/) is never redirected', substr_count($ht, 'RewriteCond %{REQUEST_URI} !^/\.well-known/') === 2);
$check('the redirects come before the front-controller rule', strpos($ht, 'R=301') < strpos($ht, 'RewriteRule ^ index.php'));

echo "\nResult: $passed passed, $failed failed\n";
exit($failed > 0 ? 1 : 0);
