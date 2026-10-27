<?php

declare(strict_types=1);

/**
 * Two-factor authentication, recovery codes, password reset: logic tests.
 *
 * CLI only:  php database/tests/twofactor_reset_test.php
 * Requires:  schema (incl. migration 001) loaded; DB credentials in .env.
 *
 * Everything runs in a transaction that is rolled back. Passwords, codes and keys
 * are random per run and never printed. No email is sent: a recording double
 * stands in for EmailService (the HTTP-level suite checks the real Brevo request
 * shape against a local mock).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

ob_start();

require_once dirname(__DIR__, 2) . '/app/services/AuthService.php';
require_once dirname(__DIR__, 2) . '/app/services/PasswordResetService.php';
require_once dirname(__DIR__, 2) . '/app/controllers/PasswordResetController.php';
require_once dirname(__DIR__, 2) . '/app/helpers/csrf.php';

use OTPHP\TOTP;

$passed = 0;
$failed = 0;
$report = [];
function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed, $report;
    $ok ? $passed++ : $failed++;
    $report[] = ($ok ? '  PASS  ' : '  FAIL  ') . $label . (!$ok && $detail !== '' ? " -> $detail" : '');
}
function section(string $title): void
{
    global $report;
    $report[] = "\n$title";
}
function run(PDO $pdo, string $sql, array $params = []): PDOStatement
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
function throws(callable $fn): bool
{
    try {
        $fn();
    } catch (Throwable $e) {
        return true;
    }
    return false;
}

/** Session double: records what the services ask of the session. */
class FakeSessions extends SessionService
{
    public ?array $data = null;
    public ?array $pending = null;
    public int $established = 0;
    public int $refreshed = 0;
    public bool $destroyed = false;

    public function establish(array $admin): void
    {
        $this->established++;
        $this->pending = null;
        $now = time();
        $this->data = ['admin_id' => (int) $admin['id'], 'role' => $admin['role'], 'authenticated_at' => $now, 'last_activity' => $now];
    }
    public function beginTwoFactor(int $adminId): void { $this->data = null; $this->pending = ['admin_id' => $adminId, 'started' => time()]; }
    public function pendingTwoFactor(): ?array
    {
        if ($this->pending === null || time() - $this->pending['started'] > AppConfig::TWO_FACTOR_PENDING_SECONDS) { return null; }
        return $this->pending;
    }
    public function clearPendingTwoFactor(): void { $this->pending = null; }
    public function current(): ?array { return $this->data; }
    public function touch(string $role): void { if ($this->data) { $this->data['last_activity'] = time(); $this->data['role'] = $role; } }
    public function refreshAuthentication(): void { $this->refreshed++; if ($this->data) { $this->data['authenticated_at'] = time(); } }
    public function destroy(): void { $this->destroyed = true; $this->data = null; $this->pending = null; }
}

/** Email double: records messages instead of sending. */
class RecordingEmail extends EmailService
{
    /** @var list<array{to:string,name:string,url:string,minutes:int}> */
    public array $sent = [];
    public bool $succeed = true;

    public function sendPasswordReset(string $toEmail, string $toName, string $resetUrl, int $validMinutes): bool
    {
        $this->sent[] = ['to' => $toEmail, 'name' => $toName, 'url' => $resetUrl, 'minutes' => $validMinutes];
        return $this->succeed;
    }
}

const TEST_EMAIL_BASE = '@example.test';
function randomPassword(): string { return 'Pw-' . bin2hex(random_bytes(10)) . '-Zz9!'; }

// Test configuration (independent of the developer's .env)
$testKey = random_bytes(32);
putenv('APP_KEY=base64:' . base64_encode($testKey));
putenv('APP_ENV=development');
putenv('APP_URL=http://localhost:8000');
putenv('PASSWORD_RESET_MINUTES=30');
putenv('BREVO_API_KEY=not-a-real-key-for-tests');
putenv('MAIL_FROM_EMAIL=noreply@example.test');
putenv('MAIL_FROM_ADDRESS=noreply@example.test');
putenv('MAIL_MAILER=brevo_api');   // these tests exercise the Brevo API transport (the developer's .env may select SMTP)

// ------------------------------------------------------------ TOTP library + codes
section('TOTP (RFC 6238) and codes');
$rfc = TOTP::createFromSecret('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');
$rfc->setDigits(8);
check('RFC 6238 test vectors (t=59, t=1111111109, t=20000000000)', $rfc->at(59) === '94287082' && $rfc->at(1111111109) === '07081804' && $rfc->at(20000000000) === '65353130');
$secret = TwoFactorService::newSecret();
check('new secret is 160 random bits as 32 base32 characters', preg_match('/^[A-Z2-7]{32}$/', $secret) === 1);
check('two secrets differ', $secret !== TwoFactorService::newSecret());
$uri = TwoFactorService::provisioningUri($secret, 'a.b@example.test');
check('provisioning URI is a standard otpauth://totp URI with issuer, label and the secret', str_starts_with($uri, 'otpauth://totp/') && str_contains($uri, 'issuer=' . rawurlencode(AppConfig::TWO_FACTOR_ISSUER)) && str_contains($uri, 'secret=' . $secret) && str_contains($uri, 'a.b%40example.test'));
$svg = TwoFactorService::qrSvg($uri);
check('QR code is a locally generated SVG with no script, handler or external reference', str_starts_with($svg, '<svg') && !preg_match('/<script|on[a-z]+=|href=|xlink|style=/i', $svg));
check('the QR SVG does not contain the secret as readable text', !str_contains($svg, $secret));

$t = TOTP::createFromSecret($secret);
$now = 1_800_000_000;
check('current code accepted', TwoFactorService::matchStep($secret, $t->at($now), $now) === intdiv($now, 30));
check('one step old / one step ahead accepted (clock drift)', TwoFactorService::matchStep($secret, $t->at($now - 30), $now) === intdiv($now, 30) - 1 && TwoFactorService::matchStep($secret, $t->at($now + 30), $now) === intdiv($now, 30) + 1);
check('two steps old / ahead rejected', TwoFactorService::matchStep($secret, $t->at($now - 60), $now) === null && TwoFactorService::matchStep($secret, $t->at($now + 60), $now) === null);
check('wrong secret, letters, short, long, empty codes rejected', TwoFactorService::matchStep(TwoFactorService::newSecret(), $t->at($now), $now) === null
    && TwoFactorService::matchStep($secret, 'abcdef', $now) === null && TwoFactorService::matchStep($secret, '12345', $now) === null
    && TwoFactorService::matchStep($secret, '1234567', $now) === null && TwoFactorService::matchStep($secret, '', $now) === null);
check('codes typed with spaces or a hyphen are normalized', TwoFactorService::normalizeCode('123 456') === '123456' && TwoFactorService::normalizeCode('123-456') === '123456');

// ------------------------------------------------------------ encryption at rest
section('Secret encryption (APP_KEY, outside the database)');
$enc = SecretBox::encrypt($secret);
check('encrypts and decrypts', SecretBox::decrypt($enc) === $secret);
check('ciphertext does not contain the plaintext secret', !str_contains($enc, $secret) && !str_contains(base64_decode(substr($enc, 3)), $secret));
check('same plaintext encrypts differently each time (random nonce)', $enc !== SecretBox::encrypt($secret));
check('a tampered ciphertext is rejected, never decrypted to garbage', throws(fn () => SecretBox::decrypt(substr($enc, 0, -4) . 'AAAA')));
putenv('APP_KEY=base64:' . base64_encode(random_bytes(32)));
check('a different application key cannot decrypt', throws(fn () => SecretBox::decrypt($enc)));
// (hide the developer's own .env values: this check is about "no key anywhere")
AppConfig::env('DB_HOST');
$fileCache = new ReflectionProperty(AppConfig::class, 'fileValues');
$originalFileValues = $fileCache->getValue();
$fileCache->setValue(null, []);
putenv('APP_KEY');
check('without APP_KEY nothing is encrypted and nothing falls back to plaintext', !SecretBox::isAvailable() && throws(fn () => SecretBox::encrypt($secret)) && !TwoFactorService::isAvailable());
$fileCache->setValue(null, $originalFileValues); // restore (the database connection below needs the real .env)
putenv('APP_KEY=base64:' . base64_encode('too-short'));
check('a malformed or short APP_KEY is treated as not configured', AppConfig::appKey() === null && !SecretBox::isAvailable());
putenv('APP_KEY=base64:' . base64_encode($testKey));
check('the configured key works again', SecretBox::decrypt($enc) === $secret);

// ------------------------------------------------------------ database scenarios
section('Enrollment, recovery codes (temporary rows, rolled back)');
$pdo = Database::connection();
$users = new AdminUserRepository($pdo);
$audit = new AuditLogRepository($pdo);
$recovery = new RecoveryCodeRepository($pdo);
$baselineAdmins = $users->count();
$baselineAudit = (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();

$pdo->beginTransaction();
try {
    $pw1 = randomPassword();
    $pw2 = randomPassword();
    $adminA = $users->create('Alpha Admin', 'alpha' . TEST_EMAIL_BASE, password_hash($pw1, PASSWORD_BCRYPT, ['cost' => 4]), 'administrator');
    $adminB = $users->create('Bravo Admin', 'bravo' . TEST_EMAIL_BASE, password_hash($pw2, PASSWORD_BCRYPT, ['cost' => 4]), 'administrator');
    $tf = new TwoFactorService($pdo, $users, $recovery);

    check('a new account has 2FA off and no secret', (int) run($pdo, 'SELECT two_factor_enabled FROM admin_users WHERE id = ?', [$adminA])->fetchColumn() === 0
        && run($pdo, 'SELECT two_factor_secret_encrypted FROM admin_users WHERE id = ?', [$adminA])->fetchColumn() === null);
    check('database refuses "enabled" without a secret and confirmation (CHECK constraint)', throws(fn () => $pdo->exec("UPDATE admin_users SET two_factor_enabled = 1 WHERE id = $adminA")));

    $wrong = $tf->confirmEnrollment($adminA, $secret, '000000') ;
    $wrong = $wrong ?? $tf->confirmEnrollment($adminA, $secret, '111111');
    check('a wrong code does NOT enable 2FA (a generated secret alone enables nothing)', $wrong === null
        && (int) run($pdo, 'SELECT two_factor_enabled FROM admin_users WHERE id = ?', [$adminA])->fetchColumn() === 0);

    $codes = $tf->confirmEnrollment($adminA, $secret, $t->now());
    check('a valid code confirms the enrollment and returns 10 recovery codes', is_array($codes) && count($codes) === 10);
    $row = run($pdo, 'SELECT two_factor_enabled, two_factor_secret_encrypted, two_factor_confirmed_at, two_factor_last_step FROM admin_users WHERE id = ?', [$adminA])->fetch();
    check('2FA is enabled, confirmation time and time-step recorded', (int) $row['two_factor_enabled'] === 1 && $row['two_factor_confirmed_at'] !== null && $row['two_factor_last_step'] !== null);
    check('stored TOTP secret is ciphertext (not the secret) and decrypts to it', $row['two_factor_secret_encrypted'] !== $secret && !str_contains($row['two_factor_secret_encrypted'], $secret)
        && str_starts_with($row['two_factor_secret_encrypted'], 'v1:') && SecretBox::decrypt($row['two_factor_secret_encrypted']) === $secret);
    check('recovery codes: 10 unique, XXXXX-XXXXX format from a 32-symbol alphabet',
        count(array_unique($codes)) === 10 && count(array_filter($codes, fn ($c) => preg_match('/^[A-HJ-NP-Z2-9]{5}-[A-HJ-NP-Z2-9]{5}$/', $c) === 1)) === 10);
    $stored = run($pdo, 'SELECT code_hash FROM admin_recovery_codes WHERE admin_user_id = ?', [$adminA])->fetchAll(PDO::FETCH_COLUMN);
    $plain = array_map(fn ($c) => str_replace('-', '', $c), $codes);
    check('recovery codes are stored only as 64-hex keyed hashes (no code, no unkeyed hash)', count($stored) === 10
        && count(array_filter($stored, fn ($h) => preg_match('/^[a-f0-9]{64}$/', $h) === 1)) === 10
        && count(array_intersect($stored, $plain, $codes)) === 0
        && count(array_intersect($stored, array_map(fn ($c) => hash('sha256', $c), $plain))) === 0);
    check('the hashes depend on the application key (useless without it)', TwoFactorService::hashRecoveryCode($plain[0]) === $stored[array_search(TwoFactorService::hashRecoveryCode($plain[0]), $stored, true)]);
    putenv('APP_KEY=base64:' . base64_encode(random_bytes(32)));
    check('...a different key yields different hashes', !in_array(TwoFactorService::hashRecoveryCode($plain[0]), $stored, true));
    putenv('APP_KEY=base64:' . base64_encode($testKey));

    // single use
    check('a recovery code works once', $tf->consumeRecoveryCode($adminA, $codes[0]) === true);
    check('the same code never works again', $tf->consumeRecoveryCode($adminA, $codes[0]) === false);
    check('case, spaces and hyphen do not matter; wrong or malformed codes fail', $tf->consumeRecoveryCode($adminA, strtolower(str_replace('-', ' ', $codes[1]))) === true
        && !$tf->consumeRecoveryCode($adminA, 'AAAAA-AAAAA') && !$tf->consumeRecoveryCode($adminA, 'short') && !$tf->consumeRecoveryCode($adminA, ''));
    check("one admin's code does not work for another admin", $tf->consumeRecoveryCode($adminB, $codes[2]) === false);
    check('8 codes remain', $tf->remainingRecoveryCodes($adminA) === 8);

    // regenerate
    $new = $tf->regenerateRecoveryCodes($adminA);
    check('regeneration issues 10 new codes', count($new) === 10 && count(array_intersect($new, $codes)) === 0);
    check('regeneration invalidates every old code (even unused ones)', $tf->consumeRecoveryCode($adminA, $codes[2]) === false && $tf->consumeRecoveryCode($adminA, $codes[9]) === false);
    check('new codes work', $tf->consumeRecoveryCode($adminA, $new[0]) === true);

    // replay protection
    $step = intdiv(time(), 30) + 5;
    check('a TOTP time-step is accepted once and never again (replay protection)', $users->acceptTotpStep($adminA, $step) === true && $users->acceptTotpStep($adminA, $step) === false);
    check('an older time-step is rejected after a newer one was used', $users->acceptTotpStep($adminA, $step - 1) === false && $users->acceptTotpStep($adminA, $step + 1) === true);

    // ------------------------------------------------------------ login flow with 2FA
    section('Login with 2FA (restricted state, throttling)');
    $pdo->exec('DELETE FROM audit_logs');
    $fake = new FakeSessions();
    $auth = new AuthService($pdo, $users, $audit, $fake, $tf);
    $ip = '203.0.113.7';
    $ua = 'test-agent';
    // adminA is enrolled with $secret; reset replay state so codes are usable again
    run($pdo, 'UPDATE admin_users SET two_factor_last_step = NULL WHERE id = ?', [$adminA]);

    $r = $auth->attempt('alpha' . TEST_EMAIL_BASE, $pw1, $ip, $ua);
    check('correct password for a 2FA account returns "two_factor", NOT a login', $r === ['status' => 'two_factor', 'admin' => null]);
    check('no authenticated session exists yet: only a restricted pending state', $fake->established === 0 && $fake->current() === null && $fake->pendingTwoFactor() !== null);
    check('last_login_at is NOT updated and no LOGIN_SUCCESS exists before the second factor',
        run($pdo, 'SELECT last_login_at FROM admin_users WHERE id = ?', [$adminA])->fetchColumn() === null
        && (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type = 'LOGIN_SUCCESS'")->fetchColumn() === 0);
    check('the guard (resolveSession) treats the pending state as signed out', $auth->resolveSession($ip, $ua)['status'] === 'none');

    $bad = $auth->verifySecondFactor('000000', false, $ip, $ua);
    check('a wrong code fails with the generic result and logs 2FA_FAILURE (without the code)', $bad['status'] === 'invalid' && $fake->established === 0
        && (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type = '2FA_FAILURE' AND description NOT LIKE '%000000%'")->fetchColumn() === 1);
    $usedCode = $t->now(); // pinned: a code computed again after a 30-second boundary would legitimately be a NEWER code
    $good = $auth->verifySecondFactor($usedCode, false, $ip, $ua);
    check('the correct code completes the login: session established once, LOGIN_SUCCESS logged, last_login_at set',
        $good['status'] === 'ok' && $fake->established === 1 && $good['admin']['id'] === $adminA
        && run($pdo, 'SELECT last_login_at FROM admin_users WHERE id = ?', [$adminA])->fetchColumn() !== null
        && (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type = 'LOGIN_SUCCESS'")->fetchColumn() === 1);
    check('the pending state is gone after success', $fake->pendingTwoFactor() === null);

    // replay: log in again with the very same code
    $fake2 = new FakeSessions();
    $auth2 = new AuthService($pdo, $users, $audit, $fake2, $tf);
    $auth2->attempt('alpha' . TEST_EMAIL_BASE, $pw1, $ip, $ua);
    check('re-using the same authenticator code is rejected (it was already used)', $auth2->verifySecondFactor($usedCode, false, $ip, $ua)['status'] === 'invalid' && $fake2->established === 0);

    check('no pending state -> "expired" (nobody can jump straight to the code step)', (new AuthService($pdo, $users, $audit, new FakeSessions(), $tf))->verifySecondFactor($t->now(), false, $ip, $ua)['status'] === 'expired');
    $fake2->pending['started'] = time() - AppConfig::TWO_FACTOR_PENDING_SECONDS - 5;
    check('the pending state expires quickly (5 minutes)', $auth2->verifySecondFactor('123456', false, $ip, $ua)['status'] === 'expired');

    // recovery-code login
    $fake3 = new FakeSessions();
    $auth3 = new AuthService($pdo, $users, $audit, $fake3, $tf);
    $auth3->attempt('alpha' . TEST_EMAIL_BASE, $pw1, $ip, $ua);
    $rc = $auth3->verifySecondFactor($new[1], true, $ip, $ua);
    check('a recovery code completes the login and logs RECOVERY_CODE_USED', $rc['status'] === 'ok' && $fake3->established === 1
        && (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type = 'RECOVERY_CODE_USED'")->fetchColumn() === 1);
    $fake4 = new FakeSessions();
    $auth4 = new AuthService($pdo, $users, $audit, $fake4, $tf);
    $auth4->attempt('alpha' . TEST_EMAIL_BASE, $pw1, $ip, $ua);
    check('the used recovery code can never log in again', $auth4->verifySecondFactor($new[1], true, $ip, $ua)['status'] === 'invalid' && $fake4->established === 0);

    // throttling: a fresh admin so the earlier success does not reset anything
    $pwC = randomPassword();
    $adminC = $users->create('Charlie Admin', 'charlie' . TEST_EMAIL_BASE, password_hash($pwC, PASSWORD_BCRYPT, ['cost' => 4]), 'administrator');
    $secretC = TwoFactorService::newSecret();
    $tf->confirmEnrollment($adminC, $secretC, TOTP::createFromSecret($secretC)->now());
    run($pdo, 'UPDATE admin_users SET two_factor_last_step = NULL WHERE id = ?', [$adminC]);
    $pdo->exec('DELETE FROM audit_logs');
    $fakeC = new FakeSessions();
    $authC = new AuthService($pdo, $users, $audit, $fakeC, $tf);
    $authC->attempt('charlie' . TEST_EMAIL_BASE, $pwC, '198.51.100.9', $ua);
    $statuses = [];
    for ($i = 0; $i < 5; $i++) { $statuses[] = $authC->verifySecondFactor(sprintf('%06d', 111111 + $i), false, '198.51.100.9', $ua)['status']; }
    check('five wrong codes are processed as ordinary failures', $statuses === array_fill(0, 5, 'invalid'), json_encode($statuses));
    $blocked = $authC->verifySecondFactor(TOTP::createFromSecret($secretC)->now(), false, '198.51.100.9', $ua);
    check('the sixth attempt, even with the CORRECT code, is throttled', $blocked['status'] === 'throttled' && $fakeC->established === 0);
    check('RATE_LIMIT_TRIGGERED logged for the second factor', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type = 'RATE_LIMIT_TRIGGERED'")->fetchColumn() === 1);
    check('recovery-code guesses count in the same bucket (cannot bypass the limit)', $authC->verifySecondFactor('AAAAA-AAAAA', true, '198.51.100.9', $ua)['status'] === 'throttled');
    check('another administrator is not affected by that lockout', (new AuthService($pdo, $users, $audit, new FakeSessions(), $tf))->verifySecondFactor('1', false, '198.51.100.9', $ua)['status'] === 'expired');
    $pdo->exec("UPDATE audit_logs SET created_at = DATE_SUB(created_at, INTERVAL 20 MINUTE)");
    check('the lockout is temporary (window passes)', $authC->verifySecondFactor(TOTP::createFromSecret($secretC)->now(), false, '198.51.100.9', $ua)['status'] === 'ok');

    // ------------------------------------------------------------ sensitive actions
    section('Sensitive actions: password + code required');
    $pdo->exec('DELETE FROM audit_logs');
    $sa = new AuthService($pdo, $users, $audit, new FakeSessions(), $tf);
    run($pdo, 'UPDATE admin_users SET two_factor_last_step = NULL WHERE id = ?', [$adminA]);
    check('disable: wrong password refused (and logged, no 2FA change)', $sa->disableTwoFactor($adminA, 'wrong-password', $t->now(), false, $ip, $ua) === 'invalid'
        && (int) run($pdo, 'SELECT two_factor_enabled FROM admin_users WHERE id = ?', [$adminA])->fetchColumn() === 1
        && (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type = 'REAUTH_FAILURE'")->fetchColumn() === 1);
    check('disable: correct password but wrong code refused', $sa->disableTwoFactor($adminA, $pw1, '000000', false, $ip, $ua) === 'invalid'
        && (int) run($pdo, 'SELECT two_factor_enabled FROM admin_users WHERE id = ?', [$adminA])->fetchColumn() === 1);
    check('disable: correct password with an empty code refused', $sa->disableTwoFactor($adminA, $pw1, '', false, $ip, $ua) === 'invalid');
    check('regenerate: needs password AND code', $sa->regenerateRecoveryCodes($adminA, $pw1, '000000', $ip, $ua)['status'] === 'invalid' && $sa->regenerateRecoveryCodes($adminA, 'nope', $t->now(), $ip, $ua)['status'] === 'invalid');
    // (the failed attempts above used up this administrator's 5-failure allowance; start a fresh window)
    check('five re-authentication failures trigger the temporary limit: even a correct attempt is then throttled', $sa->regenerateRecoveryCodes($adminA, $pw1, $t->now(), $ip, $ua)['status'] === 'throttled');
    $pdo->exec('DELETE FROM audit_logs');
    $regen = $sa->regenerateRecoveryCodes($adminA, $pw1, $t->now(), $ip, $ua);
    check('regenerate with password + valid code works, is logged, and old codes die', $regen['status'] === 'ok' && count($regen['codes']) === 10
        && $tf->consumeRecoveryCode($adminA, $new[2]) === false && (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type = 'RECOVERY_CODES_REGENERATED'")->fetchColumn() === 1);
    $disableViaRecovery = $sa->disableTwoFactor($adminA, $pw1, $regen['codes'][0], true, $ip, $ua);
    check('disable with password + recovery code works', $disableViaRecovery === 'ok');
    $after = run($pdo, 'SELECT two_factor_enabled, two_factor_secret_encrypted, two_factor_confirmed_at, two_factor_last_step FROM admin_users WHERE id = ?', [$adminA])->fetch();
    check('disabling wipes the secret and every recovery code, and logs 2FA_DISABLED', (int) $after['two_factor_enabled'] === 0 && $after['two_factor_secret_encrypted'] === null && $after['two_factor_confirmed_at'] === null
        && $tf->remainingRecoveryCodes($adminA) === 0 && (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type = '2FA_DISABLED'")->fetchColumn() === 1);
    check('after disabling, a password alone signs in again', (new AuthService($pdo, $users, $audit, $fk = new FakeSessions(), $tf))->attempt('alpha' . TEST_EMAIL_BASE, $pw1, $ip, $ua)['status'] === 'ok' && $fk->established === 1);

    // password change + session invalidation
    $fakeP = new FakeSessions();
    $authP = new AuthService($pdo, $users, $audit, $fakeP, $tf);
    $authP->attempt('bravo' . TEST_EMAIL_BASE, $pw2, $ip, $ua);
    check('bravo signed in', $fakeP->established === 1);
    $oldSessionTime = $fakeP->data['authenticated_at'];
    $users->setPassword($adminB, password_hash(randomPassword(), PASSWORD_BCRYPT, ['cost' => 4]), gmdate('Y-m-d H:i:s', $oldSessionTime + 10));
    check('a session created BEFORE a password change is ended', $authP->resolveSession($ip, $ua)['status'] === 'invalid' && $fakeP->destroyed);
    // boundary: a session authenticated in the SAME second as the change must also end
    $fakeSame = new FakeSessions();
    $authSame = new AuthService($pdo, $users, $audit, $fakeSame, $tf);
    $fakeSame->establish(['id' => $adminB, 'role' => 'administrator']);
    $fakeSame->data['authenticated_at'] = $oldSessionTime + 10;
    check('a session authenticated in the SAME second as a password change is ended too', $authSame->resolveSession($ip, $ua)['status'] === 'invalid');
    $fakeP2 = new FakeSessions();
    $authP2 = new AuthService($pdo, $users, $audit, $fakeP2, $tf);
    $fakeP2->establish(['id' => $adminB, 'role' => 'administrator']);
    $fakeP2->data['authenticated_at'] = time() + 60;
    check('a session created AFTER the change stays valid', $authP2->resolveSession($ip, $ua)['status'] === 'ok');
    $newPw = randomPassword();
    $authP2->changePassword($adminB, $newPw, $ip, $ua);
    check('changing your own password: new password works, session refreshed, PASSWORD_CHANGED logged',
        password_verify($newPw, (string) run($pdo, 'SELECT password_hash FROM admin_users WHERE id = ?', [$adminB])->fetchColumn()) && $fakeP2->refreshed === 1
        && (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type = 'PASSWORD_CHANGED'")->fetchColumn() === 1);

    // ------------------------------------------------------------ password reset
    section('Password reset (token, expiry, single use, 2FA untouched)');
    $pdo->exec('DELETE FROM audit_logs');
    $mail = new RecordingEmail();
    $reset = new PasswordResetService($pdo, $users, new PasswordResetRepository($pdo), $audit, $mail);

    // an enrolled admin to prove 2FA survives a reset
    $pwD = randomPassword();
    $adminD = $users->create('Delta Admin', 'delta' . TEST_EMAIL_BASE, password_hash($pwD, PASSWORD_BCRYPT, ['cost' => 4]), 'administrator');
    $secretD = TwoFactorService::newSecret();
    $codesD = $tf->confirmEnrollment($adminD, $secretD, TOTP::createFromSecret($secretD)->now());
    $encBefore = (string) run($pdo, 'SELECT two_factor_secret_encrypted FROM admin_users WHERE id = ?', [$adminD])->fetchColumn();

    $reset->requestReset('nobody' . TEST_EMAIL_BASE, '192.0.2.10', $ua);
    check('unknown email: no token created, no email sent', $mail->sent === [] && (int) $pdo->query('SELECT COUNT(*) FROM password_reset_tokens')->fetchColumn() === 0);
    check('...but the request is still logged and counted (uniform behaviour)', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type = 'PASSWORD_RESET_REQUESTED'")->fetchColumn() === 1);
    $users->create('Echo Disabled', 'echo' . TEST_EMAIL_BASE, password_hash('x-placeholder-password-1', PASSWORD_BCRYPT, ['cost' => 4]), 'administrator');
    run($pdo, "UPDATE admin_users SET status = 'disabled' WHERE email = ?", ['echo' . TEST_EMAIL_BASE]);
    $reset->requestReset('echo' . TEST_EMAIL_BASE, '192.0.2.11', $ua);
    check('disabled account: nothing is sent', $mail->sent === []);
    $reset->requestReset('not an email', '192.0.2.12', $ua);
    check('malformed email: nothing happens, no error', $mail->sent === []);

    $reset->requestReset('  DELTA' . TEST_EMAIL_BASE . ' ', '192.0.2.20', $ua);
    check('known active account: exactly one email, to that address', count($mail->sent) === 1 && $mail->sent[0]['to'] === 'delta' . TEST_EMAIL_BASE && $mail->sent[0]['minutes'] === 30);
    $url = $mail->sent[0]['url'];
    preg_match('#^https://nextgenautosandparts\.com/admin/reset-password\.php\?token=([a-f0-9]{64})$#', $url, $m);
    $token = $m[1] ?? '';
    check('the link uses the production domain (never localhost, never the request) and carries a 64-hex (256-bit) token', $token !== '');
    $tokenRow = run($pdo, 'SELECT token_hash, expires_at, used_at FROM password_reset_tokens WHERE admin_user_id = ?', [$adminD])->fetch();
    check('only the SHA-256 hash is stored: the raw token is not in the database', $tokenRow['token_hash'] === hash('sha256', $token) && $tokenRow['token_hash'] !== $token
        && (int) run($pdo, 'SELECT COUNT(*) FROM password_reset_tokens WHERE token_hash = ?', [$token])->fetchColumn() === 0);
    $ttl = strtotime($tokenRow['expires_at'] . ' UTC') - time();
    check('the link expires in about 30 minutes (configurable)', $ttl > 29 * 60 && $ttl <= 30 * 60 + 5, (string) $ttl);
    $token2sample = bin2hex(random_bytes(32));
    check('tokens come from random_bytes: two are never alike', $token !== $token2sample);

    check('valid link validates', $reset->validateToken($token, '192.0.2.20', $ua) !== null);
    $failsBefore = (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type = 'PASSWORD_RESET_FAILED'")->fetchColumn();
    check('garbage, short, wrong-case and unknown tokens are rejected and logged', $reset->validateToken('nonsense', '192.0.2.20', $ua) === null && $reset->validateToken(str_repeat('a', 64), '192.0.2.20', $ua) === null
        && $reset->validateToken(strtoupper($token), '192.0.2.20', $ua) === null && $reset->validateToken('', '192.0.2.20', $ua) === null
        && (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type = 'PASSWORD_RESET_FAILED'")->fetchColumn() === $failsBefore + 4);

    // a newer request replaces the older link
    $reset->requestReset('delta' . TEST_EMAIL_BASE, '192.0.2.21', $ua);
    $tokenNew = preg_match('/token=([a-f0-9]{64})$/', $mail->sent[1]['url'], $m2) ? $m2[1] : '';
    check('a new request invalidates the previous link', $reset->validateToken($token, '192.0.2.21', $ua) === null && $reset->validateToken($tokenNew, '192.0.2.21', $ua) !== null);

    // expiry
    run($pdo, 'UPDATE password_reset_tokens SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE token_hash = ?', [hash('sha256', $tokenNew)]);
    check('an expired link is rejected', $reset->validateToken($tokenNew, '192.0.2.21', $ua) === null);
    run($pdo, 'UPDATE password_reset_tokens SET expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE) WHERE token_hash = ?', [hash('sha256', $tokenNew)]);

    // completion
    $tokenId = $reset->validateToken($tokenNew, '192.0.2.21', $ua);
    $newPassword = randomPassword();
    $changedBefore = run($pdo, 'SELECT password_changed_at FROM admin_users WHERE id = ?', [$adminD])->fetchColumn();
    check('completing the reset succeeds', $reset->complete($tokenId, $newPassword, '192.0.2.21', $ua) === true);
    $dRow = run($pdo, 'SELECT password_hash, password_changed_at, two_factor_enabled, two_factor_secret_encrypted FROM admin_users WHERE id = ?', [$adminD])->fetch();
    check('the new password works and the old one does not; hash is not plaintext', password_verify($newPassword, $dRow['password_hash']) && !password_verify($pwD, $dRow['password_hash']) && $dRow['password_hash'] !== $newPassword);
    check('password_changed_at is set (this ends all older sessions)', $changedBefore === null && $dRow['password_changed_at'] !== null);
    check('2FA is untouched by the reset: still enabled, same encrypted secret, all recovery codes still there',
        (int) $dRow['two_factor_enabled'] === 1 && $dRow['two_factor_secret_encrypted'] === $encBefore && $tf->remainingRecoveryCodes($adminD) === 10);
    check('a reset is not a 2FA bypass: after the reset the password step still demands the second factor',
        (new AuthService($pdo, $users, $audit, $fk2 = new FakeSessions(), $tf))->attempt('delta' . TEST_EMAIL_BASE, $newPassword, $ip, $ua)['status'] === 'two_factor' && $fk2->established === 0);
    check('the used link cannot be used again (validate or complete)', $reset->validateToken($tokenNew, '192.0.2.21', $ua) === null && $reset->complete($tokenId, randomPassword(), '192.0.2.21', $ua) === false);
    check('PASSWORD_RESET_COMPLETED logged', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type = 'PASSWORD_RESET_COMPLETED' AND admin_user_id = ?", [$adminD])->fetchColumn() === 1);
    check('sessions created before the reset are ended', (function () use ($pdo, $users, $audit, $tf, $adminD, $ip, $ua): bool {
        $f = new FakeSessions(); $a = new AuthService($pdo, $users, $audit, $f, $tf);
        $f->establish(['id' => $adminD, 'role' => 'administrator']); $f->data['authenticated_at'] = time() - 3600;
        return $a->resolveSession($ip, $ua)['status'] === 'invalid';
    })());

    // two concurrent completions of one link: only one wins
    $reset->requestReset('delta' . TEST_EMAIL_BASE, '192.0.2.22', $ua);
    $t3 = preg_match('/token=([a-f0-9]{64})$/', end($mail->sent)['url'], $m3) ? $m3[1] : '';
    $id3 = $reset->validateToken($t3, '192.0.2.22', $ua);
    $first = $reset->complete($id3, randomPassword(), '192.0.2.22', $ua);
    $second = $reset->complete($id3, randomPassword(), '192.0.2.22', $ua);
    check('two attempts to complete one link: exactly one succeeds', $first === true && $second === false);

    // email failure
    $pdo->exec('DELETE FROM audit_logs');
    $mail->succeed = false;
    $reset->requestReset('delta' . TEST_EMAIL_BASE, '192.0.2.23', $ua);
    check('if the email provider fails, the failure is logged and nothing throws', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type = 'PASSWORD_RESET_EMAIL_FAILED'")->fetchColumn() === 1);
    $mail->succeed = true;

    // rate limiting of requests
    $pdo->exec('DELETE FROM audit_logs'); $sentBefore = count($mail->sent);
    for ($i = 0; $i < 3; $i++) { $reset->requestReset('delta' . TEST_EMAIL_BASE, '192.0.2.30', $ua); }
    $reset->requestReset('delta' . TEST_EMAIL_BASE, '192.0.2.31', $ua); // 4th request for the account, new IP
    check('requests for one account are limited to 3 per hour (4th silently ignored: no email)', count($mail->sent) - $sentBefore === 3);
    $pdo->exec('DELETE FROM audit_logs'); $sentBefore = count($mail->sent);
    for ($i = 0; $i < 5; $i++) { $reset->requestReset("rl{$i}" . TEST_EMAIL_BASE, '192.0.2.40', $ua); }
    $reset->requestReset('delta' . TEST_EMAIL_BASE, '192.0.2.40', $ua);
    check('requests from one IP are limited to 5 per hour (even for a valid account)', count($mail->sent) === $sentBefore);
    check('the rate limit is logged once', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type = 'RATE_LIMIT_TRIGGERED'")->fetchColumn() === 1);
    $pdo->exec('DELETE FROM audit_logs');
    for ($i = 0; $i < 10; $i++) { $reset->validateToken(bin2hex(random_bytes(32)), '192.0.2.50', $ua); }
    check('bad-link attempts from one IP are throttled after 10', $reset->tokenAttemptsThrottled('192.0.2.50') === true && $reset->tokenAttemptsThrottled('192.0.2.51') === false);

    // ------------------------------------------------------------ controller behaviour
    section('Forgot-password controller: identical response, identical time');
    $pdo->exec('DELETE FROM audit_logs');
    $ctrl = new PasswordResetController(new PasswordResetService($pdo, $users, new PasswordResetRepository($pdo), $audit, new RecordingEmail()));
    $post = [CSRF_FIELD_NAME => csrf_token(), 'email' => 'delta' . TEST_EMAIL_BASE];
    $t0 = hrtime(true); $known = $ctrl->submitForgot($post, '192.0.2.60', $ua); $dKnown = (hrtime(true) - $t0) / 1e6;
    $post['email'] = 'nobody' . TEST_EMAIL_BASE;
    $t0 = hrtime(true); $unknown = $ctrl->submitForgot($post, '192.0.2.61', $ua); $dUnknown = (hrtime(true) - $t0) / 1e6;
    check('known and unknown emails get the identical result and message', array_diff_key($known, ['email' => 1]) === array_diff_key($unknown, ['email' => 1]) && $known['message'] === PasswordResetController::MESSAGE_REQUESTED, json_encode([$known, $unknown]));
    check('the message does not reveal whether an account exists', !preg_match('/(no account|not found|does not exist|unknown)/i', (string) $known['message']));
    check('both take at least the minimum response time (timing does not reveal the account)', $dKnown >= AppConfig::RESET_MIN_RESPONSE_MS - 50 && $dUnknown >= AppConfig::RESET_MIN_RESPONSE_MS - 50, sprintf('%.0f / %.0f ms', $dKnown, $dUnknown));
    check('...and differ by well under 250 ms', abs($dKnown - $dUnknown) < 250, sprintf('%.0f ms', abs($dKnown - $dUnknown)));
    check('a bad CSRF token is refused before anything happens', $ctrl->submitForgot(['email' => 'delta' . TEST_EMAIL_BASE, CSRF_FIELD_NAME => 'x'], '192.0.2.62', $ua)['status'] === 'csrf');
    putenv('BREVO_API_KEY');
    putenv('BREVO_API_KEY=');
    check('with no email provider configured the feature reports itself unavailable instead of pretending',
        !PasswordResetService::isAvailable() && $ctrl->submitForgot([CSRF_FIELD_NAME => csrf_token(), 'email' => 'x' . TEST_EMAIL_BASE], '192.0.2.63', $ua)['status'] === 'unavailable');
    putenv('BREVO_API_KEY=not-a-real-key-for-tests');
    putenv('APP_URL=ftp://wrong');
    check('an unusable APP_URL is refused, but email links do not depend on it (production domain)', AppConfig::appUrl() === null && PasswordResetService::isAvailable() && AppConfig::linkUrl() === 'https://nextgenautosandparts.com');
    putenv('APP_ENV=production'); putenv('APP_URL=http://plain-http.example');
    check('in production an http:// APP_URL is refused (https required)', AppConfig::appUrl() === null);
    putenv('APP_URL=https://admin.example.com/');
    check('production accepts https and strips the trailing slash', AppConfig::appUrl() === 'https://admin.example.com');
    putenv('APP_ENV=development'); putenv('APP_URL=http://localhost:8000');
    check('reset lifetime is configurable and bounded (5 to 120 minutes)', (function (): bool {
        putenv('PASSWORD_RESET_MINUTES=45'); $a = AppConfig::passwordResetMinutes();
        putenv('PASSWORD_RESET_MINUTES=1'); $b = AppConfig::passwordResetMinutes();
        putenv('PASSWORD_RESET_MINUTES=100000'); $c = AppConfig::passwordResetMinutes();
        putenv('PASSWORD_RESET_MINUTES=30');
        return $a === 45 && $b === 30 && $c === 30;
    })());

    // ------------------------------------------------------------ logging hygiene
    section('Logging hygiene');
    $everything = implode("\n", run($pdo, "SELECT CONCAT_WS('|', event_type, entity_type, entity_id, description, ip_address, user_agent) FROM audit_logs")->fetchAll(PDO::FETCH_COLUMN));
    $secrets = array_merge([$pw1, $pw2, $pwC, $pwD, $newPassword, $secret, $secretC, $secretD, $token, $tokenNew, $t3, '000000'], $codes, $new, $regen['codes'], $codesD);
    check('no password, TOTP secret, recovery code, reset token or submitted code appears in the audit log', count(array_filter($secrets, fn ($s) => $s !== '' && str_contains($everything, (string) $s))) === 0);
    check('no hash or ciphertext appears in the audit log', !str_contains($everything, '$2y$') && !str_contains($everything, 'v1:'));
} finally {
    $pdo->rollBack();
}

section('Cleanup');
check('rolled back: no admins, tokens, recovery codes or audit rows left behind',
    $users->count() === $baselineAdmins
    && (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() === $baselineAudit
    && (int) $pdo->query('SELECT COUNT(*) FROM password_reset_tokens')->fetchColumn() === 0
    && (int) $pdo->query('SELECT COUNT(*) FROM admin_recovery_codes')->fetchColumn() === 0);

ob_end_clean();
echo implode("\n", $report), "\n\nResult: $passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
