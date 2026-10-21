<?php

declare(strict_types=1);

/**
 * Dashboard data-layer tests (spec 03_spec_dashboard.md, section 28).
 *
 * CLI only:  php database/tests/dashboard_test.php
 * Requires:  schema.sql + seed.sql loaded; DB credentials in .env.
 *
 * All test rows are inserted inside a transaction on the shared connection and
 * rolled back, so nothing is left behind. Test rows are labelled "TEST DATA".
 * Results are compared as deltas against the baseline already in the database.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/app/controllers/DashboardController.php';
require_once dirname(__DIR__, 2) . '/app/helpers/admin_ui.php';

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
$service = DashboardService::create();

// ------------------------------------------------------------ helper functions
echo "Helpers\n";
check('image url: normal file accepted', admin_vehicle_image_url('uploads/vehicles/abc_123.webp') === '../uploads/vehicles/abc_123.webp');
foreach (['../.env', 'uploads/vehicles/../../.env', 'uploads/team/x.webp', '/etc/passwd', 'http://evil.example/x.png',
          'javascript:alert(1)', 'uploads/vehicles/a/b.png', 'uploads/vehicles/', '', 'uploads/vehicles/x.png" onerror="y'] as $bad) {
    check('image url rejected: ' . ($bad === '' ? '(empty)' : $bad), admin_vehicle_image_url($bad) === null);
}
check('image url: null -> null', admin_vehicle_image_url(null) === null);
check('price formatting keeps 2 decimals + currency code', admin_format_price('1234567.5', 'ghs') === 'GHS 1,234,567.50');
check('count formatting', admin_format_count(1234) === '1,234' && admin_format_count(0) === '0');
check('date formatting (UTC)', admin_format_date('2026-10-07 23:59:59') === '7 Oct 2026');
check('vehicle title', admin_vehicle_title(2020, 'Toyota', 'Camry') === '2020 Toyota Camry');
check('status labels for the six shipment statuses', array_map('admin_status_label', DashboardService::SHIPMENT_STATUSES) === [
    'Not Yet Shipped', 'Shipment Booked', 'Tracking Information Available', 'In Transit', 'Arrived', 'Delivered']);
check('enquiry + availability statuses have labels', admin_status_label('new') === 'New' && admin_status_label('available') === 'Available');
$nav = admin_nav_items();
check('nav has the 9 required items in order (Parts added by spec 16, 2026-10-16)', array_column($nav, 'key') === ['dashboard', 'vehicles', 'parts', 'shipments', 'enquiries', 'team', 'brands', 'content', 'settings']);
check('every nav item has a link (no dead items)', count(array_filter($nav, fn ($i) => $i['href'] === '')) === 0);
check('built modules (dashboard, vehicles, parts, brands) link to their pages, the rest to the placeholder', $nav[0]['href'] === 'dashboard.php' && $nav[1]['href'] === 'vehicles.php' && $nav[2]['href'] === 'parts.php' && $nav[3]['href'] === 'coming-soon.php?module=shipments' && $nav[6]['href'] === 'brands.php');

// ------------------------------------------------------- data layer (rolled back)
echo "\nData layer (temporary TEST DATA, rolled back)\n";
$base = $service->getOverviewStatistics();
$baseShip = $service->getShipmentSummary();
$baseEnq = count($service->getRecentEnquiries(20));

$pdo->beginTransaction();
try {
    $brand = (int) run($pdo, "SELECT id FROM brands WHERE slug = 'toyota'")->fetchColumn();
    $vehicle = function (string $suffix, string $status, bool $archived = false) use ($pdo, $brand): int {
        run($pdo, 'INSERT INTO vehicles (brand_id, year, model, trim, price, slug, availability_status, deleted_at)
                   VALUES (?, 2020, ?, ?, 1000.00, ?, ?, ?)',
            [$brand, 'TEST DATA ' . $suffix, 'Trim ' . $suffix, 'test-dashboard-' . $suffix, $status, $archived ? '2026-01-01 00:00:00' : null]);
        return (int) $pdo->lastInsertId();
    };
    $v = [];
    foreach ([['a', 'available'], ['b', 'available'], ['c', 'reserved'], ['d', 'sold'], ['e', 'unavailable'], ['f', 'available'], ['g', 'available', true]] as $row) {
        $v[$row[0]] = $vehicle(...$row);
    }
    // images: vehicle f has 1 primary + 2 non-primary (must still yield ONE row); vehicle e has none
    run($pdo, 'INSERT INTO vehicle_images (vehicle_id, file_path, is_primary, display_order) VALUES (?, ?, 1, 0), (?, ?, 0, 1), (?, ?, 0, 2)',
        [$v['f'], 'uploads/vehicles/test-primary.webp', $v['f'], 'uploads/vehicles/test-2.webp', $v['f'], 'uploads/vehicles/test-3.webp']);

    // shipments: NYS 2, BOOKED 1, TRACKING 0, TRANSIT 3, ARRIVED 1, DELIVERED 2 = 9
    $ship = ['NOT_YET_SHIPPED' => 2, 'SHIPMENT_BOOKED' => 1, 'IN_TRANSIT' => 3, 'ARRIVED' => 1, 'DELIVERED' => 2];
    foreach ($ship as $status => $n) {
        for ($i = 0; $i < $n; $i++) {
            run($pdo, 'INSERT INTO shipments (vehicle_id, status) VALUES (?, ?)', [$v['a'], $status]);
        }
    }

    // enquiries: new 3, in_progress 1, closed 1; 2 are general (no vehicle)
    $enq = [['new', $v['a'], 'vehicle'], ['new', null, 'general'], ['in_progress', $v['b'], 'vehicle'],
            ['new', $v['c'], 'purchase'], ['closed', null, 'general']];
    foreach ($enq as $i => [$status, $vid, $type]) {
        run($pdo, 'INSERT INTO enquiries (vehicle_id, customer_name, email, phone, enquiry_type, message, status)
                   VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$vid, "TEST DATA Customer $i", "secret$i@example.test", "+000000000$i", $type, 'TEST DATA message', $status]);
    }

    $stats = $service->getOverviewStatistics();
    check('total vehicles +6 (archived vehicle excluded)', $stats['total_vehicles'] - $base['total_vehicles'] === 6, json_encode([$base, $stats]));
    check('available vehicles +3 (reserved/sold/unavailable/archived excluded)', $stats['available_vehicles'] - $base['available_vehicles'] === 3);
    check('total shipments +9', $stats['total_shipments'] - $base['total_shipments'] === 9);
    check('new enquiries +3 (in_progress/closed excluded)', $stats['new_enquiries'] - $base['new_enquiries'] === 3);

    $summary = $service->getShipmentSummary();
    $delta = [];
    foreach ($summary['by_status'] as $s => $c) { $delta[$s] = $c - $baseShip['by_status'][$s]; }
    check('shipment summary counts per status are exact', $delta === [
        'NOT_YET_SHIPPED' => 2, 'SHIPMENT_BOOKED' => 1, 'TRACKING_INFORMATION_AVAILABLE' => 0,
        'IN_TRANSIT' => 3, 'ARRIVED' => 1, 'DELIVERED' => 2], json_encode($delta));
    check('shipment summary lists all six statuses in lifecycle order (zero-filled)', array_keys($summary['by_status']) === DashboardService::SHIPMENT_STATUSES);
    check('shipment summary total equals the sum', $summary['total'] === array_sum($summary['by_status']) && $summary['total'] - $baseShip['total'] === 9);

    $recent = $service->getRecentVehicles();
    check('recent vehicles returns the latest 5', count($recent) === 5);
    check('recent vehicles are newest first and exclude the archived one',
        array_column($recent, 'id') === [$v['f'], $v['e'], $v['d'], $v['c'], $v['b']], json_encode(array_column($recent, 'id')));
    check('vehicle with 3 images appears once (only the primary image is joined)',
        count(array_filter($recent, fn ($r) => (int) $r['id'] === $v['f'])) === 1
        && $recent[0]['image_path'] === 'uploads/vehicles/test-primary.webp');
    check('vehicle without images has a null image path', $recent[1]['image_path'] === null);
    check('vehicle rows carry brand name and only dashboard columns',
        $recent[0]['brand_name'] === 'Toyota' && array_keys($recent[0]) === ['id', 'year', 'brand_name', 'model', 'trim', 'price', 'currency', 'availability_status', 'image_path', 'image_alt']);

    $enquiries = $service->getRecentEnquiries();
    check('recent enquiries returns the latest 5, newest first',
        count($enquiries) === 5 && $enquiries[0]['customer_name'] === 'TEST DATA Customer 4' && $enquiries[4]['customer_name'] === 'TEST DATA Customer 0');
    check('recent enquiries do NOT expose email, phone or message',
        array_diff(['email', 'phone', 'message'], array_keys($enquiries[0])) === ['email', 'phone', 'message']);
    check('general enquiry has no vehicle; vehicle enquiry has one',
        $enquiries[0]['vehicle_model'] === null && $enquiries[4]['vehicle_model'] === 'TEST DATA a' && $enquiries[4]['brand_name'] === 'Toyota');

    check('limit is clamped to at least 1', count($service->getRecentVehicles(0)) === 1 && count($service->getRecentVehicles(-5)) === 1);
    check('limit is clamped to at most 20', count($service->getRecentVehicles(100000)) <= 20);

    // controller: all sections succeed
    $view = (new DashboardController($service))->index();
    check('controller returns four sections, none in error',
        array_keys($view) === ['stats', 'vehicles', 'enquiries', 'shipments']
        && count(array_filter($view, fn ($s) => $s['error'])) === 0);

    // controller: one section fails, the rest still load
    $failing = new class (new DashboardRepository($pdo)) extends DashboardService {
        public function getShipmentSummary(): array
        {
            throw new PDOException('SQLSTATE[42S02]: Base table or view not found (simulated)');
        }
    };
    $prevLog = ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
    $partial = (new DashboardController($failing))->index();
    ini_set('error_log', $prevLog === false ? '' : $prevLog);
    check('a failing section reports error without data', $partial['shipments']['error'] === true && $partial['shipments']['data'] === null);
    check('other sections still load when one fails',
        !$partial['stats']['error'] && !$partial['vehicles']['error'] && !$partial['enquiries']['error']);
    check('the error state exposes no technical text', !str_contains(json_encode($partial), 'SQLSTATE'));
} finally {
    $pdo->rollBack();
}

echo "\nRollback check\n";
$after = $service->getOverviewStatistics();
check('baseline restored after rollback (no test data left)', $after === $base, json_encode([$base, $after]));
check('no TEST DATA rows remain', (int) run($pdo, "SELECT COUNT(*) FROM vehicles WHERE slug LIKE 'test-dashboard-%'")->fetchColumn() === 0
    && (int) run($pdo, "SELECT COUNT(*) FROM enquiries WHERE customer_name LIKE 'TEST DATA%'")->fetchColumn() === 0);

echo "\nResult: $passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
