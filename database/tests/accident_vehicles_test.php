<?php

declare(strict_types=1);

/**
 * Tests for spec 23 (accident vehicles): damage validation, the publish rule (damage description + at least one Damage
 * photo), separation from the normal inventory, the admin switch (off = nothing shown), the vehicle page disclosure and
 * favourites. One rolled-back transaction; nothing is kept.
 * Run: php database/tests/accident_vehicles_test.php
 */

require_once dirname(__DIR__, 2) . '/app/services/VehicleService.php';
require_once dirname(__DIR__, 2) . '/app/services/PublicVehicleService.php';
require_once dirname(__DIR__, 2) . '/app/services/AccidentVehicleSettings.php';
require_once dirname(__DIR__, 2) . '/app/services/CustomerDashboardService.php';
require_once dirname(__DIR__, 2) . '/app/repositories/CustomerRepository.php';

$passed = 0;
$failed = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "  PASS  $label\n"; }
    else { $failed++; echo "  FAIL  $label" . ($detail !== '' ? " -> $detail" : '') . "\n"; }
}

$pdo = Database::connection();
$adminId = (int) $pdo->query('SELECT id FROM admin_users ORDER BY id LIMIT 1')->fetchColumn();
$brand = (int) $pdo->query("SELECT id FROM brands WHERE status = 'active' ORDER BY id LIMIT 1")->fetchColumn();
$ip = '203.0.113.40';
$ua = 'accident_test';
$maxYear = VehicleValidator::maxYear();

$base = static fn (array $extra = []): array => $extra + [
    'brand_id' => (string) $brand, 'year' => '2021', 'model' => 'TESTACCMODEL', 'trim' => '', 'engine_size' => '', 'mileage' => '', 'mileage_unit' => 'km',
    'transmission' => '', 'fuel_type' => '', 'drive_type' => '', 'price' => '9000', 'currency' => 'GHS', 'description' => '', 'key_features' => '',
    'shipping_info' => '', 'duty_included' => '0', 'availability_status' => 'available', 'is_published' => '0', 'slug' => '',
];
$damage = ['is_accident' => '1', 'damage_description' => 'TEST front-end collision; bonnet and bumper replaced.', 'damage_areas' => ['front', 'interior'],
    'runs_drives' => 'yes', 'airbags_deployed' => 'no', 'title_status' => 'salvage'];

echo "Validation\n";
$r = VehicleValidator::validate($base(), $maxYear);
check('a form without the accident box is a normal vehicle (forms from before spec 23 still work)', $r['errors'] === [] && $r['values']['is_accident'] === 0 && $r['values']['damage_description'] === null);
$r = VehicleValidator::validate($base(['is_accident' => '1']), $maxYear);
check('an accident vehicle needs description, areas, runs/drives, airbags and title status',
    array_keys(array_intersect_key($r['errors'], array_flip(['damage_description', 'damage_areas', 'runs_drives', 'airbags_deployed', 'title_status']))) === ['damage_description', 'damage_areas', 'runs_drives', 'airbags_deployed', 'title_status'],
    implode(',', array_keys($r['errors'])));
$r = VehicleValidator::validate($base($damage), $maxYear);
check('a full disclosure is accepted', $r['errors'] === [] && $r['values']['damage_areas'] === ['front', 'interior'] && $r['values']['title_status'] === 'salvage');
$r = VehicleValidator::validate($base(['damage_areas' => ['front', 'engine']] + $damage), $maxYear);
check('an unknown damaged area is refused', isset($r['errors']['damage_areas']));
$r = VehicleValidator::validate($base(['damage_areas' => 'front'] + $damage), $maxYear);
check('damaged areas that are not a list are refused', isset($r['errors']['damage_areas']));
$r = VehicleValidator::validate($base(['runs_drives' => 'maybe', 'title_status' => "clean' OR 1=1"] + $damage), $maxYear);
check('values outside the lists are refused', isset($r['errors']['runs_drives'], $r['errors']['title_status']));
$r = VehicleValidator::validate($base(['is_accident' => 'yes']), $maxYear);
check('the accident flag accepts only 1 or 0', isset($r['errors']['is_accident']));
$r = VehicleValidator::validate($base(['is_accident' => '0'] + $damage), $maxYear);
check('a normal vehicle stores no damage values, whatever was posted', $r['errors'] === [] && $r['values']['damage_description'] === null && $r['values']['damage_areas'] === [] && $r['values']['title_status'] === null);
$r = VehicleValidator::validate($base(['damage_description' => str_repeat('a', VehicleValidator::DAMAGE_MAX + 1)] + $damage), $maxYear);
check('the damage description has a length limit', isset($r['errors']['damage_description']));

$pdo->beginTransaction();
try {
    $settingsBefore = $pdo->query("SELECT COUNT(*) FROM settings WHERE category = 'features'")->fetchColumn();
    $pdo->exec("DELETE FROM settings WHERE setting_key = 'accident_vehicles_enabled'");   // start from OFF (rolled back)
    AccidentVehicleSettings::forget();

    echo "\nPublish rule\n";
    $service = VehicleService::create();
    $r = $service->createVehicle($base(['is_published' => '1'] + $damage), $adminId, $ip, $ua);
    check('a NEW accident vehicle cannot be published (it has no damage photo yet)', $r['status'] === VehicleService::RESULT_INVALID && isset($r['errors']['is_published']));
    $r = $service->createVehicle($base($damage), $adminId, $ip, $ua);
    $accId = (int) $r['id'];
    $row = $pdo->query('SELECT * FROM vehicles WHERE id = ' . $accId)->fetch();
    check('saved unpublished, with every damage value stored', $r['status'] === VehicleService::RESULT_OK && (int) $row['is_accident'] === 1
        && $row['damage_areas'] === 'front,interior' && $row['runs_drives'] === 'yes' && $row['airbags_deployed'] === 'no' && $row['title_status'] === 'salvage');
    check('the creation is audited as an accident vehicle', (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE user_agent = 'accident_test' AND event_type = 'VEHICLE_CREATED' AND description LIKE '%accident vehicle%'")->fetchColumn() === 1);
    $slug = (string) $row['slug'];
    $edit = $base(['slug' => $slug, 'is_published' => '1'] + $damage);
    $r = $service->updateVehicle($accId, $edit, $adminId, $ip, $ua);
    check('publishing without a Damage photo is refused', $r['status'] === VehicleService::RESULT_INVALID && $r['errors']['is_published'] === VehicleService::MESSAGE_NEEDS_DAMAGE_PHOTO);
    $pdo->prepare("INSERT INTO vehicle_images (vehicle_id, file_path, photo_type, display_order, is_primary) VALUES (?, 'uploads/vehicles/test-acc-ext.webp', 'exterior', 1, 1)")->execute([$accId]);
    $r = $service->updateVehicle($accId, $edit, $adminId, $ip, $ua);
    check('an exterior photo is not enough', $r['status'] === VehicleService::RESULT_INVALID);
    $pdo->prepare("INSERT INTO vehicle_images (vehicle_id, file_path, photo_type, display_order) VALUES (?, 'uploads/vehicles/test-acc-dmg.webp', 'damage', 2)")->execute([$accId]);
    $r = $service->updateVehicle($accId, $edit, $adminId, $ip, $ua);
    check('with a Damage photo it can be published', $r['status'] === VehicleService::RESULT_OK);
    $r = $service->updateVehicle($accId, $base(['slug' => $slug, 'is_published' => '1', 'damage_areas' => ['front', 'rear']] + $damage), $adminId, $ip, $ua);
    check('a change of damaged areas is detected and audited', $r['status'] === VehicleService::RESULT_OK
        && (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE user_agent = 'accident_test' AND description LIKE '%damage_areas%'")->fetchColumn() === 1);

    // A normal published vehicle with the same model, for comparison
    $r = $service->createVehicle($base(['is_published' => '1']), $adminId, $ip, $ua);
    $normalId = (int) $r['id'];
    $normalSlug = (string) $pdo->query('SELECT slug FROM vehicles WHERE id = ' . $normalId)->fetchColumn();

    echo "\nSeparation from the normal inventory (switch OFF)\n";
    $public = PublicVehicleService::create();
    $slugsOf = static fn (array $cards): array => array_column($cards, 'slug');
    check('the switch is off by default (no setting)', AccidentVehicleSettings::enabled() === false);
    $normal = $public->search(['q' => 'TESTACCMODEL'], 48);
    check('the Vehicles page shows the normal vehicle only', in_array($normalSlug, $slugsOf($normal['cards']), true) && !in_array($slug, $slugsOf($normal['cards']), true));
    check('the homepage (latest) never shows the accident vehicle', !in_array($slug, $slugsOf($public->latest(12)), true));
    check('a section in the URL filters cannot reach accident vehicles', !in_array($slug, $slugsOf($public->search(['q' => 'TESTACCMODEL', 'section' => 'accident'], 48)['cards']), true));
    check('the accident page is unavailable while OFF', $public->accidentSearch([], false) === null);
    check('the accident vehicle\'s own page is unavailable while OFF', $public->detail($slug, false) === null);
    check('the normal vehicle\'s page works and has no damage section', ($nd = $public->detail($normalSlug, false)) !== null && array_key_exists('accident', $nd['vehicle']) && $nd['vehicle']['accident'] === null);
    $customers = new CustomerRepository($pdo);
    $cust = (int) $customers->create('TEST Acc', 'acc.test-accident@example.test', password_hash('pw-' . bin2hex(random_bytes(6)), PASSWORD_DEFAULT));
    check('an accident vehicle cannot be saved as a favourite while OFF', (new CustomerDashboardService($pdo, null, false))->setFavourite($cust, $slug, true) === 'not_found');

    echo "\nAccident Vehicles page (switch ON)\n";
    $switch = AccidentVehicleSettings::create();
    check('switching on is saved and audited', $switch->set(true, $adminId, $ip, $ua) === 'ok' && AccidentVehicleSettings::enabled() === true
        && (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE user_agent = 'accident_test' AND event_type = 'SETTINGS_UPDATED' AND description LIKE '%switched ON%'")->fetchColumn() === 1);
    check('switching on again changes nothing', $switch->set(true, $adminId, $ip, $ua) === 'unchanged');
    $acc = $public->accidentSearch(['q' => 'TESTACCMODEL'], true);
    check('the accident page lists the accident vehicle only', $acc !== null && $slugsOf($acc['cards']) === [$slug]);
    check('its card is labelled as accident-damaged', ($acc['cards'][0]['accident'] ?? false) === true);
    check('the Vehicles page still does not show it', !in_array($slug, $slugsOf($public->search(['q' => 'TESTACCMODEL'], 48)['cards']), true));
    $opts = $public->searchOptions(VehicleRepository::SECTION_ACCIDENT);
    check('the accident page filters offer its own models', in_array('TESTACCMODEL', $opts['models'][array_key_first(array_filter($opts['models'], static fn ($m) => in_array('TESTACCMODEL', $m['models'], true)))]['models'] ?? [], true));
    $detail = $public->detail($slug, true);
    $d = $detail['vehicle']['accident'] ?? null;
    check('the vehicle page shows the full disclosure in plain words', $d !== null && $d['areas'] === ['Front', 'Rear']
        && $d['facts'] === [['Runs and drives', 'Yes'], ['Airbags deployed', 'No'], ['Title status', 'Salvage']] && str_starts_with($d['description'], 'TEST front-end'));
    check('favourites: an accident vehicle can be saved while ON', (new CustomerDashboardService($pdo, null, true))->setFavourite($cust, $slug, true) === 'added');
    check('favourites: it is hidden again (not linked) when the page is switched off', (new CustomerDashboardService($pdo, null, false))->favouriteSlugs($cust) === []);

    echo "\nDisclosure is enforced on the website\n";
    $pdo->exec("UPDATE vehicle_images SET photo_type = 'exterior' WHERE vehicle_id = $accId AND photo_type = 'damage'");
    check('an accident vehicle that loses its last Damage photo leaves the accident page', $public->accidentSearch(['q' => 'TESTACCMODEL'], true)['cards'] === []);
    check('and its own page', $public->detail($slug, true) === null);
    $pdo->exec("UPDATE vehicle_images SET photo_type = 'damage' WHERE vehicle_id = $accId AND display_order = 2");
    $pdo->exec("UPDATE vehicles SET damage_description = NULL WHERE id = $accId");
    check('so does one without a damage description', $public->accidentSearch(['q' => 'TESTACCMODEL'], true)['cards'] === []);
    $pdo->exec("UPDATE vehicles SET damage_description = 'TEST' WHERE id = $accId");
    check('switching off is audited', $switch->set(false, $adminId, $ip, $ua) === 'ok' && AccidentVehicleSettings::enabled() === false
        && (int) $pdo->query("SELECT COUNT(*) FROM settings WHERE setting_key = 'accident_vehicles_enabled'")->fetchColumn() === 0);
} finally {
    $pdo->rollBack();
    AccidentVehicleSettings::forget();
}

echo "\nCleanup\n";
check('no test vehicles remain', (int) $pdo->query("SELECT COUNT(*) FROM vehicles WHERE model = 'TESTACCMODEL'")->fetchColumn() === 0);
check('no audit rows remain from this run', (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE user_agent = 'accident_test'")->fetchColumn() === 0);
check('the switch is as it was', $pdo->query("SELECT COUNT(*) FROM settings WHERE category = 'features'")->fetchColumn() === $settingsBefore);
foreach (['customers', 'vehicles', 'vehicle_images', 'audit_logs', 'settings'] as $t) { $pdo->exec("ALTER TABLE `$t` AUTO_INCREMENT = 1"); }

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
