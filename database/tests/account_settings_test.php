<?php

declare(strict_types=1);

/**
 * Tests for the account settings and sign-in experience of 2026-10-22: change name, change / set password (current
 * password, policy, confirmation, throttling, audit), the one-time welcome flag, the header items and the default
 * destination after sign-in. One rolled-back transaction; no email is sent.
 * Run: php database/tests/account_settings_test.php
 */

require_once dirname(__DIR__, 2) . '/app/services/CustomerAccountService.php';
require_once dirname(__DIR__, 2) . '/app/helpers/public_ui.php';

$passed = 0;
$failed = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "  PASS  $label\n"; }
    else { $failed++; echo "  FAIL  $label" . ($detail !== '' ? " -> $detail" : '') . "\n"; }
}
/** Session double: records what the service asks of the session (no cookies, no headers in the CLI). */
final class SpySession extends CustomerSessionService
{
    public array $established = [];
    public int $welcomeConsumed = 0;
    public function establish(int $customerId, string $welcome = 'back'): void { $this->established[] = [$customerId, $welcome]; }
    public function consumeWelcome(): ?string { $this->welcomeConsumed++; return null; }
}

$pdo = Database::connection();
$ip = '203.0.113.50';
$ua = 'account_settings_test';

echo "Header items\n";
$guest = public_nav_items(false);
$member = public_nav_items(true);
$routes = static fn (array $items): array => array_column($items, 'route');
check('signed out: the user icon (Login)', in_array('login', $routes($guest), true) && !in_array('logout', $routes($guest), true));
check('signed in: a settings icon and a sign-out icon instead of the user icon', in_array('account-settings', $routes($member), true) && in_array('logout', $routes($member), true)
    && !in_array('login', $routes($member), true));
$logout = array_values(array_filter($member, static fn (array $i): bool => $i['route'] === 'logout'))[0];
check('sign out is a form (POST), not a link', !empty($logout['logout']) && $logout['path'] === '/account/');
check('the settings page is a safe return address after sign-in', CustomerSessionService::isReturnTarget('/account/settings/'));

echo "\nAfter sign-in\n";
$src = file_get_contents(dirname(__DIR__, 2) . '/app/controllers/CustomerAuthController.php');
check('the default destination after signing in or registering is the Track page', substr_count($src, "consumeReturnTo() ?? '/track/'") === 5 && !str_contains($src, "consumeReturnTo() ?? '/account/'"));

$pdo->beginTransaction();
try {
    $customers = new CustomerRepository($pdo);
    $email = 'settings.test-acct@example.test';
    $id = (int) $customers->create('TEST Kwame', $email, password_hash('Old-pass-2026!x', PASSWORD_DEFAULT));
    $google = (int) $customers->createFromGoogle('TEST Esi', 'g.test-acct@example.test', 'g-' . bin2hex(random_bytes(6)));
    $session = new SpySession();
    $svc = new CustomerAccountService($customers, new AuditLogRepository($pdo), $session);
    $audit = static fn (string $event): int => (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE user_agent = 'account_settings_test' AND event_type = " . $pdo->quote($event))->fetchColumn();

    echo "\nName\n";
    check('a new name is saved and audited', $svc->changeName($id, ['name' => '  TEST   Kwame  Mensah '], $ip, $ua)['status'] === 'ok'
        && $customers->findById($id)['name'] === 'TEST Kwame Mensah' && $audit('CUSTOMER_NAME_CHANGED') === 1);
    check('the same name again changes nothing', $svc->changeName($id, ['name' => 'TEST Kwame Mensah'], $ip, $ua)['status'] === 'unchanged');
    foreach (['', '   ', '<script>', str_repeat('a', 101), ['x']] as $bad) {
        check('invalid name refused: ' . substr(json_encode($bad), 0, 20), $svc->changeName($id, ['name' => $bad], $ip, $ua)['status'] === 'invalid');
    }
    check('the stored name is unchanged after the refusals', $customers->findById($id)['name'] === 'TEST Kwame Mensah');

    echo "\nPassword\n";
    $r = $svc->changePassword($id, ['new_password' => 'New-pass-2026!x', 'new_password_confirm' => 'New-pass-2026!x'], $ip, $ua);
    check('the current password is required', $r['status'] === 'invalid' && isset($r['errors']['current_password']));
    $r = $svc->changePassword($id, ['current_password' => 'Old-pass-2026!x', 'new_password' => 'short', 'new_password_confirm' => 'short'], $ip, $ua);
    check('the password policy applies', $r['status'] === 'invalid' && isset($r['errors']['new_password']));
    $r = $svc->changePassword($id, ['current_password' => 'Old-pass-2026!x', 'new_password' => 'New-pass-2026!x', 'new_password_confirm' => 'New-pass-2026!y'], $ip, $ua);
    check('the two new passwords must match', $r['status'] === 'invalid' && isset($r['errors']['new_password_confirm']));
    $r = $svc->changePassword($id, ['current_password' => 'Wrong-pass-2026!', 'new_password' => 'New-pass-2026!x', 'new_password_confirm' => 'New-pass-2026!x'], $ip, $ua);
    check('a wrong current password is refused and audited', $r['status'] === 'invalid' && isset($r['errors']['current_password']) && $audit('CUSTOMER_PASSWORD_CHANGE_FAILED') === 1
        && password_verify('Old-pass-2026!x', (string) $customers->findById($id)['password_hash']));
    $r = $svc->changePassword($id, ['current_password' => 'Old-pass-2026!x', 'new_password' => 'New-pass-2026!x', 'new_password_confirm' => 'New-pass-2026!x'], $ip, $ua);
    $row = $customers->findById($id);
    check('with the right current password the password changes (hashed), audited', $r['status'] === 'ok' && password_verify('New-pass-2026!x', (string) $row['password_hash'])
        && !str_contains((string) $row['password_hash'], 'New-pass') && $audit('CUSTOMER_PASSWORD_CHANGED') === 1);
    check('the session id is renewed after the change, without a welcome toast', $session->established === [[$id, 'back']] && $session->welcomeConsumed === 1);
    check('no password or name appears in the audit descriptions', (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE user_agent = 'account_settings_test' AND (description LIKE '%pass-2026%' OR description LIKE '%Kwame%')")->fetchColumn() === 0);
    for ($i = 0; $i < 5; $i++) {
        $svc->changePassword($id, ['current_password' => 'Wrong-' . $i . '-2026!x', 'new_password' => 'Other-pass-2026!x', 'new_password_confirm' => 'Other-pass-2026!x'], $ip, $ua);
    }
    check('after 5 wrong current passwords in 15 minutes the change is throttled', $svc->changePassword($id, ['current_password' => 'New-pass-2026!x', 'new_password' => 'Other-pass-2026!x', 'new_password_confirm' => 'Other-pass-2026!x'], $ip, $ua)['status'] === 'throttled'
        && password_verify('New-pass-2026!x', (string) $customers->findById($id)['password_hash']));
    check('throttling counts per account: another account is not affected', $svc->changePassword($google, ['new_password' => 'Google-set-2026!x', 'new_password_confirm' => 'Google-set-2026!x'], $ip, $ua)['status'] === 'ok');
    check('a Google-only account can set its first password (no current password needed)', password_verify('Google-set-2026!x', (string) $customers->findById($google)['password_hash']));
    check('hasPassword reflects the account', $svc->hasPassword($id) && $svc->hasPassword($google));
} finally {
    $pdo->rollBack();
}

echo "\nCleanup\n";
check('no test customers remain', (int) $pdo->query("SELECT COUNT(*) FROM customers WHERE email LIKE '%.test-acct@example.test'")->fetchColumn() === 0);
check('no audit rows remain from this run', (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE user_agent = 'account_settings_test'")->fetchColumn() === 0);
foreach (['customers', 'audit_logs'] as $t) { $pdo->exec("ALTER TABLE `$t` AUTO_INCREMENT = 1"); }

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
