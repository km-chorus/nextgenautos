<?php

declare(strict_types=1);

/**
 * Tests for customer password recovery and the sign-in rate limits of 2026-10-22: one email with a link and a 6-digit
 * code; no account enumeration; 3 emails per address per hour; link/code expiry, single use, newest wins, 5 wrong codes;
 * IP throttling of failed resets; other sessions end after a reset; 3 wrong passwords throttle sign-in. The link and code are
 * never stored in the database: only hashes, in a short-lived file cache (a throwaway folder here) that is deleted after use.
 * One rolled-back transaction; emails go to a recording double (nothing is sent).
 * Run: php database/tests/password_reset_test.php
 */

require_once dirname(__DIR__, 2) . '/app/services/CustomerPasswordResetService.php';

$passed = 0;
$failed = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "  PASS  $label\n"; }
    else { $failed++; echo "  FAIL  $label" . ($detail !== '' ? " -> $detail" : '') . "\n"; }
}
final class ResetMail extends EmailService
{
    public array $sent = [];
    public function sendTemplate(string $toEmail, string $toName, array $content, ?string $replyToEmail = null, string $replyToName = ''): bool { $this->sent[] = ['to' => $toEmail, 'content' => $content]; return true; }
}
final class NoSession extends CustomerSessionService
{
    public ?int $id = null;
    public ?int $at = null;
    public function establish(int $customerId, string $welcome = 'back'): void { $this->id = $customerId; $this->at = time(); }
    public function currentCustomerId(): ?int { return $this->id; }
    public function authenticatedAt(): ?int { return $this->at; }
    public function destroy(): void { $this->id = null; $this->at = null; }
}

putenv('APP_URL=https://www.example.test');
$pdo = Database::connection();
$ua = 'password_reset_test';
$pdo->beginTransaction();
try {
    $customers = new CustomerRepository($pdo);
    $cacheDir = sys_get_temp_dir() . '/reset_store_test_' . bin2hex(random_bytes(4));
    $store = new ResetCodeStore($cacheDir, random_bytes(32));
    $files = static fn (): array => glob($cacheDir . '/*.json') ?: [];
    $audit = new AuditLogRepository($pdo);
    $mail = new ResetMail();
    $svc = new CustomerPasswordResetService($pdo, $customers, $store, $audit, $mail);
    $email = 'reset.test-pw@example.test';
    $id = (int) $customers->create('TEST Yaw', $email, password_hash('Old-pass-2026!x', PASSWORD_DEFAULT));
    $link = static function (array $sent): string { return (string) $sent['content']['button']['url']; };
    $codeOf = static function (array $sent): string { return (string) array_column($sent['content']['details'], 1, 0)['Your code']; };
    $token = static fn (string $url): string => substr($url, strpos($url, '?t=') + 3);
    $ip = '203.0.113.60';

    echo "Request\n";
    check('a non-email is refused', $svc->request('not an email', $ip, $ua) === 'invalid' && $mail->sent === []);
    check('unknown address: the same answer, and no email', $svc->request('nobody.test-pw@example.test', $ip, $ua) === 'sent' && $mail->sent === []);
    check('known address: one branded email with a link and a 6-digit code', $svc->request(' Reset.Test-PW@example.test ', $ip, $ua) === 'sent' && count($mail->sent) === 1
        && $mail->sent[0]['to'] === $email && str_starts_with($link($mail->sent[0]), 'https://nextgenautosandparts.com/reset-password/?t=')
        && preg_match('/^[0-9]{6}$/', $codeOf($mail->sent[0])) === 1);
    check('nothing about the reset is stored in the database (no reset table)', $pdo->query("SHOW TABLES LIKE 'customer_password_resets'")->rowCount() === 0);
    $cached = $files();
    $raw = $cached === [] ? '' : (string) file_get_contents($cached[0]);
    $tok = substr($link($mail->sent[0]), strpos($link($mail->sent[0]), '.', strpos($link($mail->sent[0]), '?t=')) + 1);
    check('the cache holds one entry with hashes only (not the token, not the code, not the customer id in the name)', count($cached) === 1
        && !str_contains($raw, $tok) && !str_contains($raw, '"' . $codeOf($mail->sent[0]) . '"') && str_contains($raw, hash('sha256', $tok))
        && preg_match('#/[a-f0-9]{64}\.json$#', $cached[0]) === 1);
    check('the audit log holds no email address, token or code', (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE user_agent = 'password_reset_test' AND (description LIKE '%@%' OR description LIKE '%" . $codeOf($mail->sent[0]) . "%')")->fetchColumn() === 0);

    $svc->request($email, '203.0.113.61', $ua);
    $svc->request($email, '203.0.113.62', $ua);
    check('3 emails per address per hour (even from other IPs), then "too many requests"', count($mail->sent) === 3 && $svc->request($email, '203.0.113.63', $ua) === 'throttled' && count($mail->sent) === 3);
    $ipSpray = '203.0.113.70';
    for ($i = 0; $i < CustomerAuthService::MAX_LOGIN_FAILURES_PER_IP; $i++) { $svc->request("spray$i.test-pw@example.test", $ipSpray, $ua); }
    check('10 requests per IP per hour, then "too many requests"', $svc->request('other.test-pw@example.test', $ipSpray, $ua) === 'throttled');

    echo "\nLink\n";
    $latest = end($mail->sent);
    $old = $mail->sent[0];
    check('a newer email cancels the older link and code', $svc->checkLink($token($link($old)), $ip) === 'invalid');
    check('the latest link is usable', $svc->checkLink($token($link($latest)), $ip) === 'ok');
    foreach (['', 'abc', '1.' . str_repeat('a', 64), $token($link($latest)) . 'x', ['x']] as $bad) {
        check('malformed or forged link refused: ' . substr(json_encode($bad), 0, 24), $svc->checkLink($bad, $ip) === 'invalid');
    }
    $r = $svc->reset(['t' => $token($link($latest)), 'new_password' => 'short', 'new_password_confirm' => 'short'], $ip, $ua);
    check('the password policy applies (and the link stays usable)', $r['status'] === 'invalid' && isset($r['errors']['new_password']) && $svc->checkLink($token($link($latest)), $ip) === 'ok');

    // a session signed in before the reset
    $session = new NoSession();
    $auth = new CustomerAuthService($customers, $audit, $session, null);
    $session->establish($id);
    $session->at = time() - 60;
    check('(before) the other session is signed in', $auth->currentCustomer() !== null);
    $r = $svc->reset(['t' => $token($link($latest)), 'new_password' => 'New-pass-2026!x', 'new_password_confirm' => 'New-pass-2026!x'], $ip, $ua);
    $row = $customers->findById($id);
    check('the link sets the new password (hashed)', $r['status'] === 'ok' && password_verify('New-pass-2026!x', (string) $row['password_hash']));
    check('the email counts as confirmed (the customer read it)', $row['email_verified_at'] !== null);
    check('every other session of the account ends', $auth->currentCustomer() === null);
    check('the cache entry is deleted as soon as the link is used', $files() === []);
    check('the link works only once', $svc->reset(['t' => $token($link($latest)), 'new_password' => 'Again-pass-2026!x', 'new_password_confirm' => 'Again-pass-2026!x'], $ip, $ua)['status'] === 'invalid');
    $oldTok = str_repeat('ab', 32);
    $store->put($id, hash('sha256', $oldTok), 'x', -1);   // an entry whose time is up
    check('an expired entry with the WRONG secret says only "invalid" (nothing revealed); the sweep deletes the entry', $svc->checkLink($id . '.' . str_repeat('cd', 32), $ip) === 'invalid' && $files() === []);
    $store->put($id, hash('sha256', $oldTok), 'x', -1);
    check('an expired link is refused as "expired", and its entry is deleted', $svc->checkLink($id . '.' . $oldTok, $ip) === 'expired' && $files() === []);
    $store->put(999999001, 'a', 'b', -5);
    $store->put(999999002, 'a', 'b', 600);
    check('expired entries are swept away (valid ones stay)', $store->purgeExpired() === 1 && count($files()) === 1);
    $store->delete(999999002);

    echo "\nCode\n";
    $pdo->exec("DELETE FROM audit_logs WHERE user_agent = 'password_reset_test' AND event_type = 'CUSTOMER_PASSWORD_RESET_REQUESTED'");   // inside the rolled-back transaction: lets the test request again
    $svc->request($email, $ip, $ua);
    $code = $codeOf(end($mail->sent));
    $wrong = $code === '000000' ? '111111' : '000000';
    $r = $svc->reset(['email' => $email, 'code' => $wrong, 'new_password' => 'Code-pass-2026!x', 'new_password_confirm' => 'Code-pass-2026!x'], '203.0.113.80', $ua);
    check('a wrong code is refused with a plain message', $r['status'] === 'invalid' && isset($r['errors']['code']));
    $r = $svc->reset(['email' => $email, 'code' => '12 34', 'new_password' => 'Code-pass-2026!x', 'new_password_confirm' => 'Code-pass-2026!x'], '203.0.113.80', $ua);
    check('a code that is not 6 digits is refused', $r['status'] === 'invalid' && isset($r['errors']['code']));
    $r = $svc->reset(['email' => $email, 'code' => substr($code, 0, 3) . ' ' . substr($code, 3), 'new_password' => 'Code-pass-2026!x', 'new_password_confirm' => 'Code-pass-2026!x'], '203.0.113.81', $ua);
    check('the right code (spaces allowed) with the account email sets the new password', $r['status'] === 'ok' && password_verify('Code-pass-2026!x', (string) $customers->findById($id)['password_hash']));
    check('the cache entry is deleted as soon as the code is used', $files() === []);
    check('the code works only once', $svc->reset(['email' => $email, 'code' => $code, 'new_password' => 'Code2-pass-2026!x', 'new_password_confirm' => 'Code2-pass-2026!x'], '203.0.113.82', $ua)['status'] === 'invalid');

    $pdo->exec("DELETE FROM audit_logs WHERE user_agent = 'password_reset_test' AND event_type = 'CUSTOMER_PASSWORD_RESET_REQUESTED'");
    $svc->request($email, $ip, $ua);
    $code = $codeOf(end($mail->sent));
    for ($i = 0; $i < CustomerPasswordResetService::MAX_CODE_ATTEMPTS; $i++) {
        $svc->reset(['email' => $email, 'code' => sprintf('%06d', ((int) $code + 1 + $i) % 1000000), 'new_password' => 'Brute-pass-2026!x', 'new_password_confirm' => 'Brute-pass-2026!x'], '203.0.113.' . (90 + $i), $ua);
    }
    check('5 wrong codes delete the cache entry', $files() === []);
    check('5 wrong codes cancel the reset: even the right code then fails', $svc->reset(['email' => $email, 'code' => $code, 'new_password' => 'Brute-pass-2026!x', 'new_password_confirm' => 'Brute-pass-2026!x'], '203.0.113.99', $ua)['status'] === 'invalid'
        && password_verify('Code-pass-2026!x', (string) $customers->findById($id)['password_hash']));
    $ipFail = '203.0.113.110';
    for ($i = 0; $i < CustomerPasswordResetService::MAX_FAILURES_PER_IP; $i++) {
        $svc->reset(['email' => 'nobody.test-pw@example.test', 'code' => '123456', 'new_password' => 'x', 'new_password_confirm' => 'x'], $ipFail, $ua);
    }
    check('10 failed resets from one IP in 15 minutes: "too many attempts"', $svc->reset(['email' => $email, 'code' => '123456', 'new_password' => 'x', 'new_password_confirm' => 'x'], $ipFail, $ua)['status'] === 'throttled'
        && $svc->checkLink('1.' . str_repeat('a', 64), $ipFail) === 'throttled');

    echo "\nSign-in limit\n";
    $ipLogin = '203.0.113.120';
    $auth2 = new CustomerAuthService($customers, $audit, new NoSession(), null);
    for ($i = 0; $i < 3; $i++) { $auth2->attempt($email, 'Wrong-pass-2026!' . $i, $ipLogin, $ua); }
    check('after 3 wrong passwords the account is throttled, even with the right password', $auth2->attempt($email, 'Code-pass-2026!x', $ipLogin, $ua)['status'] === 'throttled');
    check('...also from another IP (the limit is per account)', $auth2->attempt($email, 'Code-pass-2026!x', '203.0.113.121', $ua)['status'] === 'throttled');
} finally {
    $pdo->rollBack();
}

echo "\nCleanup\n";
check('no test customers remain', (int) $pdo->query("SELECT COUNT(*) FROM customers WHERE email LIKE '%.test-pw@example.test'")->fetchColumn() === 0
);
foreach (glob($cacheDir . '/{,.}*', GLOB_BRACE) ?: [] as $f) { if (is_file($f)) { unlink($f); } }
@rmdir($cacheDir);
check('the throwaway cache folder is removed', !is_dir($cacheDir));
check('no audit rows remain from this run', (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE user_agent = 'password_reset_test'")->fetchColumn() === 0);
foreach (['customers', 'audit_logs'] as $t) { $pdo->exec("ALTER TABLE `$t` AUTO_INCREMENT = 1"); }

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
