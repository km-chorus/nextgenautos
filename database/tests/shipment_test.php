<?php

declare(strict_types=1);

/**
 * Tests for shipment management, customer tracking and shipment emails (spec 20, D1-D10). Everything runs inside ONE
 * transaction that is rolled back; emails go to a recording double (nothing is sent).
 * Run: php database/tests/shipment_test.php
 */

require_once dirname(__DIR__, 2) . '/app/services/ShipmentService.php';
require_once dirname(__DIR__, 2) . '/app/controllers/ShipmentController.php';

$passed = 0;
$failed = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "  PASS  $label\n"; }
    else { $failed++; echo "  FAIL  $label" . ($detail !== '' ? " -> $detail" : '') . "\n"; }
}

/** Records messages instead of sending them. */
final class RecordingMail extends EmailService
{
    public array $sent = [];
    public bool $succeed = true;
    public function sendTemplate(string $toEmail, string $toName, array $content, ?string $replyToEmail = null, string $replyToName = ''): bool
    {
        $this->sent[] = ['to' => $toEmail, 'name' => $toName, 'content' => $content];

        return $this->succeed;
    }
}

echo "Validation\n";
$base = ['vehicle_id' => '1', 'status' => 'NOT_YET_SHIPPED', 'customer_name' => 'Ama Mensah', 'customer_email' => 'Ama@Example.test'];
$v = ShipmentValidator::validate($base);
check('a minimal shipment is valid; email lower-cased', $v['errors'] === [] && $v['values']['customer_email'] === 'ama@example.test');
check('ISO 6346 check digit: CSQU3054383 valid, CSQU3054384 not', ShipmentValidator::checkDigitOk('CSQU3054383') && !ShipmentValidator::checkDigitOk('CSQU3054384'));
check('container number normalised (spaces, hyphen, lower case)', ShipmentValidator::validate($base + ['container_number' => 'csqu 305438-3'])['values']['container_number'] === 'CSQU3054383');
foreach (['ABC1234567', 'ABCD123456', 'ABCD12345678', '1234ABCDEFG', "CSQU3054383'--"] as $bad) {
    check('container format refused: ' . $bad, isset(ShipmentValidator::validate($base + ['container_number' => $bad])['errors']['container_number']));
}
$w = ShipmentValidator::validate($base + ['container_number' => 'CSQU3054384']);
check('a wrong check digit is only a warning (D9)', $w['errors'] === [] && count($w['warnings']) === 1);
check('container required from "Tracking information available" on', isset(ShipmentValidator::validate(['status' => 'IN_TRANSIT'] + $base)['errors']['container_number'])
    && !isset(ShipmentValidator::validate(['status' => 'SHIPMENT_BOOKED'] + $base)['errors']['container_number']));
check('only the six statuses', isset(ShipmentValidator::validate(['status' => 'LOST_AT_SEA'] + $base)['errors']['status']));
check('tracking link must be https', isset(ShipmentValidator::validate($base + ['tracking_url' => 'http://x.test/'])['errors']['tracking_url'])
    && isset(ShipmentValidator::validate($base + ['tracking_url' => 'javascript:alert(1)'])['errors']['tracking_url'])
    && !isset(ShipmentValidator::validate($base + ['tracking_url' => 'https://www.hapag-lloyd.com/track'])['errors']['tracking_url']));
check('only a Hapag-Lloyd tracking address is accepted (owner, 2026-10-22)', isset(ShipmentValidator::validate($base + ['tracking_url' => 'https://morningsidesystems.com'])['errors']['tracking_url'])
    && isset(ShipmentValidator::validate($base + ['tracking_url' => 'https://www.hapag-lloyd.com.evil.test/'])['errors']['tracking_url']));
check('a non-Hapag-Lloyd address stored earlier is never given to a customer', str_starts_with(ShipmentService::trackingUrl(['tracking_url' => 'https://morningsidesystems.com']), 'https://www.hapag-lloyd.com/'));
check('dates: real dates only, in a sensible order', isset(ShipmentValidator::validate($base + ['shipped_date' => '2026-02-30'])['errors']['shipped_date'])
    && isset(ShipmentValidator::validate($base + ['shipped_date' => '2026-11-10', 'arrival_date' => '2026-11-01'])['errors']['arrival_date'])
    && isset(ShipmentValidator::validate($base + ['arrival_date' => '2026-12-10', 'delivered_date' => '2026-12-01'])['errors']['delivered_date']));
check('name required, no markup; invalid email refused', isset(ShipmentValidator::validate(['customer_name' => ' '] + $base)['errors']['customer_name'])
    && isset(ShipmentValidator::validate(['customer_name' => '<b>x</b>'] + $base)['errors']['customer_name'])
    && isset(ShipmentValidator::validate(['customer_email' => 'nope'] + $base)['errors']['customer_email']));

$pdo = Database::connection();
$adminId = (int) $pdo->query('SELECT id FROM admin_users ORDER BY id LIMIT 1')->fetchColumn();
$realShipments = (int) $pdo->query('SELECT COUNT(*) FROM shipments')->fetchColumn();
// The dev database may hold real shipments: list checks below count relative to what is already there.
$realInTransit = (int) $pdo->query("SELECT COUNT(*) FROM shipments WHERE status = 'IN_TRANSIT'")->fetchColumn();
$realWithTestContainer = (int) $pdo->query("SELECT COUNT(*) FROM shipments WHERE container_number = 'CSQU3054383'")->fetchColumn();
$ip = '203.0.113.20';
$ua = 'shipment_test';
$brand = (int) $pdo->query("SELECT id FROM brands ORDER BY id LIMIT 1")->fetchColumn();

$pdo->beginTransaction();
try {
    $vehicle = static function (string $status, string $slug) use ($pdo, $brand): int {
        $pdo->prepare("INSERT INTO vehicles (brand_id, year, model, price, slug, availability_status) VALUES (?, 2023, 'TESTMODEL', 1000, ?, ?)")->execute([$brand, $slug, $status]);

        return (int) $pdo->lastInsertId();
    };
    $sold = $vehicle('sold', 'test-ship-sold-' . bin2hex(random_bytes(3)));
    $sold2 = $vehicle('reserved', 'test-ship-res-' . bin2hex(random_bytes(3)));
    $available = $vehicle('available', 'test-ship-avail-' . bin2hex(random_bytes(3)));
    $pdo->prepare("INSERT INTO customers (name, email, password_hash, email_verified_at) VALUES ('TEST Ama', 'ama.test-ship@example.test', ?, UTC_TIMESTAMP())")->execute([password_hash('x-' . bin2hex(random_bytes(8)), PASSWORD_DEFAULT)]);
    $customerId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO customers (name, email, password_hash, email_verified_at) VALUES ('TEST Kofi', 'kofi.test-ship@example.test', ?, UTC_TIMESTAMP())")->execute([password_hash('x-' . bin2hex(random_bytes(8)), PASSWORD_DEFAULT)]);
    $otherCustomer = (int) $pdo->lastInsertId();

    $mail = new RecordingMail();
    $repo = new ShipmentRepository($pdo);
    $service = new ShipmentService($pdo, $repo, new AuditLogRepository($pdo), $mail);
    $audit = static fn (string $e): int => (int) $GLOBALS['pdo']->query("SELECT COUNT(*) FROM audit_logs WHERE user_agent = 'shipment_test' AND event_type = " . $GLOBALS['pdo']->quote($e))->fetchColumn();
    $form = static fn (array $over = []): array => $over + ['vehicle_id' => (string) $sold, 'status' => 'NOT_YET_SHIPPED', 'customer_name' => 'TEST Ama', 'customer_email' => 'AMA.test-ship@example.test', 'shipping_company' => 'Hapag-Lloyd'];

    echo "\nCreate\n";
    check('vehicles offered: reserved and sold only (D6)', in_array($sold, array_column($service->vehicleChoices(), 'id'), true) && in_array($sold2, array_column($service->vehicleChoices(), 'id'), true)
        && !in_array($available, array_column($service->vehicleChoices(), 'id'), true));
    check('an available vehicle is refused on the server too', isset($service->createShipment($form(['vehicle_id' => (string) $available]), $adminId, $ip, $ua)['errors']['vehicle_id']));
    $r = $service->createShipment($form(), $adminId, $ip, $ua);
    $id = (int) $r['id'];
    $s = $repo->find($id);
    check('created, linked to the active account with that email (D1, case-insensitive)', $r['status'] === 'ok' && (int) $s['customer_id'] === $customerId && $audit('SHIPMENT_CREATED') === 1);
    check('the vehicle status is not changed by the shipment (D6)', $pdo->query("SELECT availability_status FROM vehicles WHERE id = $sold")->fetchColumn() === 'sold');
    check('one open shipment per vehicle', isset($service->createShipment($form(), $adminId, $ip, $ua)['errors']['vehicle_id']));
    check('no email unless ticked (D2)', $mail->sent === [] && $r['email'] === 'none');
    $noAccount = $service->createShipment($form(['vehicle_id' => (string) $sold2, 'customer_email' => 'walkin@example.test', 'customer_name' => 'Walk In']), $adminId, $ip, $ua);
    check('no account with that email: not linked, still saved', $noAccount['status'] === 'ok' && $repo->find((int) $noAccount['id'])['customer_id'] === null);

    echo "\nUpdate and emails\n";
    $u = $service->updateShipment($id, $form(['status' => 'SHIPMENT_BOOKED', 'notify' => '1']), $adminId, $ip, $ua);
    check('booked + ticked: saved and the customer emailed once, to the account email', $u['status'] === 'ok' && $u['email'] === 'sent' && count($mail->sent) === 1 && $mail->sent[0]['to'] === 'ama.test-ship@example.test');
    check('the booked email: right title, no container row yet, no invented values', $mail->sent[0]['content']['title'] === 'Your shipment is booked' && !in_array('Container number', array_column($mail->sent[0]['content']['details'], 0), true));
    check('notified_at set; status change and email audited', $repo->find($id)['notified_at'] !== null && $audit('SHIPMENT_STATUS_CHANGED') === 1 && $audit('SHIPMENT_EMAIL_SENT') === 1);
    check('"Tracking information available" without a container number is refused', isset($service->updateShipment($id, $form(['status' => 'TRACKING_INFORMATION_AVAILABLE']), $adminId, $ip, $ua)['errors']['container_number']));
    $u = $service->updateShipment($id, $form(['status' => 'IN_TRANSIT', 'container_number' => 'CSQU3054383', 'shipped_date' => '2026-11-12', 'estimated_arrival' => '2026-12-20', 'notify' => '1']), $adminId, $ip, $ua);
    $last = end($mail->sent)['content'];
    $rows = array_column($last['details'], 1, 0);
    check('in transit email: container, line, shipped, estimate labelled "may change", official tracking button', $u['email'] === 'sent' && $rows['Container number'] === 'CSQU3054383'
        && $rows['Shipping line'] === 'Hapag-Lloyd' && $rows['Shipped'] === '12 Nov 2026' && isset($rows['Estimated arrival (given by the shipping line; may change)'])
        && $last['button']['label'] === 'Track on Hapag-Lloyd' && str_starts_with($last['button']['url'], 'https://www.hapag-lloyd.com/'));
    check('container recorded in the audit log', $audit('SHIPMENT_CONTAINER_RECORDED') === 1);
    $mail->succeed = false;
    $f = $service->updateShipment($id, $form(['status' => 'ARRIVED', 'container_number' => 'CSQU3054383', 'shipped_date' => '2026-11-12', 'arrival_date' => '2026-12-21', 'notify' => '1']), $adminId, $ip, $ua);
    check('a failed email does not undo the update; reported and audited', $f['status'] === 'ok' && $f['email'] === 'failed' && $repo->find($id)['status'] === 'ARRIVED' && $audit('SHIPMENT_EMAIL_FAILED') === 1);
    $mail->succeed = true;
    $before = count($mail->sent);
    $back = $service->updateShipment($id, $form(['status' => 'IN_TRANSIT', 'container_number' => 'CSQU3054383', 'shipped_date' => '2026-11-12']), $adminId, $ip, $ua);
    check('moving back needs the confirmation (D8)', $back['status'] === 'invalid' && str_contains($back['errors']['status'], 'move the status back'));
    $back = $service->updateShipment($id, $form(['status' => 'IN_TRANSIT', 'container_number' => 'CSQU3054383', 'shipped_date' => '2026-11-12', 'confirm_back' => '1']), $adminId, $ip, $ua);
    check('with confirmation it moves back, and the customer is emailed automatically (every status change, 2026-10-22)', $back['status'] === 'ok' && $repo->find($id)['status'] === 'IN_TRANSIT'
        && $back['email'] === 'sent' && count($mail->sent) === $before + 1 && end($mail->sent)['content']['title'] === 'Your vehicle is on its way');
    check('saving without changes reports unchanged and sends nothing', $service->updateShipment($id, $form(['status' => 'IN_TRANSIT', 'container_number' => 'CSQU3054383', 'shipped_date' => '2026-11-12']), $adminId, $ip, $ua)['status'] === 'unchanged'
        && count($mail->sent) === $before + 1);
    $edit = $service->updateShipment($id, $form(['status' => 'IN_TRANSIT', 'container_number' => 'CSQU3054383', 'shipped_date' => '2026-11-12', 'estimated_arrival' => '2026-12-28']), $adminId, $ip, $ua);
    check('an edit that does not change the status sends no email', $edit['status'] === 'ok' && $edit['email'] === 'none' && count($mail->sent) === $before + 1);
    $again = $service->updateShipment($id, $form(['status' => 'IN_TRANSIT', 'container_number' => 'CSQU3054383', 'shipped_date' => '2026-11-12', 'estimated_arrival' => '2026-12-28', 'notify' => '1']), $adminId, $ip, $ua);
    check('"Send the email again" re-sends the current status', $again['email'] === 'sent' && count($mail->sent) === $before + 2);
    $auto = $service->updateShipment($id, $form(['status' => 'ARRIVED', 'container_number' => 'CSQU3054383', 'shipped_date' => '2026-11-12', 'arrival_date' => '2026-12-21', 'estimated_arrival' => '2026-12-28']), $adminId, $ip, $ua);
    check('a status change emails WITHOUT any box ticked', $auto['email'] === 'sent' && end($mail->sent)['content']['title'] === 'Your vehicle has arrived in Ghana');
    // back to "In transit" (with its estimate cleared) for the checks below
    $service->updateShipment($id, $form(['status' => 'IN_TRANSIT', 'container_number' => 'CSQU3054383', 'shipped_date' => '2026-11-12', 'estimated_arrival' => '2026-12-20', 'confirm_back' => '1']), $adminId, $ip, $ua);
    check('"Not yet shipped" never emails, even when ticked', $service->updateShipment((int) $noAccount['id'], $form(['vehicle_id' => (string) $sold2, 'customer_email' => 'walkin@example.test', 'customer_name' => 'Walk In', 'notify' => '1']), $adminId, $ip, $ua)['email'] === 'none');
    $noMail = $service->updateShipment((int) $noAccount['id'], $form(['vehicle_id' => (string) $sold2, 'customer_email' => '', 'customer_name' => 'Walk In', 'status' => 'SHIPMENT_BOOKED', 'notify' => '1']), $adminId, $ip, $ua);
    check('no email address: saved, reported as "no address"', $noMail['status'] === 'ok' && $noMail['email'] === 'no_address');
    check('audit descriptions hold no email address', (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE user_agent = 'shipment_test' AND description LIKE '%@%'")->fetchColumn() === 0);

    echo "\nEmail content for every status\n";
    $s = $repo->find($id);
    foreach (ShipmentValidator::EMAIL_STATUSES as $st) {
        $c = ShipmentEmail::content($s, $st, 'https://track.example/');
        check('content for ' . $st . ': title, greeting, vehicle row, no empty rows', $c !== null && $c['title'] !== '' && $c['greeting'] === 'Hello TEST Ama,'
            && $c['details'][0] === ['Vehicle', ShipmentEmail::vehicleLabel($s)] && array_filter($c['details'], static fn ($r) => $r[1] === '') === []);
    }
    check('"Not yet shipped" has no email', ShipmentEmail::content($s, 'NOT_YET_SHIPPED', 'https://track.example/') === null);
    $html = EmailTemplate::render(ShipmentEmail::content($s, 'IN_TRANSIT', 'https://track.example/'), ['name' => 'B', 'logo' => null, 'site' => null, 'phones' => []])['html'];
    check('rendered in the branded template, nothing unescaped', str_contains($html, 'Your vehicle is on its way') && str_contains($html, 'CSQU3054383') && !str_contains($html, '<script'));

    echo "\nCustomer view (D5) and access\n";
    $mine = $service->forCustomer($customerId);
    check('the customer sees their own shipment: vehicle, current status, last update, container, line, tracker link', count($mine) === 1 && $mine[0]['container'] === 'CSQU3054383'
        && $mine[0]['status'] === 'In transit to Ghana' && $mine[0]['step'] === 3 && str_starts_with((string) $mine[0]['tracking'], 'https://')
        && $mine[0]['line'] === 'Hapag-Lloyd' && str_ends_with($mine[0]['updated'], ' GMT'));
    check('the track update carries a few recorded vehicle details and a photo field (null when no real file)', array_key_exists('photo', $mine[0])
        && ($mine[0]['photo'] === null || str_starts_with($mine[0]['photo']['url'], '/uploads/vehicles/'))
        && array_slice(array_column($mine[0]['details'], 0), 0, 3) === ['Year', 'Make', 'Model']);
    check('status bar: six steps, three done, the fourth current', array_column($mine[0]['bar'], 'state') === ['done', 'done', 'done', 'current', 'todo', 'todo']);
    check('milestones: the four reached so far, oldest first, the last one current', array_column($mine[0]['reached'], 'title') === ['Preparing for shipment', 'Shipment booked', 'Container assigned', 'In transit to Ghana']
        && $mine[0]['reached'][3]['current'] === true && $mine[0]['reached'][2]['current'] === false && $mine[0]['upcoming'] === ['Arrived in Ghana', 'Delivered']);
    check('a date only where one was recorded (shipped date on "In transit"; none on booking or container)', $mine[0]['reached'][3]['date'] === '12 Nov 2026'
        && $mine[0]['reached'][1]['date'] === null && $mine[0]['reached'][2]['date'] === null);
    check('another customer sees nothing of it (no IDOR)', $service->forCustomer($otherCustomer) === []);
    check('internal notes and emails never reach the customer view', !str_contains(json_encode($mine), '@') && !array_key_exists('internal_notes', $mine[0]));
    $sold3 = $vehicle('sold', 'test-ship-sold3-' . bin2hex(random_bytes(3)));
    $booked = $service->createShipment($form(['vehicle_id' => (string) $sold3, 'customer_email' => 'ama.test-ship@example.test', 'customer_name' => 'TEST Ama', 'shipping_company' => '']), $adminId, $ip, $ua);
    $pdo->exec("UPDATE shipments SET status = 'SHIPMENT_BOOKED' WHERE id = " . (int) $booked['id']);
    $view = array_values(array_filter($service->forCustomer($customerId), static fn (array $s): bool => $s['status'] === 'Shipment booked'))[0] ?? null;
    check('the booked test shipment was created', $booked['status'] === 'ok', json_encode($booked['errors']));
    check('booked is never shown as departed: "In transit" is still to come', $view !== null && !in_array('In transit to Ghana', array_column($view['reached'], 'title'), true)
        && in_array('In transit to Ghana', $view['upcoming'], true));
    check('missing information stays empty, never invented: no container, no tracker link, no estimate; the line defaults to Hapag-Lloyd (owner, 2026-10-22)', $view !== null && $view['container'] === null
        && $view['line'] === 'Hapag-Lloyd' && $view['tracking'] === null && $view['estimate'] === null);

    echo "\nDelete (D10)\n";
    check('a booked / emailed shipment cannot be deleted', $service->deleteShipment($id, $adminId, $ip, $ua) === 'refused' && $repo->find($id) !== null);
    $fresh = $service->createShipment($form(['vehicle_id' => (string) $available]), $adminId, $ip, $ua);
    $pdo->exec("UPDATE vehicles SET availability_status = 'sold' WHERE id = $available");
    $fresh = $service->createShipment($form(['vehicle_id' => (string) $available]), $adminId, $ip, $ua);
    check('a new, never-emailed shipment can be deleted, and it is audited', $service->deleteShipment((int) $fresh['id'], $adminId, $ip, $ua) === 'ok' && $repo->find((int) $fresh['id']) === null && $audit('SHIPMENT_DELETED') === 1);

    echo "\nList\n";
    $list = $service->listShipments(ShipmentValidator::filters(['q' => 'CSQU3054383']));
    check('search by container number', $list['total'] === $realWithTestContainer + 1);
    check('LIKE wildcards are literal', $service->listShipments(ShipmentValidator::filters(['q' => '%']))['total'] === 0);
    check('filter by status', $service->listShipments(ShipmentValidator::filters(['status' => 'IN_TRANSIT']))['total'] === $realInTransit + 1);
} finally {
    $pdo->rollBack();
}

echo "\nController\n";
$staff = ['id' => 1, 'name' => 'Staff', 'role' => 'staff'];
$controller = new ShipmentController();
foreach (['index' => $controller->index($staff, []), 'create' => $controller->create($staff, 'POST', [], $ip, $ua), 'edit' => $controller->edit($staff, '1', 'GET', [], $ip, $ua),
             'delete' => $controller->delete($staff, 'POST', null, ['id' => '1'], $ip, $ua), 'preview' => $controller->preview($staff, '1', 'IN_TRANSIT')] as $action => $res) {
    check("a role without the shipments permission is refused: $action", $res['http'] === 403);
}
check('administrator holds the shipments permission', AuthorizationService::can('administrator', ShipmentController::PERMISSION));
$admin = ['id' => $adminId, 'name' => 'Admin', 'role' => 'administrator'];
$_SESSION = [];
$pdo->beginTransaction();   // the controller audits CSRF failures: keep those rows out of the real table
foreach (['create' => $controller->create($admin, 'POST', ['customer_name' => 'x'], $ip, $ua), 'delete' => $controller->delete($admin, 'POST', null, ['id' => '1'], $ip, $ua)] as $action => $res) {
    check("a POST without a valid CSRF token is refused before anything else: $action", $res['http'] === 400 && $res['problem'] === 'csrf');
}
$pdo->rollBack();
check('preview of an unknown status or shipment is "not found"', $controller->preview($admin, '1', 'LOST')['http'] === 404 && $controller->preview($admin, 'abc', 'IN_TRANSIT')['http'] === 404);

echo "\nTrack page: tracking number search and status bar (2026-10-22)\n";
$mine = [['container' => 'HLXU1234569', 'bar' => []], ['container' => null, 'bar' => []]];
check('no number = no search', ShipmentService::findContainer($mine, null)['status'] === 'none' && ShipmentService::findContainer($mine, '')['status'] === 'none');
$hit = ShipmentService::findContainer($mine, ' hlxu 123456-9 ');
check('own container found (spaces, hyphens and case ignored)', $hit['status'] === 'found' && $hit['index'] === 0 && $hit['number'] === 'HLXU1234569');
check('a number that is not on the account is "not found" (nothing else is searched)', ShipmentService::findContainer($mine, 'MSCU7654321') === ['status' => 'not_found', 'number' => 'MSCU7654321', 'index' => null]);
foreach (['abc', 'HLXU12345', "HLXU1234569' OR 1=1", str_repeat('A', 500), ['HLXU1234569']] as $bad) {
    check('invalid input refused: ' . substr(json_encode($bad), 0, 25), ShipmentService::findContainer($mine, $bad)['status'] === 'invalid');
}
check('six short bar labels, one per status', count(ShipmentService::BAR_LABELS) === count(ShipmentService::MILESTONES) && count(ShipmentService::BAR_LABELS) === 6);

echo "\nCleanup\n";
check('the real shipments table is unchanged', (int) $pdo->query('SELECT COUNT(*) FROM shipments')->fetchColumn() === $realShipments);
check('no audit rows remain from this run', (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE user_agent = 'shipment_test'")->fetchColumn() === 0);
foreach (['shipments', 'vehicles', 'customers', 'audit_logs'] as $t) {
    $pdo->exec("ALTER TABLE `$t` AUTO_INCREMENT = 1");
}

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
