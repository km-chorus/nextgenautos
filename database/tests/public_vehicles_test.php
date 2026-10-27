<?php

declare(strict_types=1);

/**
 * Logic tests for the public vehicle search (spec 10B cards, homepage search and the Vehicles page):
 * VehicleRepository::publicSearch/publicCount/publicFilterValues, PublicVehicleService and PublicVehicleSearchValidator. CLI only:  php database/tests/public_vehicles_test.php
 *
 * Database rows are created inside ONE transaction that is rolled back (real vehicles are never
 * changed). Image files go to a temporary folder, never to uploads/. Everything is generated here
 * and clearly named TESTDATA; nothing remains afterwards.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/app/services/PublicVehicleService.php';

$passed = 0;
$failed = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$passed, &$failed): void {
    if ($ok) {
        $passed++;
        echo "  PASS  $label\n";
    } else {
        $failed++;
        echo "  FAIL  $label" . ($detail !== '' ? " -> $detail" : '') . "\n";
    }
};

$pdo = Database::connection();
$vehicles = new VehicleRepository($pdo);
$images = new VehicleImageRepository($pdo);
$root = sys_get_temp_dir() . '/pv_test_' . bin2hex(random_bytes(4));
mkdir($root . '/uploads/vehicles', 0777, true);
$service = new PublicVehicleService($vehicles, $images, $root, new BrandRepository($pdo));
$none = ['q' => null, 'brand' => null, 'model' => null, 'year' => null, 'min_price' => null, 'max_price' => null, 'availability' => null, 'sort' => 'newest', 'page' => 1];
$feat = static fn (int $n = 4, array $f = []) => $service->search($f + $none, $n)['cards'];
$brand = (int) $pdo->query("SELECT id FROM brands WHERE slug = 'toyota'")->fetchColumn();
$mine = static fn (array $cards): array => array_values(array_filter($cards, static fn (array $c): bool => str_contains($c['title'], 'TESTDATA')));
$minutesAgo = 0;

$add = static function (array $v) use ($pdo, $brand, &$minutesAgo): int {
    $v += ['year' => 2021, 'model' => 'TESTDATA', 'trim' => null, 'engine' => null, 'trans' => null, 'drive' => null, 'fuel' => null, 'status' => 'available', 'published' => 1, 'archived' => false, 'price' => '1234.50', 'currency' => 'CAD', 'slug' => null, 'age' => null];
    $slug = $v['slug'] ?? 'testdata-' . bin2hex(random_bytes(4));
    $age = $v['age'] ?? (--$minutesAgo);   // each new row is newer than the previous one, unless told otherwise
    $pdo->prepare('INSERT INTO vehicles (brand_id, year, model, trim, engine_size, transmission, drive_type, fuel_type, price, currency, shipping_info, availability_status, is_published, slug, deleted_at, created_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, DATE_ADD(NOW(), INTERVAL ? MINUTE))')
        ->execute([$brand, $v['year'], $v['model'], $v['trim'], $v['engine'], $v['trans'], $v['drive'], $v['fuel'], $v['price'], $v['currency'], 'INTERNAL-SHIPPING-NOTE', $v['status'], $v['published'], $slug, $v['archived'] ? '2026-01-01 00:00:00' : null, $age + 100000]);

    return (int) $pdo->lastInsertId();
};
$image = static function (int $vehicleId, string $path, bool $primary, ?string $alt = null) use ($pdo): void {
    $pdo->prepare('INSERT INTO vehicle_images (vehicle_id, file_path, alt_text, display_order, is_primary) VALUES (?,?,?,?,?)')->execute([$vehicleId, $path, $alt, 1, $primary ? 1 : 0]);
};
$webp = static function (string $file, int $w, int $h) use ($root): void {
    $im = imagecreatetruecolor($w, $h);
    imagewebp($im, $root . '/' . $file, 60);
};

$pdo->beginTransaction();
try {
    echo "Eligibility (public visibility rules)\n";
    $ok = $add(['model' => 'TESTDATA Available']);
    $reserved = $add(['model' => 'TESTDATA Reserved', 'status' => 'reserved']);
    $add(['model' => 'TESTDATA Sold', 'status' => 'sold']);
    $add(['model' => 'TESTDATA Unavailable', 'status' => 'unavailable']);
    $add(['model' => 'TESTDATA Unpublished', 'published' => 0]);
    $add(['model' => 'TESTDATA Archived', 'archived' => true]);
    $titles = array_column($mine($feat(12)), 'title');
    $check('available and reserved vehicles are shown', in_array('2021 Toyota TESTDATA Available', $titles, true) && in_array('2021 Toyota TESTDATA Reserved', $titles, true));
    $check('sold, unavailable, unpublished and archived vehicles are not shown', count($titles) === 2, implode('|', $titles));
    $cards = $mine($feat(12));
    $byTitle = array_column($cards, null, 'title');
    $check('reserved is labelled, available is labelled', $byTitle['2021 Toyota TESTDATA Reserved']['status'] === 'Reserved' && $byTitle['2021 Toyota TESTDATA Reserved']['reserved'] === true && $byTitle['2021 Toyota TESTDATA Available']['status'] === 'Available');

    echo "\nLimit and order\n";
    for ($i = 1; $i <= 6; $i++) {
        $add(['model' => "TESTDATA Order $i", 'age' => 5000 - $i]);   // newest = highest number? created later => larger age value
    }
    $four = $feat();
    $check('default is 4 vehicles', count($four) === 4, (string) count($four));
    $check('page size is clamped (0 becomes 1, 99 becomes at most 48)', count($feat(0)) === 1 && count($feat(99)) <= 48);
    $check('newest first (created_at DESC)', $four[0]['title'] === '2021 Toyota TESTDATA Order 1', $four[0]['title']);
    $check('no duplicate cards', count(array_unique(array_column($four, 'url'))) === count($four));

    echo "\nCard content (customer-facing only)\n";
    $add(['model' => 'TESTDATA Full', 'trim' => 'XLE', 'engine' => '2.5L', 'trans' => 'Automatic', 'drive' => 'AWD', 'fuel' => 'Gasoline', 'price' => '28000.00', 'currency' => 'CAD', 'age' => 9000]);
    $add(['model' => 'TESTDATA Bare', 'age' => 8999]);
    $add(['model' => 'TESTDATA OnlyEngine', 'engine' => '1.8L', 'age' => 8998]);
    $add(['model' => 'TESTDATA OnlyTrans', 'trans' => 'Manual', 'age' => 8997]);
    $add(['model' => 'TESTDATA Ghs', 'price' => '12388.00', 'currency' => 'GHS', 'age' => 8996]);
    $all = array_column($mine($feat(12)), null, 'title');
    $check('full title is year, brand, model and trim', isset($all['2021 Toyota TESTDATA Full XLE']));
    $full = $all['2021 Toyota TESTDATA Full XLE'];
    $check('year, brand and name are separate fields ("2021", "Toyota", "TESTDATA Full XLE")', $full['year'] === '2021' && $full['brand'] === 'Toyota' && $full['name'] === 'TESTDATA Full XLE');
    $check('spec line uses " · " and at most the first three details that exist (engine, transmission, drive; fuel dropped)', $full['spec'] === '2.5L · Automatic · AWD', (string) $full['spec']);
    $bare = $all['2021 Toyota TESTDATA Bare'];
    $check('missing trim: the name is just the model; missing specs are null, never invented', $bare['name'] === 'TESTDATA Bare' && $bare['spec'] === null);
    $check('only the part that exists is shown (engine only, transmission only)', ($all['2021 Toyota TESTDATA OnlyEngine']['spec'] ?? '') === '1.8L' && ($all['2021 Toyota TESTDATA OnlyTrans']['spec'] ?? '') === 'Manual');
    $check('price is shown as stored with its currency, not converted (whole amounts without cents)', $full['price'] === 'CAD 28,000' && $all['2021 Toyota TESTDATA Ghs']['price'] === 'GHS 12,388');
    $add(['model' => 'TESTDATA Cents', 'price' => '9999.50', 'currency' => 'CAD', 'age' => 8995]);
    $cents = array_column($mine($feat(12)), null, 'title')['2021 Toyota TESTDATA Cents'];
    $pdo->exec("DELETE FROM vehicles WHERE model = 'TESTDATA Cents'");   // keep the 12-row window free for the image cases below
    $check('a price with cents keeps them (stored value is never rounded)', $cents['price'] === 'CAD 9,999.50', $cents['price']);
    $check('link is the SEO slug route', preg_match('#^/vehicle/testdata-[a-f0-9]{8}/$#', $full['url']) === 1, $full['url']);
    $keys = array_keys($full);
    sort($keys);
    $check('only customer-facing fields leave the service (no id, notes, shipping, timestamps)', $keys === ['accident', 'alt', 'brand', 'duty', 'gallery', 'image', 'name', 'price', 'reserved', 'slug', 'spec', 'specs', 'status', 'title', 'url', 'year'], implode(',', $keys));
    $check('internal notes never appear in the data', !str_contains(json_encode($cards), 'INTERNAL-SHIPPING-NOTE'));

    echo "\nImages\n";
    $hexOk = bin2hex(random_bytes(16));
    $webp("uploads/vehicles/$hexOk-t.webp", 640, 480);
    $webp("uploads/vehicles/$hexOk.webp", 1600, 1200);
    $withImg = $add(['model' => 'TESTDATA WithImage', 'age' => 7000]);
    $image($withImg, "uploads/vehicles/$hexOk.webp", true, 'Front view');
    $hexMissing = bin2hex(random_bytes(16));
    $missing = $add(['model' => 'TESTDATA MissingFile', 'age' => 6999]);
    $image($missing, "uploads/vehicles/$hexMissing.webp", true);
    $hostile = $add(['model' => 'TESTDATA Hostile', 'age' => 6998]);
    $image($hostile, 'uploads/vehicles/../../.env', true);
    $httpImg = $add(['model' => 'TESTDATA Remote', 'age' => 6997]);
    $image($httpImg, 'https://evil.example/x.webp', true);
    $notWebp = bin2hex(random_bytes(16));
    file_put_contents($root . "/uploads/vehicles/$notWebp-t.webp", '<?php echo 1;');
    $fake = $add(['model' => 'TESTDATA FakeWebp', 'age' => 6996]);
    $image($fake, "uploads/vehicles/$notWebp.webp", true);
    $secondary = bin2hex(random_bytes(16));
    $webp("uploads/vehicles/$secondary-t.webp", 640, 480);
    $noPrimary = $add(['model' => 'TESTDATA NoPrimary', 'age' => 6995]);
    $image($noPrimary, "uploads/vehicles/$secondary.webp", false);
    $multi = $add(['model' => 'TESTDATA Multi', 'age' => 6994]);
    $hexA = bin2hex(random_bytes(16));
    $hexB = bin2hex(random_bytes(16));
    foreach ([$hexA, $hexB] as $h) {
        $webp("uploads/vehicles/$h-t.webp", 640, 480);
    }
    $image($multi, "uploads/vehicles/$hexA.webp", false);
    $image($multi, "uploads/vehicles/$hexB.webp", true);
    $all = array_column($mine($feat(12)), null, 'title');
    $img = $all['2021 Toyota TESTDATA WithImage'];
    $check('primary image: the stored 640 px thumbnail, with its real dimensions', $img['image'] === ['url' => "/uploads/vehicles/$hexOk-t.webp", 'width' => 640, 'height' => 480]);
    $check('alt text uses the stored alt, otherwise the vehicle name', $img['alt'] === 'Front view' && $all['2021 Toyota TESTDATA MissingFile']['alt'] === '2021 Toyota TESTDATA MissingFile');
    $check('missing image file gives no image (placeholder)', $all['2021 Toyota TESTDATA MissingFile']['image'] === null);
    $check('path with ".." gives no image', $all['2021 Toyota TESTDATA Hostile']['image'] === null);
    $check('remote URL in the database gives no image', $all['2021 Toyota TESTDATA Remote']['image'] === null);
    $check('a file that is not a real WebP gives no image', $all['2021 Toyota TESTDATA FakeWebp']['image'] === null);
    $check('vehicle with images but no primary gets the placeholder', $all['2021 Toyota TESTDATA NoPrimary']['image'] === null);
    $check('several images: only the primary is used', $all['2021 Toyota TESTDATA Multi']['image']['url'] === "/uploads/vehicles/$hexB-t.webp");
    $check('vehicle with no images at all gets the placeholder', $all['2021 Toyota TESTDATA Full XLE']['image'] === null);

    echo "\nSearch filters (real SQL, bound values)\n";
    $hondaId = (int) $pdo->query("SELECT id FROM brands WHERE slug = 'honda'")->fetchColumn();
    $ins = $pdo->prepare('INSERT INTO vehicles (brand_id, year, model, price, currency, availability_status, is_published, slug, created_at) VALUES (?,?,?,?,?,?,?,?, DATE_ADD(NOW(), INTERVAL 200000 MINUTE))');
    $ins->execute([$hondaId, 2019, 'TESTDATA Filter Civic', '9000.00', 'GHS', 'available', 1, 'testdata-f-civic']);
    $ins->execute([$brand, 2024, 'TESTDATA Filter Rav', '45000.00', 'GHS', 'available', 1, 'testdata-f-rav']);
    $ins->execute([$brand, 2024, 'TESTDATA Filter CadOnly', '1000.00', 'CAD', 'available', 1, 'testdata-f-cad']);
    $found = static function (array $f) use ($service, $none): array {
        $names = array_column(array_filter($service->search($f + $none, 48)['cards'], static fn (array $c): bool => str_contains($c['title'], 'TESTDATA Filter')), 'name');
        sort($names);

        return $names;
    };
    $check('no filter: all three eligible test vehicles', count($found([])) === 3);
    $check('brand filter (slug)', $found(['brand' => 'honda']) === ['TESTDATA Filter Civic'] && count($found(['brand' => 'toyota'])) === 2);
    $check('model filter (exact, case-insensitive)', $found(['model' => 'testdata filter rav']) === ['TESTDATA Filter Rav'] && $found(['model' => 'TESTDATA Filter']) === []);
    $check('year filter', $found(['year' => 2019]) === ['TESTDATA Filter Civic'] && count($found(['year' => 2024])) === 2);
    $check('price filter is "up to" in GHS and never matches a vehicle priced in another currency', $found(['max_price' => 10000]) === ['TESTDATA Filter Civic'] && $found(['max_price' => 50000]) === ['TESTDATA Filter Civic', 'TESTDATA Filter Rav'] && !in_array('TESTDATA Filter CadOnly', $found(['max_price' => 999999]), true));
    $check('price boundary is inclusive', $found(['max_price' => 9000]) === ['TESTDATA Filter Civic'] && $found(['max_price' => 8999]) === []);
    $check('filters combine with AND', $found(['brand' => 'toyota', 'year' => 2019]) === [] && $found(['brand' => 'honda', 'year' => 2019, 'max_price' => 10000]) === ['TESTDATA Filter Civic']);
    $check('count and page agree', $service->search(['model' => 'TESTDATA Filter Civic'] + $none)['total'] === 1);
    $safe = true;
    foreach (["' OR 1=1 -- ", "x'; DROP TABLE vehicles; --", '%', '_', '\\'] as $evil) {
        $safe = $safe && $service->search(['model' => $evil, 'brand' => 'toyota'] + $none)['total'] === 0;
    }
    $check('hostile text in filters is plain data (no SQL effect, no LIKE wildcards, nothing matches)', $safe && (int) $pdo->query('SELECT COUNT(*) FROM vehicles')->fetchColumn() > 0);
    $pager = $service->search($none, 2);
    $last = $service->search(['page' => 9999] + $none, 2);
    $check('pagination: total, pages, and a too-high page is clamped to the last page', $pager['pages'] === (int) ceil($pager['total'] / 2) && $last['page'] === $last['pages'] && $last['cards'] !== []);
    $opts = $service->searchOptions();
    $activeSlugs = array_map(static fn (array $b): string => (string) $b['slug'], (new BrandRepository($pdo))->search('', 'active'));
    $check('options: active brands from the Brands table, in the Brands order', array_column($opts['brands'], 'slug') === $activeSlugs && count($opts['brands']) >= 1);
    $allModels = array_merge(...array_column($opts['models'], 'models'));
    $check('options: models are grouped by brand and come from vehicles on offer (no sold or draft model, none invented)', in_array('TESTDATA Filter Civic', $opts['models']['honda']['models'] ?? [], true) && !in_array('TESTDATA Sold', $allModels, true) && !in_array('TESTDATA Unpublished', $allModels, true) && ($opts['models']['honda']['name'] ?? '') === 'Honda');
    $sorted = $opts['years'];
    rsort($sorted);
    $check('options: years are the real years, newest first, no duplicates', in_array(2024, $opts['years'], true) && in_array(2019, $opts['years'], true) && $opts['years'] === $sorted && count($sorted) === count(array_unique($sorted)));
    $check('options: prices are round "up to" steps ending at the first step that covers the highest GHS price', $opts['prices'] !== [] && end($opts['prices']) >= 45000 && $opts['prices'][0] === 5000);

    echo "\nQuery string validation\n";
    $v = static fn (array $q): array => PublicVehicleSearchValidator::fromQuery($q);
    $check('valid values pass', $v(['brand' => 'toyota', 'model' => 'RAV4', 'year' => '2024', 'max_price' => '50000', 'page' => '3']) === ['q' => null, 'brand' => 'toyota', 'model' => 'RAV4', 'year' => 2024, 'min_price' => null, 'max_price' => 50000, 'availability' => null, 'sort' => 'newest', 'page' => 3]);
    $check('empty form values mean "no filter"', $v(['brand' => '', 'model' => '', 'year' => '', 'max_price' => '']) === $none);
    $check('bad brand slugs are dropped', $v(['brand' => 'Toy ota'])['brand'] === null && $v(['brand' => '../x'])['brand'] === null && $v(['brand' => 'UPPER'])['brand'] === null && $v(['brand' => str_repeat('a', 101)])['brand'] === null);
    $check('bad years are dropped', $v(['year' => '1899'])['year'] === null && $v(['year' => '2101'])['year'] === null && $v(['year' => '20x4'])['year'] === null && $v(['year' => '-2024'])['year'] === null);
    $check('bad prices are dropped (zero, negative, decimals, huge, text)', $v(['max_price' => '0'])['max_price'] === null && $v(['max_price' => '-5'])['max_price'] === null && $v(['max_price' => '10.5'])['max_price'] === null && $v(['max_price' => '99999999999'])['max_price'] === null && $v(['max_price' => '1e5'])['max_price'] === null);
    $check('arrays, control characters, bad encoding and over-long models are dropped', $v(['brand' => ['a'], 'model' => ['x']])['model'] === null && $v(['model' => "a\0b"])['model'] === null && $v(['model' => str_repeat('m', 101)])['model'] === null && $v(['model' => "bad\xFF"])['model'] === null);
    $check('page is a whole number from 1 to 500', $v(['page' => '0'])['page'] === 1 && $v(['page' => 'abc'])['page'] === 1 && $v(['page' => '-3'])['page'] === 1 && $v(['page' => '9999'])['page'] === 500);
    $check('hasFilters ignores the page number', !PublicVehicleSearchValidator::hasFilters($v(['page' => '4'])) && PublicVehicleSearchValidator::hasFilters($v(['year' => '2020'])));

    echo "\nQuery count (no N+1)\n";
    $selects = static fn (): int => (int) $pdo->query("SHOW SESSION STATUS LIKE 'Com_select'")->fetch(PDO::FETCH_NUM)[1];
    $before = $selects();
    $feat(12);
    $used = $selects() - $before;
    $check('a page of 12 vehicles with images uses exactly 3 SELECT queries (count, vehicles, primary images)', $used === 3, (string) $used);
    $before = $selects();
    $feat(1);
    $check('1 vehicle also uses 3 queries (no N+1)', $selects() - $before === 3);
    $before = $selects();
    $service->searchOptions();
    $check('the search form options use 4 SELECT queries (active brands, models, years, highest GHS price)', $selects() - $before === 4, (string) ($selects() - $before));

    echo "\nMalformed data and hostile values\n";
    $stub = new class ($pdo) extends VehicleRepository {
        public function publicCount(array $filters): int
        {
            return 4;
        }

        public function publicSearch(array $filters, int $limit, int $offset): array
        {
            return [
                ['id' => 900001, 'brand_name' => 'Toyota', 'year' => 2020, 'model' => 'Bad Slug', 'trim' => null, 'engine_size' => null, 'transmission' => null, 'drive_type' => null, 'fuel_type' => null, 'price' => '1.00', 'currency' => 'CAD', 'duty_included' => 0, 'availability_status' => 'available', 'slug' => '../admin/login.php'],
                ['id' => 900002, 'brand_name' => 'Toyota', 'year' => 2020, 'model' => 'Upper', 'trim' => null, 'engine_size' => null, 'transmission' => null, 'drive_type' => null, 'fuel_type' => null, 'price' => '1.00', 'currency' => 'CAD', 'duty_included' => 0, 'availability_status' => 'available', 'slug' => 'Upper-Case'],
                ['id' => 900003, 'brand_name' => 'Toyota', 'year' => 2020, 'model' => 'Long', 'trim' => null, 'engine_size' => null, 'transmission' => null, 'drive_type' => null, 'fuel_type' => null, 'price' => '1.00', 'currency' => 'CAD', 'duty_included' => 0, 'availability_status' => 'available', 'slug' => str_repeat('a', 161)],
                ['id' => 900004, 'brand_name' => 'Toyota', 'year' => 2020, 'model' => '<script>alert(1)</script>', 'trim' => '  ', 'engine_size' => '', 'transmission' => '', 'drive_type' => null, 'fuel_type' => '', 'price' => '1.00', 'currency' => 'CAD', 'duty_included' => 0, 'availability_status' => 'available', 'slug' => 'fine-slug'],
            ];
        }
    };
    $cards = (new PublicVehicleService($stub, $images, $root))->search($none)['cards'];
    $check('rows with a malformed slug are skipped, never linked', count($cards) === 1 && $cards[0]['url'] === '/vehicle/fine-slug/');
    $check('blank trim, engine and transmission are treated as missing', $cards[0]['name'] === '<script>alert(1)</script>' && $cards[0]['spec'] === null);
    $check('markup in a model name is returned as plain text for the view to escape', str_contains($cards[0]['title'], '<script>'));
    $empty = new class ($pdo) extends VehicleRepository {
        public function publicCount(array $filters): int
        {
            return 0;
        }

        public function publicSearch(array $filters, int $limit, int $offset): array
        {
            return [];
        }
    };
    $check('no eligible vehicles gives an empty list (no placeholders, no fake cards)', (new PublicVehicleService($empty, $images, $root))->search($none)['cards'] === []);

    echo "\nSQL safety\n";
    $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/repositories/VehicleRepository.php');
    $start = strpos($src, 'function publicSearch');
    $body = substr($src, $start, 900);
    $check('publicSearch uses a prepared statement with bound LIMIT/OFFSET and no SELECT *', str_contains($body, ':limit') && str_contains($body, 'bindValue') && str_contains($src, 'private const PUBLIC_COLUMNS') && !preg_match('/SELECT\s+\*/i', $body) && !preg_match('/v\.description|shipping_info|key_features|enquir|shipment/i', substr($src, (int) strpos($src, 'PUBLIC_COLUMNS = '), 300)));
    $view = (string) file_get_contents(dirname(__DIR__, 2) . '/public/views/home.php');
    $check('the homepage template contains no SQL or database calls', !preg_match('/SELECT |PDO|Database::|->prepare\(/i', $view));
} finally {
    $pdo->rollBack();
    foreach (glob($root . '/uploads/vehicles/*') ?: [] as $f) {
        unlink($f);
    }
    rmdir($root . '/uploads/vehicles');
    rmdir($root . '/uploads');
    rmdir($root);
}

$left = (int) $pdo->query("SELECT COUNT(*) FROM vehicles WHERE model LIKE 'TESTDATA%'")->fetchColumn();
$check('rolled back: no test vehicle remains in the database', $left === 0);

echo "\nResult: $passed passed, $failed failed\n";
exit($failed > 0 ? 1 : 0);
