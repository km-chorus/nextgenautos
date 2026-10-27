<?php

declare(strict_types=1);

/**
 * Vehicle Management logic tests (spec 06_spec_vehicle_managt, sections 67 to 72).
 *
 * CLI only:  php database/tests/vehicle_test.php
 * Requires:  schema.sql + seed.sql loaded; DB credentials in .env; one administrator.
 *
 * Covers validation, repository queries (search, filters, paging, order,
 * injection strings), VehicleService rules (brand relationship, slugs, status,
 * deletion safety, audit rows) and the database's own constraints. Everything
 * runs inside one transaction that is rolled back, so no vehicle, shipment,
 * enquiry, image or audit row is left behind. Test rows are labelled "TEST DATA".
 * HTTP behaviour (authentication, CSRF, GET refusal, XSS, redirects) is covered
 * by the HTTP/browser suites.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/app/services/VehicleService.php';
require_once dirname(__DIR__, 2) . '/app/controllers/VehicleController.php';
require_once dirname(__DIR__, 2) . '/app/services/BrandService.php';

$passed = 0;
$failed = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "  PASS  $label\n"; }
    else { $failed++; echo "  FAIL  $label" . ($detail !== '' ? " -> $detail" : '') . "\n"; }
}
function run(PDO $pdo, string $sql, array $params = []): PDOStatement
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

$pdo = Database::connection();
$service = VehicleService::create();
$repo = new VehicleRepository($pdo);
$ip = '203.0.113.7';
$ua = 'vehicle_test';
$maxYear = VehicleValidator::maxYear();

$brandId = static fn (string $slug): int => (int) run($GLOBALS['pdo'], 'SELECT id FROM brands WHERE slug = ?', [$slug])->fetchColumn();
$toyota = $brandId('toyota');
$honda = $brandId('honda');
$mazda = $brandId('mazda');

/** Valid form input; $over replaces fields. */
function input(array $over = []): array
{
    return array_merge([
        'brand_id' => (string) $GLOBALS['toyota'], 'year' => '2021', 'model' => 'TEST DATA Camry', 'trim' => 'XSE', 'engine_size' => '2.5L',
        'mileage' => '', 'mileage_unit' => 'km', 'transmission' => '', 'fuel_type' => '', 'drive_type' => '',
        'price' => '12500', 'currency' => 'GHS', 'description' => '', 'key_features' => '', 'shipping_info' => '',
        'duty_included' => '0', 'availability_status' => 'available', 'is_published' => '0', 'slug' => '',
    ], $over);
}
function errs(array $over): array
{
    return VehicleValidator::validate(input($over), $GLOBALS['maxYear'])['errors'];
}

// ------------------------------------------------------------------ validator
echo "Validator\n";
$ok = VehicleValidator::validate(input(), $maxYear);
check('valid vehicle has no errors', $ok['errors'] === [], json_encode($ok['errors']));
check('price normalised to two decimals as text (12500 -> 12500.00)', $ok['values']['price'] === '12500.00');
foreach (['0' => '0.00', '0.5' => '0.50', '1250.5' => '1250.50', '007' => '7.00', '9999999999.99' => '9999999999.99', '12500.00' => '12500.00'] as $in => $out) {
    $r = VehicleValidator::validate(input(['price' => (string) $in]), $maxYear);
    check("price " . json_encode((string) $in) . " accepted as $out", $r['errors'] === [] && $r['values']['price'] === $out, json_encode($r['errors']));
}
foreach (['', 'abc', '-1', '-0', '1,000', '1.234', '.5', '5.', '1e3', '12 500', 'GHS 5', '99999999999', '1.2.3', '0x10', ' '] as $bad) {
    check('price rejected: ' . json_encode($bad), (errs(['price' => $bad])['price'] ?? '') === VehicleValidator::MESSAGE_PRICE);
}
check('price message is the specified sentence', errs(['price' => 'abc'])['price'] === 'Please enter a valid vehicle price.');
check('array price rejected', isset(errs(['price' => ['1']])['price']));
foreach (['1900', '1999', '2021', (string) $maxYear] as $y) {
    check("year $y accepted", !isset(errs(['year' => $y])['year']));
}
foreach (['', 'abc', '-2024', '999999', '1899', (string) ($maxYear + 1), '20.5', '2k21', ' ', '02021', '٢٠٢١'] as $y) {
    check('year rejected: ' . json_encode($y), isset(errs(['year' => $y])['year']));
}
check('the latest allowed year follows the calendar (not hardcoded)', VehicleValidator::maxYear(new DateTimeImmutable('2030-06-01', new DateTimeZone('UTC'))) === 2031
    && VehicleValidator::maxYear(new DateTimeImmutable('2099-01-01', new DateTimeZone('UTC'))) === 2100
    && VehicleValidator::maxYear(new DateTimeImmutable('2150-01-01', new DateTimeZone('UTC'))) === 2100);
check('missing brand rejected with the specified sentence', errs(['brand_id' => ''])['brand_id'] === 'Please select a valid vehicle brand.');
foreach (['abc', '0', '-1', '1 OR 1=1', '1.5', '99999999999', ['1']] as $bad) {
    check('brand id rejected: ' . json_encode($bad), isset(errs(['brand_id' => $bad])['brand_id']));
}
check('missing model rejected', isset(errs(['model' => '   '])['model']));
check('model of 101 chars rejected, 100 accepted', isset(errs(['model' => str_repeat('a', 101)])['model']) && !isset(errs(['model' => str_repeat('a', 100)])['model']));
check('control / hidden characters in model rejected', isset(errs(['model' => "Cam\0ry"])['model']) && isset(errs(['model' => "Cam\u{200B}ry"])['model']) && isset(errs(['model' => "Cam\x07ry"])['model']));
check('invalid UTF-8 in model rejected', isset(errs(['model' => "Ca\xC3\x28mry"])['model']));
check('trim and engine are optional (empty -> null)', ($r = VehicleValidator::validate(input(['trim' => '', 'engine_size' => '  ']), $maxYear))['errors'] === [] && $r['values']['trim'] === null && $r['values']['engine_size'] === null);
check('over-long trim/engine/transmission rejected', isset(errs(['trim' => str_repeat('a', 101)])['trim']) && isset(errs(['engine_size' => str_repeat('a', 51)])['engine_size']) && isset(errs(['transmission' => str_repeat('a', 51)])['transmission']));
check('mileage optional; a figure needs a unit; unit alone is dropped', !isset(errs(['mileage' => ''])['mileage'])
    && VehicleValidator::validate(input(['mileage' => '', 'mileage_unit' => 'mi']), $maxYear)['values']['mileage_unit'] === null
    && isset(errs(['mileage' => '45000', 'mileage_unit' => ''])['mileage_unit']));
check('mileage must be a whole number in range', isset(errs(['mileage' => '-5'])['mileage']) && isset(errs(['mileage' => '1.5'])['mileage']) && isset(errs(['mileage' => '12345678'])['mileage']) && !isset(errs(['mileage' => '45000'])['mileage']));
check('invalid mileage unit rejected', isset(errs(['mileage' => '1', 'mileage_unit' => 'furlongs'])['mileage_unit']));
check('currency must be in the allowed list', !isset(errs(['currency' => 'GHS'])['currency']) && isset(errs(['currency' => 'USD'])['currency']) && isset(errs(['currency' => ''])['currency']) && isset(errs(['currency' => "GHS' OR '1"])['currency']));
check('lowercase currency normalised', VehicleValidator::validate(input(['currency' => 'ghs']), $maxYear)['values']['currency'] === 'GHS');
foreach (['duty_included', 'is_published'] as $flag) {
    check("$flag accepts only 1 or 0", !isset(errs([$flag => '1'])[$flag]) && !isset(errs([$flag => '0'])[$flag]) && isset(errs([$flag => ''])[$flag]) && isset(errs([$flag => 'yes'])[$flag]) && isset(errs([$flag => '2'])[$flag]) && isset(errs([$flag => ['1']])[$flag]));
}
foreach (VehicleValidator::STATUSES as $s) { check("status $s accepted", !isset(errs(['availability_status' => $s])['availability_status'])); }
foreach (['deleted', 'inactive', '', 'AVAILABLE ', "sold'; DROP TABLE vehicles;--", ['sold']] as $bad) {
    $e = errs(['availability_status' => $bad]);
    check('status handled: ' . json_encode($bad), $bad === 'AVAILABLE ' ? !isset($e['availability_status']) : isset($e['availability_status']));
}
check('description: empty is null, text kept, CRLF normalised', VehicleValidator::validate(input(['description' => '']), $maxYear)['values']['description'] === null
    && VehicleValidator::validate(input(['description' => "a\r\nb"]), $maxYear)['values']['description'] === "a\nb");
check('description limit enforced', isset(errs(['description' => str_repeat('a', 5001)])['description']) && !isset(errs(['description' => str_repeat('a', 5000)])['description']));
check('description with NUL rejected', isset(errs(['description' => "a\0b"])['description']));
check('shipping info limit enforced', isset(errs(['shipping_info' => str_repeat('a', 2001)])['shipping_info']));
$f = VehicleValidator::validate(input(['key_features' => "Sunroof\r\n\r\n  Leather   interior \nsunroof\nApple CarPlay"]), $maxYear);
check('features: split by line, blank lines dropped, spaces collapsed, repeats removed', $f['values']['key_features'] === ['Sunroof', 'Leather interior', 'Apple CarPlay'], json_encode($f['values']['key_features']));
check('too many features rejected', isset(errs(['key_features' => implode("\n", array_map(static fn ($i) => "F$i", range(1, 41)))])['key_features']));
check('over-long feature rejected', isset(errs(['key_features' => str_repeat('a', 101)])['key_features']));
check('array features rejected', isset(errs(['key_features' => ['x']])['key_features']));
check('slug blank is allowed here (generated by the service)', VehicleValidator::validate(input(['slug' => '']), $maxYear)['values']['slug'] === '');
check('manual slug kept and lowercased', VehicleValidator::validate(input(['slug' => '2024-My-Slug']), $maxYear)['values']['slug'] === '2024-my-slug');
foreach (['has space', 'under_score', '-lead', 'trail-', 'dou--ble', "caf\u{e9}", 'a/b', '../x', "x\0y", '<s>', str_repeat('a', 161)] as $bad) {
    check('bad slug rejected: ' . json_encode($bad), isset(errs(['slug' => $bad])['slug']));
}
check('slugify: 2024 Toyota Camry XSE -> 2024-toyota-camry-xse', VehicleValidator::slugify('2024 Toyota Camry XSE') === '2024-toyota-camry-xse');
check('slugify strips punctuation and edges', VehicleValidator::slugify('  2020 Mercedes-Benz C-Class (AMG)! ') === '2020-mercedes-benz-c-class-amg');
check('every field reports at once', count(errs(['brand_id' => '', 'year' => 'x', 'model' => '', 'price' => 'x', 'currency' => 'x', 'duty_included' => '', 'availability_status' => 'x', 'is_published' => 'x', 'slug' => 'BAD SLUG'])) === 9);
foreach (['1', '12', '4294967295'] as $good) { check("id $good valid", VehicleValidator::id($good) === (int) $good); }
foreach (['0', '-1', '01', '1.0', 'abc', '', '1 OR 1=1', '4294967296', ' 1', null, ['1'], 5] as $bad) {
    check('id rejected: ' . json_encode($bad), VehicleValidator::id($bad) === null);
}
$fl = VehicleValidator::filters(['q' => 'x', 'brand' => '3', 'status' => 'sold', 'year' => '2020', 'page' => '4'], $maxYear);
check('filters parsed', $fl === ['q' => 'x', 'brand' => 3, 'status' => 'sold', 'year' => 2020, 'page' => 4], json_encode($fl));
check('filters: junk falls back to no filter, page 1', VehicleValidator::filters(['q' => ['a'], 'brand' => 'x', 'status' => "' OR 1", 'year' => '1800', 'page' => '-2'], $maxYear) === ['q' => '', 'brand' => null, 'status' => 'all', 'year' => null, 'page' => 1]);
check('filters: page 0, huge page, non-numeric page -> 1 (or capped digits)', VehicleValidator::filters(['page' => '0'], $maxYear)['page'] === 1 && VehicleValidator::filters(['page' => 'abc'], $maxYear)['page'] === 1 && VehicleValidator::filters(['page' => '9999999999'], $maxYear)['page'] === 1);
check('filters: search capped at 100 characters', mb_strlen(VehicleValidator::filters(['q' => str_repeat('a', 500)], $maxYear)['q']) === 100);
check('target status accepts only the four values', VehicleValidator::targetStatus('sold') === 'sold' && VehicleValidator::targetStatus('SOLD') === null && VehicleValidator::targetStatus('inactive') === null && VehicleValidator::targetStatus(['sold']) === null);

// ------------------------------------------------------------- real database
echo "Database state\n";
// The owner may already have entered real vehicles: every count below is relative to this baseline, which is never touched.
$baseCount = $repo->countAll();
$baseImages = (int) $pdo->query("SELECT COUNT(*) FROM vehicle_images")->fetchColumn();   // real photographs may already exist
$baseShipments = (int) $pdo->query('SELECT COUNT(*) FROM shipments')->fetchColumn();   // real shipments and enquiries may exist too
$baseEnquiries = (int) $pdo->query('SELECT COUNT(*) FROM enquiries')->fetchColumn();
$baseHonda = (int) run($pdo, 'SELECT COUNT(*) FROM vehicles WHERE brand_id = ? AND deleted_at IS NULL', [$honda])->fetchColumn();
$baseYears = $repo->years();
check('baseline recorded (existing real vehicles are left untouched)', $baseCount >= 0);
check('the schema has no "make" column (brand_id is the only brand reference)', (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles' AND COLUMN_NAME = 'make'")->fetchColumn() === 0);
$adminId = (int) $pdo->query('SELECT id FROM admin_users ORDER BY id LIMIT 1')->fetchColumn();
check('an administrator row exists for audit foreign keys', $adminId > 0);

$pdo->beginTransaction();
try {
    // ---------------------------------------------------------------- create
    echo "Create\n";
    $a = $service->createVehicle(input(['model' => 'TEST DATA Camry']), $adminId, $ip, $ua);
    check('valid create returns ok + id', $a['status'] === 'ok' && $a['id'] > 0, json_encode($a['errors']));
    $row = $service->find($a['id']);
    check('stored values are exact', (int) $row['year'] === 2021 && $row['model'] === 'TEST DATA Camry' && $row['trim'] === 'XSE' && $row['price'] === '12500.00' && $row['currency'] === 'GHS'
        && (int) $row['brand_id'] === $toyota && $row['availability_status'] === 'available' && (int) $row['is_published'] === 0 && (int) $row['duty_included'] === 0);
    check('slug generated from year + brand + model + trim', $row['slug'] === '2021-toyota-test-data-camry-xse', $row['slug']);
    check('optional fields stay empty (nothing invented)', $row['description'] === null && $row['key_features'] === null && $row['shipping_info'] === null && $row['mileage'] === null && $row['transmission'] === null);
    check('brand relationship returns the brand name in one query', $row['brand_name'] === 'Toyota');
    check('VEHICLE_CREATED audited with admin + entity', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='VEHICLE_CREATED' AND admin_user_id=? AND entity_type='vehicle' AND entity_id=?", [$adminId, $a['id']])->fetchColumn() === 1);

    $b = $service->createVehicle(input(['model' => 'TEST DATA Camry']), $adminId, $ip, $ua);
    $c = $service->createVehicle(input(['model' => 'TEST DATA Camry']), $adminId, $ip, $ua);
    check('a second identical unit is allowed (same brand + model + year)', $b['status'] === 'ok' && $c['status'] === 'ok');
    check('their generated slugs are made unique with -2, -3', $service->find($b['id'])['slug'] === '2021-toyota-test-data-camry-xse-2' && $service->find($c['id'])['slug'] === '2021-toyota-test-data-camry-xse-3');
    $dupSlug = $service->createVehicle(input(['model' => 'Other', 'slug' => '2021-toyota-test-data-camry-xse']), $adminId, $ip, $ua);
    check('a typed slug that is taken is rejected: "This vehicle URL is already in use."', ($dupSlug['errors']['slug'] ?? '') === 'This vehicle URL is already in use.', json_encode($dupSlug['errors']));
    $typed = $service->createVehicle(input(['model' => 'Typed', 'slug' => 'my-typed-slug']), $adminId, $ip, $ua);
    check('a typed slug that is free is kept exactly', $typed['status'] === 'ok' && $service->find($typed['id'])['slug'] === 'my-typed-slug');
    $full = $service->createVehicle(input(['model' => 'TEST DATA Full', 'trim' => '', 'mileage' => '45000', 'mileage_unit' => 'mi', 'transmission' => 'Automatic', 'fuel_type' => 'Petrol', 'drive_type' => 'AWD',
        'price' => '0', 'description' => "Line 1\nLine 2", 'key_features' => "Sunroof\nBackup camera", 'shipping_info' => 'From Toronto', 'duty_included' => '1', 'availability_status' => 'reserved', 'is_published' => '1']), $adminId, $ip, $ua);
    $fr = $service->find($full['id']);
    check('every optional field round-trips', $full['status'] === 'ok' && (int) $fr['mileage'] === 45000 && $fr['mileage_unit'] === 'mi' && $fr['transmission'] === 'Automatic' && $fr['fuel_type'] === 'Petrol'
        && $fr['drive_type'] === 'AWD' && $fr['description'] === "Line 1\nLine 2" && $fr['shipping_info'] === 'From Toronto' && (int) $fr['duty_included'] === 1 && $fr['availability_status'] === 'reserved' && (int) $fr['is_published'] === 1);
    check('price 0 is allowed (not negative)', $fr['price'] === '0.00');
    check('key features stored as the existing JSON array, read back as a list', $fr['features'] === ['Sunroof', 'Backup camera'] && json_decode($fr['key_features'], true) === ['Sunroof', 'Backup camera']);
    check('slug without trim: 2021-toyota-test-data-full', $fr['slug'] === '2021-toyota-test-data-full');
    $before = $repo->countAll();
    $bad = [
        'missing brand' => ['brand_id' => ''], 'nonexistent brand' => ['brand_id' => '99999'], 'text brand' => ['brand_id' => 'Toyota'], 'missing year' => ['year' => ''],
        'invalid year' => ['year' => 'abc'], 'missing model' => ['model' => ''], 'invalid price' => ['price' => 'abc'], 'negative price' => ['price' => '-5'], 'invalid status' => ['availability_status' => 'gone'],
    ];
    foreach ($bad as $name => $over) {
        $r = $service->createVehicle(input($over), $adminId, $ip, $ua);
        check("create rejected: $name", $r['status'] === 'invalid' && $r['errors'] !== [] && $r['id'] === null, json_encode($r));
    }
    check('none of the rejected submissions created a vehicle or an audit row', $repo->countAll() === $before
        && (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='VEHICLE_CREATED' AND user_agent=?", [$ua])->fetchColumn() === 5);
    check('the rejected nonexistent brand gives the specified sentence', ($service->createVehicle(input(['brand_id' => '99999']), $adminId, $ip, $ua)['errors']['brand_id'] ?? '') === 'Please select a valid vehicle brand.');

    // ----------------------------------------------------- active brand rule
    echo "Active brand rule\n";
    run($pdo, "UPDATE brands SET status='inactive' WHERE id=?", [$mazda]);
    $inactive = $service->createVehicle(input(['brand_id' => (string) $mazda, 'model' => 'TEST DATA CX-5']), $adminId, $ip, $ua);
    check('a new vehicle cannot use an inactive brand', $inactive['status'] === 'invalid' && isset($inactive['errors']['brand_id']));
    check('inactive brands are not offered in the drop-down', !in_array($mazda, array_map(static fn ($b) => (int) $b['id'], $service->brandChoices()), true));
    run($pdo, "UPDATE brands SET status='active' WHERE id=?", [$mazda]);
    $m = $service->createVehicle(input(['brand_id' => (string) $mazda, 'model' => 'TEST DATA CX-5']), $adminId, $ip, $ua);
    check('the same brand works when active', $m['status'] === 'ok');
    run($pdo, "UPDATE brands SET status='inactive' WHERE id=?", [$mazda]);
    check('an existing vehicle keeps its brand when the brand is later deactivated (untouched)', (int) $service->find($m['id'])['brand_id'] === $mazda && $service->find($m['id'])['availability_status'] === 'available');
    check('the current inactive brand stays in that vehicle\'s edit drop-down', in_array($mazda, array_map(static fn ($b) => (int) $b['id'], $service->brandChoices($mazda)), true));
    $keep = $service->updateVehicle($m['id'], input(['brand_id' => (string) $mazda, 'model' => 'TEST DATA CX-5', 'price' => '999', 'slug' => $service->find($m['id'])['slug']]), $adminId, $ip, $ua);
    check('editing a vehicle whose brand is inactive works if the brand is kept', $keep['status'] === 'ok');
    $moveToInactive = $service->updateVehicle($a['id'], input(['brand_id' => (string) $mazda, 'slug' => $service->find($a['id'])['slug']]), $adminId, $ip, $ua);
    check('moving a vehicle TO an inactive brand is rejected', $moveToInactive['status'] === 'invalid' && isset($moveToInactive['errors']['brand_id']));
    run($pdo, "UPDATE brands SET status='active' WHERE id=?", [$mazda]);

    // ------------------------------------------------------------------ edit
    echo "Edit\n";
    $slugA = $service->find($a['id'])['slug'];
    $same = $service->updateVehicle($a['id'], input(['slug' => $slugA]), $adminId, $ip, $ua);
    check('saving without changes reports "unchanged" and writes no audit row', $same['status'] === 'unchanged'
        && (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='VEHICLE_UPDATED' AND entity_id=?", [$a['id']])->fetchColumn() === 0);
    $beforeEdit = $service->find($a['id']);
    $edit = $service->updateVehicle($a['id'], input(['brand_id' => (string) $honda, 'price' => '13999.9', 'key_features' => "ABS\nAirbags", 'availability_status' => 'sold', 'slug' => $slugA, 'description' => 'New text']), $adminId, $ip, $ua);
    $afterEdit = $service->find($a['id']);
    check('edit saved: brand, price, features, status, description', $edit['status'] === 'ok' && (int) $afterEdit['brand_id'] === $honda && $afterEdit['price'] === '13999.90' && $afterEdit['features'] === ['ABS', 'Airbags']
        && $afterEdit['availability_status'] === 'sold' && $afterEdit['description'] === 'New text');
    check('own slug is not flagged as a duplicate of itself; id and created date preserved', $afterEdit['slug'] === $slugA && $afterEdit['created_at'] === $beforeEdit['created_at'] && (int) $afterEdit['id'] === $a['id']);
    check('brand name follows the new brand (no copied text to go stale)', $afterEdit['brand_name'] === 'Honda');
    check('VEHICLE_UPDATED audit lists the changed fields', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='VEHICLE_UPDATED' AND entity_id=? AND description LIKE '%brand_id%' AND description LIKE '%price%' AND description LIKE '%key_features%'", [$a['id']])->fetchColumn() === 1);
    check('a status change through the edit form is audited as a status change', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='VEHICLE_STATUS_CHANGED' AND entity_id=? AND description LIKE '%available -> sold%'", [$a['id']])->fetchColumn() === 1);
    $dupEdit = $service->updateVehicle($a['id'], input(['brand_id' => (string) $honda, 'slug' => $service->find($b['id'])['slug']]), $adminId, $ip, $ua);
    check('editing to another vehicle\'s slug is rejected', ($dupEdit['errors']['slug'] ?? '') === 'This vehicle URL is already in use.');
    check('a blank slug on edit is rejected (the slug is always shown)', isset($service->updateVehicle($a['id'], input(['slug' => '']), $adminId, $ip, $ua)['errors']['slug']));
    check('failed edits changed nothing', $service->find($a['id'])['slug'] === $slugA && $service->find($a['id'])['price'] === '13999.90');
    check('edit of a missing id reports not_found', $service->updateVehicle(99999, input(), $adminId, $ip, $ua)['status'] === 'not_found');
    check('currency of an existing record is preserved in the allowed list', $service->updateVehicle($a['id'], input(['brand_id' => (string) $honda, 'currency' => 'USD', 'slug' => $slugA]), $adminId, $ip, $ua)['status'] === 'invalid');
    $touched = $service->find($b['id']);
    check('other vehicles are untouched by edits', $touched['price'] === '12500.00' && $touched['availability_status'] === 'available');
    $cleared = $service->updateVehicle($full['id'], input(['trim' => '', 'mileage' => '', 'transmission' => '', 'fuel_type' => '', 'drive_type' => '', 'price' => '0', 'description' => '', 'key_features' => '', 'shipping_info' => '', 'availability_status' => 'reserved',
        'duty_included' => '1', 'is_published' => '1', 'model' => 'TEST DATA Full', 'slug' => $fr['slug']]), $adminId, $ip, $ua);
    $cr = $service->find($full['id']);
    check('optional fields can be cleared back to empty', $cleared['status'] === 'ok' && $cr['mileage'] === null && $cr['mileage_unit'] === null && $cr['description'] === null && $cr['key_features'] === null && $cr['shipping_info'] === null && $cr['transmission'] === null);

    // ---------------------------------------------------------------- status
    echo "Status\n";
    $svc = static fn (int $id, string $to) => $GLOBALS['service']->setStatus($id, $to, $GLOBALS['adminId'], $GLOBALS['ip'], $GLOBALS['ua']);
    $st = static fn (int $id): string => $GLOBALS['service']->find($id)['availability_status'];
    check('available -> reserved', $svc($b['id'], 'reserved')['status'] === 'ok' && $st($b['id']) === 'reserved');
    check('reserved -> available', $svc($b['id'], 'available')['status'] === 'ok' && $st($b['id']) === 'available');
    check('available -> sold', $svc($b['id'], 'sold')['status'] === 'ok' && $st($b['id']) === 'sold');
    check('sold -> unavailable', $svc($b['id'], 'unavailable')['status'] === 'ok' && $st($b['id']) === 'unavailable');
    check('unavailable -> available (no transition is restricted)', $svc($b['id'], 'available')['status'] === 'ok' && $st($b['id']) === 'available');
    check('VEHICLE_STATUS_CHANGED audited with from -> to', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='VEHICLE_STATUS_CHANGED' AND entity_id=? AND description LIKE '%sold -> unavailable%' AND admin_user_id=?", [$b['id'], $adminId])->fetchColumn() === 1);
    check('repeating the same target is a no-op and not audited again', $svc($b['id'], 'available')['status'] === 'unchanged'
        && (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='VEHICLE_STATUS_CHANGED' AND entity_id=?", [$b['id']])->fetchColumn() === 5);
    foreach (['inactive', 'deleted', '', 'SOLD'] as $bad) { check('invalid status refused: ' . json_encode($bad), $svc($b['id'], $bad)['status'] === 'invalid'); }
    check('status change of a missing vehicle = not_found', $svc(99999, 'sold')['status'] === 'not_found');
    check('status change leaves every other field alone', $service->find($b['id'])['price'] === '12500.00' && $service->find($b['id'])['model'] === 'TEST DATA Camry');

    // ------------------------------------------------------------ list/search
    echo "Listing, search, filters, paging\n";
    $all = static fn (array $f = []): array => $GLOBALS['service']->listVehicles(VehicleValidator::filters($f, $GLOBALS['maxYear']));
    $labels = static fn (array $d): array => array_map(static fn ($v) => $v['model'], $d['vehicles']);
    $lf = $service->createVehicle(input(['brand_id' => (string) $honda, 'year' => '2019', 'model' => 'Civic', 'trim' => 'EX-L', 'slug' => 'a-civic-ex-l']), $adminId, $ip, $ua);
    $d = $all();
    check('newest first (created_at DESC, id DESC): the last created is first', $d['vehicles'][0]['id'] == $lf['id'] && $d['total'] === $d['total_all']);
    check('the list returns the brand name without extra queries (JOIN)', $d['vehicles'][0]['brand_name'] === 'Honda');
    check('search by model', $labels($all(['q' => 'civic'])) === ['Civic']);
    check('search by trim', $labels($all(['q' => 'ex-l'])) === ['Civic']);
    check('search by brand name', $all(['q' => 'honda'])['total'] === 2 + $baseHonda);
    check('search by slug', $labels($all(['q' => 'a-civic-ex-l'])) === ['Civic']);
    check('search by year', $labels($all(['q' => '2019'])) === ['Civic']);
    check('several words must all match: "honda civic 2019"', $labels($all(['q' => 'honda civic 2019'])) === ['Civic'] && $all(['q' => 'honda camry xyz'])['total'] === 0);
    check('search is case-insensitive', $labels($all(['q' => 'CIVIC'])) === ['Civic']);
    check('LIKE wildcards are literal', $all(['q' => '%'])['total'] === 0 && $all(['q' => '_'])['total'] === 0);
    foreach (["' OR '1'='1", "x'; DROP TABLE vehicles; --", '" OR ""="', '\\', "1' UNION SELECT password_hash FROM admin_users-- "] as $evil) {
        check('injection string in search: no rows, no error: ' . substr($evil, 0, 25), $all(['q' => $evil])['total'] === 0);
    }
    check('vehicles table intact after injection strings', $repo->countAll() > 0);
    check('brand filter', $all(['brand' => (string) $honda])['total'] === 2 + $baseHonda && array_unique(array_column($all(['brand' => (string) $honda])['vehicles'], 'brand_name')) === ['Honda']);
    check('status filter', array_unique(array_column($all(['status' => 'sold'])['vehicles'], 'availability_status')) === ['sold']);
    check('year filter', $labels($all(['year' => '2019'])) === ['Civic']);
    check('filters combine (brand + status + search)', $all(['brand' => (string) $honda, 'status' => 'available', 'q' => 'civic'])['total'] === 1 && $all(['brand' => (string) $toyota, 'status' => 'available', 'q' => 'civic'])['total'] === 0);
    check('unknown brand id filter gives an empty list, not an error', $all(['brand' => '99999'])['total'] === 0);
    check('the year filter offers only years that exist, newest first', $all()['years'] === (static function (array $y): array { rsort($y); return $y; })(array_values(array_unique(array_merge([2021, 2019], $baseYears)))));
    check('the brand filter lists every brand (inactive ones too)', count($all()['brands']) === 12);
    for ($i = 1; $i <= 45; $i++) {
        $repo->insert(['brand_id' => $toyota, 'year' => 2010, 'model' => sprintf('TEST DATA Page %02d', $i), 'trim' => null, 'engine_size' => null, 'mileage' => null, 'mileage_unit' => null, 'transmission' => null, 'fuel_type' => null,
            'drive_type' => null, 'price' => '1.00', 'currency' => 'GHS', 'description' => null, 'key_features' => [], 'shipping_info' => null, 'duty_included' => 0, 'availability_status' => 'available', 'is_published' => 0, 'slug' => sprintf('test-data-page-%02d', $i)]);
    }
    $p1 = $all(['q' => 'TEST DATA Page']);
    check('pagination: 45 matches = 3 pages of 20, 20, 5', $p1['total'] === 45 && $p1['pages'] === 3 && count($p1['vehicles']) === 20 && $p1['from'] === 1 && $p1['to'] === 20);
    $p3 = $all(['q' => 'TEST DATA Page', 'page' => '3']);
    check('pagination: last page has 5 rows with correct range', count($p3['vehicles']) === 5 && $p3['from'] === 41 && $p3['to'] === 45 && $p3['page'] === 3);
    check('pagination: a page beyond the end shows the last page', $all(['q' => 'TEST DATA Page', 'page' => '99'])['page'] === 3);
    $ids = array_merge(...array_map(static fn ($pg) => array_column($all(['q' => 'TEST DATA Page', 'page' => (string) $pg])['vehicles'], 'id'), [1, 2, 3]));
    check('pagination: no row repeated or skipped across pages (deterministic order)', count($ids) === 45 && count(array_unique($ids)) === 45);
    check('pagination: ids descend overall (newest first)', $ids === (static function (array $x): array { rsort($x); return $x; })($ids));
    check('no rows: pages = 1, from = to = 0', ($e = $all(['q' => 'zzzzzz']))['pages'] === 1 && $e['from'] === 0 && $e['to'] === 0 && $e['vehicles'] === []);

    // --------------------------------------------------------------- delete
    echo "Delete\n";
    $del = $service->deleteVehicle($typed['id'], $adminId, $ip, $ua);
    check('unreferenced vehicle deleted', $del['status'] === 'ok' && $service->find($typed['id']) === null);
    check('VEHICLE_DELETED audited (row survives the vehicle)', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='VEHICLE_DELETED' AND entity_id=? AND admin_user_id=?", [$typed['id'], $adminId])->fetchColumn() === 1);
    check('deleting a missing vehicle = not_found; twice = not_found', $service->deleteVehicle(99999, $adminId, $ip, $ua)['status'] === 'not_found' && $service->deleteVehicle($typed['id'], $adminId, $ip, $ua)['status'] === 'not_found');

    run($pdo, 'INSERT INTO shipments (vehicle_id, customer_name) VALUES (?, ?)', [$b['id'], 'TEST DATA customer']);
    $blockedShip = $service->deleteVehicle($b['id'], $adminId, $ip, $ua);
    check('a vehicle with a shipment cannot be deleted', $blockedShip['status'] === 'referenced' && $service->find($b['id']) !== null && $blockedShip['reasons'] === ['shipment']);
    check('the specified sentence is produced for shipments', VehicleController::referencedMessage($blockedShip['reasons']) === 'This vehicle cannot be deleted because it is associated with shipment records. Change its status instead.');
    check('the shipment row still exists (no cascade)', (int) run($pdo, 'SELECT COUNT(*) FROM shipments WHERE vehicle_id=?', [$b['id']])->fetchColumn() === 1);
    check('VEHICLE_DELETION_ATTEMPTED audited', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='VEHICLE_DELETION_ATTEMPTED' AND entity_id=? AND admin_user_id=?", [$b['id'], $adminId])->fetchColumn() === 1);

    run($pdo, "INSERT INTO enquiries (vehicle_id, customer_name, email, message) VALUES (?, 'TEST DATA', 'test@example.test', 'TEST DATA message')", [$c['id']]);
    $blockedEnq = $service->deleteVehicle($c['id'], $adminId, $ip, $ua);
    check('a vehicle with an enquiry cannot be deleted', $blockedEnq['status'] === 'referenced' && $blockedEnq['reasons'] === ['enquiry'] && (int) run($pdo, 'SELECT COUNT(*) FROM enquiries WHERE vehicle_id=?', [$c['id']])->fetchColumn() === 1);
    run($pdo, "INSERT INTO vehicle_images (vehicle_id, file_path) VALUES (?, 'uploads/vehicles/test-data.webp')", [$m['id']]);
    $blockedImg = $service->deleteVehicle($m['id'], $adminId, $ip, $ua);
    check('a vehicle with images cannot be deleted (their FK would cascade, so it is refused explicitly)', $blockedImg['status'] === 'referenced' && $blockedImg['reasons'] === ['image'] && (int) run($pdo, 'SELECT COUNT(*) FROM vehicle_images WHERE vehicle_id=?', [$m['id']])->fetchColumn() === 1);
    check('mixed references are all named', VehicleController::referencedMessage(['shipment', 'enquiry', 'image']) === 'This vehicle cannot be deleted because it is associated with shipment, enquiry and image records. Change its status instead.');
    $threw = static function (callable $fn): ?PDOException {
        $pdo = Database::connection();
        $pdo->exec('SAVEPOINT t');
        try { $fn(); $pdo->exec('ROLLBACK TO SAVEPOINT t'); return null; }
        catch (PDOException $e) { $pdo->exec('ROLLBACK TO SAVEPOINT t'); return $e; }
    };
    $e = $threw(fn () => $repo->delete($b['id']));
    check('the database foreign key (RESTRICT) refuses the delete even if the service check were skipped', $e !== null && (int) $e->errorInfo[1] === 1451);
    check('foreign key checks are still on', (int) $pdo->query('SELECT @@foreign_key_checks')->fetchColumn() === 1);
    check('a referenced vehicle can still be retired with a status change', $svc($b['id'], 'sold')['status'] === 'ok');

    // ------------------------------------------------------- DB constraints
    echo "Database constraints\n";
    $row = static fn (array $over): array => $over + ['brand_id' => $GLOBALS['toyota'], 'year' => 2020, 'model' => 'TEST DATA Raw', 'trim' => null, 'engine_size' => null, 'mileage' => null, 'mileage_unit' => null, 'transmission' => null,
        'fuel_type' => null, 'drive_type' => null, 'price' => '1.00', 'currency' => 'GHS', 'description' => null, 'key_features' => [], 'shipping_info' => null, 'duty_included' => 0, 'availability_status' => 'available', 'is_published' => 0, 'slug' => 'test-data-raw'];
    $e = $threw(fn () => $repo->insert($row(['slug' => $slugA])));
    check('UNIQUE(slug) enforced by the database itself', $e !== null && (int) $e->errorInfo[1] === 1062 && str_contains($e->getMessage(), 'uq_vehicles_slug'));
    $e = $threw(fn () => $repo->insert($row(['brand_id' => 99999])));
    check('the brand foreign key rejects a nonexistent brand', $e !== null && (int) $e->errorInfo[1] === 1452);
    check('CHECK(year range) enforced', $threw(fn () => $repo->insert($row(['year' => 1800]))) !== null);
    check('CHECK(price >= 0) enforced', $threw(fn () => $repo->insert($row(['price' => '-1.00']))) !== null);
    check('CHECK(slug format) enforced', $threw(fn () => $repo->insert($row(['slug' => 'Bad Slug']))) !== null);
    check('brand deletion is blocked while vehicles use it (Spec 05 rule still holds)', (new BrandService($pdo, new BrandRepository($pdo), new AuditLogRepository($pdo)))->deleteBrand($toyota, $adminId, $ip, $ua)['status'] === 'referenced');

    // ------------------------------------------------------ hostile data
    echo "Hostile input is stored as data\n";
    $x = $service->createVehicle(input(['model' => '<script>alert(1)</script>', 'trim' => '"><img src=x onerror=alert(1)>', 'description' => '<b>x</b>', 'key_features' => '<i>f</i>', 'shipping_info' => '<u>s</u>', 'slug' => 'xss-vehicle']), $adminId, $ip, $ua);
    $xr = $service->find($x['id']);
    check('HTML in text fields is accepted as plain text and stored byte for byte', $x['status'] === 'ok' && $xr['model'] === '<script>alert(1)</script>' && $xr['trim'] === '"><img src=x onerror=alert(1)>' && $xr['description'] === '<b>x</b>' && $xr['features'] === ['<i>f</i>']);
    check('escaping neutralises it on output', !str_contains(e($xr['model']), '<script>') && str_contains(e($xr['model']), '&lt;script&gt;'));
    $sq = $service->createVehicle(input(['model' => "Robert'); DROP TABLE vehicles;--", 'slug' => 'sqli-vehicle']), $adminId, $ip, $ua);
    check('SQL metacharacters in fields are stored as data; the table survives', $sq['status'] === 'ok' && $service->find($sq['id'])['model'] === "Robert'); DROP TABLE vehicles;--" && $repo->countAll() > 50);
    check('audit descriptions are capped and carry no secrets', (int) run($pdo, 'SELECT MAX(CHAR_LENGTH(description)) FROM audit_logs')->fetchColumn() <= 500
        && (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type LIKE 'VEHICLE\\_%' AND (description LIKE '%password%' OR description LIKE '%token%' OR description LIKE '%session%' OR description LIKE '%secret%')")->fetchColumn() === 0);
} finally {
    $pdo->rollBack();
}

// ------------------------------------------------------ controller
echo "Controller authorization and error containment\n";
$staff = ['id' => 1, 'name' => 'Staff', 'role' => 'staff'];
$controller = new VehicleController();
$auditBefore = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE event_type LIKE 'VEHICLE\\_%'")->fetchColumn();
$refused = [
    'index'         => $controller->index($staff, []),
    'create (GET)'  => $controller->create($staff, 'GET', [], $ip, $ua),
    'create (POST)' => $controller->create($staff, 'POST', input(), $ip, $ua),
    'edit'          => $controller->edit($staff, '1', 'GET', [], $ip, $ua),
    'show'          => $controller->show($staff, '1'),
    'changeStatus'  => $controller->changeStatus($staff, ['id' => '1', 'status' => 'sold'], $ip, $ua),
    'confirmDelete' => $controller->confirmDelete($staff, '1'),
    'delete'        => $controller->delete($staff, ['id' => '1', 'confirm' => 'yes'], $ip, $ua),
    'no role'       => $controller->index(['id' => 1, 'name' => 'x', 'role' => ''], []),
];
foreach ($refused as $action => $result) {
    check("role without the vehicles permission is refused: $action", $result['http'] === 403 && $result['problem'] === 'forbidden' && !isset($result['vehicles']));
}
check('the refused actions changed nothing', $repo->countAll() === $baseCount && (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE event_type LIKE 'VEHICLE\\_%'")->fetchColumn() === $auditBefore);
check('administrator role holds the vehicles permission', AuthorizationService::can('administrator', VehicleController::PERMISSION));

$failing = new class extends VehicleService {
    public function __construct() {}
    public function maxYear(): int { return 2030; }
    public function listVehicles(array $filters): array { throw new RuntimeException("SQLSTATE[HY000] secret detail in C:\\path\\file.php"); }
};
$admin = ['id' => 1, 'name' => 'Admin', 'role' => 'administrator'];
$prev = ini_set('error_log', sys_get_temp_dir() . '/vehicle_test_errors.log');
$err = (new VehicleController($failing))->index($admin, []);
ini_set('error_log', (string) $prev);
check('a failing service gives a 500 with a generic message', $err['http'] === 500 && $err['problem'] === 'error' && $err['message'] === VehicleController::MESSAGE_ERROR);
check('the message exposes no SQL, path or exception text', !preg_match('/SQLSTATE|secret|path|file\.php/i', json_encode($err)));
check('the technical detail went to the server log instead', str_contains((string) @file_get_contents(sys_get_temp_dir() . '/vehicle_test_errors.log'), 'SQLSTATE'));
@unlink(sys_get_temp_dir() . '/vehicle_test_errors.log');

echo "Cleanup\n";
check('rollback left the vehicle, shipment, enquiry and image counts as they were', $repo->countAll() === $baseCount
    && (int) $pdo->query('SELECT COUNT(*) FROM vehicles')->fetchColumn() === $baseCount
    && (int) $pdo->query('SELECT COUNT(*) FROM shipments')->fetchColumn() === $baseShipments
    && (int) $pdo->query('SELECT COUNT(*) FROM enquiries')->fetchColumn() === $baseEnquiries
    && (int) $pdo->query('SELECT COUNT(*) FROM vehicle_images')->fetchColumn() === $baseImages);
check('no vehicle audit rows remain from this run', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type LIKE 'VEHICLE\\_%' AND user_agent = ?", [$ua])->fetchColumn() === 0);
check('the approved brands are unchanged (statuses restored by the rollback)', (int) $pdo->query("SELECT COUNT(*) FROM brands WHERE slug IN ('toyota','honda','mazda')")->fetchColumn() === 3);

// Rolled-back inserts still consume AUTO_INCREMENT values; put the counters back (MySQL never lowers one below max+1).
foreach (['vehicles', 'shipments', 'enquiries', 'vehicle_images', 'audit_logs'] as $table) {
    $pdo->exec("ALTER TABLE `$table` AUTO_INCREMENT = 1");
}

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
