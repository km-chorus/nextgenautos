<?php

declare(strict_types=1);

/**
 * Tests for spec 22: email verification, shipments only for verified accounts, the Google pre-hijack defence,
 * favourites, reservation requests and safe return addresses. One rolled-back transaction; no email is sent.
 * Run: php database/tests/customer_dashboard_test.php
 */

require_once dirname(__DIR__, 2) . '/app/services/CustomerVerificationService.php';
require_once dirname(__DIR__, 2) . '/app/services/CustomerDashboardService.php';
require_once dirname(__DIR__, 2) . '/app/services/ShipmentService.php';
require_once dirname(__DIR__, 2) . '/app/repositories/CustomerRepository.php';
require_once dirname(__DIR__, 2) . '/app/repositories/EnquiryRepository.php';
require_once dirname(__DIR__, 2) . '/app/services/CustomerSessionService.php';

$passed = 0;
$failed = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "  PASS  $label\n"; }
    else { $failed++; echo "  FAIL  $label" . ($detail !== '' ? " -> $detail" : '') . "\n"; }
}
final class RecordingMail2 extends EmailService
{
    public array $sent = [];
    public function sendTemplate(string $toEmail, string $toName, array $content, ?string $replyToEmail = null, string $replyToName = ''): bool { $this->sent[] = ['to' => $toEmail, 'content' => $content]; return true; }
}

putenv('APP_URL=https://www.example.test');
$pdo = Database::connection();
$adminId = (int) $pdo->query('SELECT id FROM admin_users ORDER BY id LIMIT 1')->fetchColumn();
$brand = (int) $pdo->query('SELECT id FROM brands ORDER BY id LIMIT 1')->fetchColumn();
$ip = '203.0.113.30';
$ua = 'dashboard_test';

echo "Return addresses\n";
foreach (['/', '/track/', '/account/', '/vehicles/', '/vehicle/2023-honda-accord/'] as $ok) { check('allowed: ' . $ok, CustomerSessionService::isReturnTarget($ok)); }
foreach (['//evil.test/', 'https://evil.test/', '/vehicle/../admin/', "/vehicle/a/\n", '/admin/', '/vehicle/A/'] as $bad) { check('refused: ' . json_encode($bad), !CustomerSessionService::isReturnTarget($bad)); }

$pdo->beginTransaction();
try {
    $customers = new CustomerRepository($pdo);
    $mkCustomer = static function (string $email, bool $google = false) use ($pdo, $customers): int {
        return $google ? (int) $customers->createFromGoogle('TEST G', $email, 'g-' . bin2hex(random_bytes(6))) : (int) $customers->create('TEST P', $email, password_hash('pw-' . bin2hex(random_bytes(6)), PASSWORD_DEFAULT));
    };
    $vehicle = static function (string $status, bool $published = true) use ($pdo, $brand): array {
        $slug = 'test-dash-' . bin2hex(random_bytes(4));
        $pdo->prepare('INSERT INTO vehicles (brand_id, year, model, price, slug, availability_status, is_published) VALUES (?, 2022, ?, 5000, ?, ?, ?)')
            ->execute([$brand, 'DASHMODEL', $slug, $status, $published ? 1 : 0]);

        return ['id' => (int) $pdo->lastInsertId(), 'slug' => $slug];
    };
    $mail = new RecordingMail2();
    $verify = new CustomerVerificationService($pdo, new AuditLogRepository($pdo), $mail);
    $isVerified = static fn (int $id): bool => $pdo->query('SELECT email_verified_at FROM customers WHERE id = ' . $id)->fetchColumn() !== null;

    echo "\nEmail verification\n";
    $pw = $mkCustomer('pw.test-dash@example.test');
    $g = $mkCustomer('g.test-dash@example.test', true);
    check('a new password account is not verified; a Google account is', !$isVerified($pw) && $isVerified($g));
    check('send(): one branded email with a confirm link to the account address', $verify->send($pw, $ip, $ua) === 'sent' && count($mail->sent) === 1
        && $mail->sent[0]['to'] === 'pw.test-dash@example.test' && str_starts_with($mail->sent[0]['content']['button']['url'], 'https://nextgenautosandparts.com/verify-email/?t='));
    $verify->send($pw, $ip, $ua); $verify->send($pw, $ip, $ua);
    check('at most 3 links per hour', $verify->send($pw, $ip, $ua) === 'throttled');
    check('already verified accounts get no link', $verify->send($g, $ip, $ua) === 'already');
    $token = (string) CustomerVerificationService::token($pw, 'pw.test-dash@example.test');
    foreach (['', 'abc', substr($token, 0, -1) . (substr($token, -1) === 'a' ? 'b' : 'a'), str_replace((string) $pw . '.', (string) $g . '.', $token), ['x']] as $bad) {
        check('invalid link refused: ' . substr(json_encode($bad), 0, 30), $verify->verify($bad, $ip, $ua) === 'invalid');
    }
    check('a link for an old email stops working', $verify->verify(CustomerVerificationService::token($pw, 'old@example.test'), $ip, $ua) === 'invalid');
    check('an expired link is refused', $verify->verify(CustomerVerificationService::token($pw, 'pw.test-dash@example.test', time() - 10), $ip, $ua) === 'expired' && !$isVerified($pw));

    echo "\nShipments only for verified accounts\n";
    $v1 = $vehicle('sold');
    $ship = ShipmentService::create();
    $r = (new ShipmentService($pdo, new ShipmentRepository($pdo), new AuditLogRepository($pdo), $mail))->createShipment(
        ['vehicle_id' => (string) $v1['id'], 'status' => 'SHIPMENT_BOOKED', 'customer_name' => 'TEST P', 'customer_email' => 'pw.test-dash@example.test'], $adminId, $ip, $ua);
    $sid = (int) $r['id'];
    check('a shipment for an UNVERIFIED account is not linked', $pdo->query('SELECT customer_id FROM shipments WHERE id = ' . $sid)->fetchColumn() === null);
    check('and is not shown to that account', (new ShipmentService($pdo, new ShipmentRepository($pdo), new AuditLogRepository($pdo), $mail))->forCustomer($pw) === []);
    check('a valid link verifies the account', $verify->verify($token, $ip, $ua) === 'verified' && $isVerified($pw));
    check('verification links the waiting shipment', (int) $pdo->query('SELECT customer_id FROM shipments WHERE id = ' . $sid)->fetchColumn() === $pw);
    check('now it is shown to the account', count((new ShipmentService($pdo, new ShipmentRepository($pdo), new AuditLogRepository($pdo), $mail))->forCustomer($pw)) === 1);
    check('using the link again says "already confirmed"', $verify->verify($token, $ip, $ua) === 'already');
    $pdo->exec('UPDATE shipments SET customer_id = ' . $g . ' WHERE id = ' . $sid);
    $pdo->exec('UPDATE customers SET email_verified_at = NULL WHERE id = ' . $g);
    check('defence in depth: a link to an account that is not verified shows nothing', (new ShipmentService($pdo, new ShipmentRepository($pdo), new AuditLogRepository($pdo), $mail))->forCustomer($g) === []);

    echo "\nGoogle takeover of an unconfirmed account (pre-hijack defence)\n";
    $squat = $mkCustomer('owner.test-dash@example.test');
    $customers->linkGoogle($squat, 'g-owner-' . bin2hex(random_bytes(4)));
    $row = $customers->findById($squat);
    check('linking Google to an UNCONFIRMED password account removes that password and verifies the email', $row['password_hash'] === null && $row['email_verified_at'] !== null);
    $real = $mkCustomer('real.test-dash@example.test');
    $pdo->exec('UPDATE customers SET email_verified_at = UTC_TIMESTAMP() WHERE id = ' . $real);
    $customers->linkGoogle($real, 'g-real-' . bin2hex(random_bytes(4)));
    check('a CONFIRMED account keeps its password when Google is linked', $customers->findById($real)['password_hash'] !== null);

    echo "\nFavourites\n";
    $dash = new CustomerDashboardService($pdo);
    $pub = $vehicle('available');
    $res = $vehicle('reserved');
    $hidden = $vehicle('available', false);
    $sold = $vehicle('sold');
    check('a public vehicle can be saved (twice = once)', $dash->setFavourite($pw, $pub['slug'], true) === 'added' && $dash->setFavourite($pw, $pub['slug'], true) === 'added'
        && (int) $pdo->query('SELECT COUNT(*) FROM customer_favourites WHERE customer_id = ' . $pw)->fetchColumn() === 1);
    check('a reserved vehicle can be saved', $dash->setFavourite($pw, $res['slug'], true) === 'added');
    check('an unpublished or sold vehicle cannot be saved', $dash->setFavourite($pw, $hidden['slug'], true) === 'not_found' && $dash->setFavourite($pw, $sold['slug'], true) === 'not_found');
    check('malformed slugs are refused', $dash->setFavourite($pw, "x' OR 1=1", true) === 'not_found' && $dash->setFavourite($pw, '', true) === 'not_found');
    check('favouriteSlugs lists the saved public vehicles', count($dash->favouriteSlugs($pw)) === 2 && in_array($pub['slug'], $dash->favouriteSlugs($pw), true));
    $pdo->exec("UPDATE vehicles SET availability_status = 'sold' WHERE id = " . $res['id']);
    $favs = $dash->favourites($pw);
    $gone = array_values(array_filter($favs, static fn (array $f): bool => $f['slug'] === $res['slug']))[0] ?? null;
    check('a favourite that was sold stays, marked "no longer available", without link or price', $gone !== null && $gone['status'] === 'No longer available' && $gone['url'] === null && $gone['price'] === null);
    check('another customer sees none of these favourites', $dash->favourites($g) === [] && $dash->favouriteSlugs($g) === []);
    check('remove works', $dash->setFavourite($pw, $pub['slug'], false) === 'removed' && !in_array($pub['slug'], $dash->favouriteSlugs($pw), true));

    echo "\nReservation requests\n";
    $enq = new EnquiryRepository($pdo);
    $values = ['name' => 'TEST P', 'email' => 'pw.test-dash@example.test', 'phone' => '0244000000', 'message' => 'TEST'];
    $enq->insert($pub['id'], 'purchase', $values, $pw);
    $enq->insert($pub['id'], 'vehicle', $values, $pw);     // a question, not a reservation
    $enq->insert($pub['id'], 'purchase', $values, null);   // sent signed out
    $enq->insert($pub['id'], 'purchase', $values, $g);     // someone else's
    $mine = $dash->reservationRequests($pw);
    check('only my reservation requests (not questions, not signed-out ones, not other customers\')', count($mine) === 1 && $mine[0]['status'] === 'Received' && $mine[0]['url'] === '/vehicle/' . $pub['slug'] . '/');
    check('the stored customer id is the one given by the server', (int) $pdo->query("SELECT COUNT(*) FROM enquiries WHERE customer_id = $pw")->fetchColumn() === 2);
} finally {
    $pdo->rollBack();
}

echo "\nCleanup\n";
check('no test customers remain', (int) $pdo->query("SELECT COUNT(*) FROM customers WHERE email LIKE '%.test-dash@example.test'")->fetchColumn() === 0);
check('no audit rows remain from this run', (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE user_agent = 'dashboard_test'")->fetchColumn() === 0);
foreach (['customers', 'vehicles', 'shipments', 'enquiries', 'audit_logs'] as $t) { $pdo->exec("ALTER TABLE `$t` AUTO_INCREMENT = 1"); }

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
