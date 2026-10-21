<?php

declare(strict_types=1);

/**
 * Logic tests for customer accounts and Google sign-in (spec 13): CustomerValidator, CustomerAuthService
 * (register, sign in, throttling, Google link/create/refuse) and GoogleOAuthService::claimsFrom. CLI only:
 *   php database/tests/customer_auth_test.php
 * All database rows are created inside one transaction that is rolled back. Google is stubbed: no network call is made.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// A fake, clearly non-production Google configuration for the token checks (never a real credential).
putenv('GOOGLE_CLIENT_ID=test-client.apps.googleusercontent.com');
putenv('GOOGLE_CLIENT_SECRET=test-secret');
putenv('APP_URL=http://localhost:8000');

require_once dirname(__DIR__, 2) . '/app/services/CustomerAuthService.php';
require_once dirname(__DIR__, 2) . '/app/validation/CustomerValidator.php';

$passed = 0;
$failed = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$passed, &$failed): void {
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . ($ok || $detail === '' ? '' : " ($detail)") . "\n";
};

/** In-memory session: no cookies, no files. */
final class FakeSession extends CustomerSessionService
{
    public ?int $customerId = null;
    public ?array $google = null;

    public function establish(int $customerId): void { $this->customerId = $customerId; }
    public function destroy(): void { $this->customerId = null; }
    public function currentCustomerId(): ?int { return $this->customerId; }
    public function beginGoogle(string $state, string $nonce): void { $this->google = ['state' => $state, 'nonce' => $nonce]; }
    public function consumeGoogle(string $state): ?string
    {
        $g = $this->google;
        $this->google = null;

        return $g !== null && hash_equals($g['state'], $state) ? $g['nonce'] : null;
    }
}

/** Google stub: returns whatever identity the test sets. */
final class FakeGoogle extends GoogleOAuthService
{
    public ?array $identity = null;
    public function identityFromCode(string $code, string $expectedNonce): ?array { return $this->identity; }
}

$pdo = Database::connection();
$pdo->beginTransaction();
$session = new FakeSession();
$google = new FakeGoogle();
$auth = new CustomerAuthService(new CustomerRepository($pdo), new AuditLogRepository($pdo), $session, $google);
$count = static fn (string $sql): int => (int) $pdo->query($sql)->fetchColumn();
$ip = '203.0.113.7';
$pw = 'correct horse battery';

echo "Validator\n";
$ok = CustomerValidator::registration(['name' => '  Ama   Mensah ', 'email' => ' Ama@Example.COM ', 'password' => $pw, 'password_confirm' => $pw]);
$check('valid input passes and is cleaned', $ok['errors'] === [] && $ok['values']['name'] === 'Ama Mensah' && $ok['values']['email'] === 'ama@example.com');
$bad = CustomerValidator::registration(['name' => '<b>x</b>', 'email' => 'nope', 'password' => 'short', 'password_confirm' => 'short']);
$check('markup in the name, bad email and short password are rejected', isset($bad['errors']['name'], $bad['errors']['email'], $bad['errors']['password']));
$mismatch = CustomerValidator::registration(['name' => 'A', 'email' => 'a@example.com', 'password' => $pw, 'password_confirm' => 'different horse battery']);
$check('mismatched confirmation is rejected', isset($mismatch['errors']['password_confirm']));
$check('array values (name[]=x) are treated as empty', isset(CustomerValidator::registration(['name' => ['x'], 'email' => ['y'], 'password' => ['z']])['errors']['name']));

echo "Registration and sign-in\n";
$r = $auth->register('Test One', 'one@customer-test.invalid', $pw, $ip, 'test');
$check('register succeeds and signs the customer in', $r['status'] === 'ok' && $session->customerId !== null);
$row = $pdo->query("SELECT * FROM customers WHERE email = 'one@customer-test.invalid'")->fetch();
$check('password is stored hashed, never plaintext', is_string($row['password_hash']) && str_starts_with($row['password_hash'], '$2y$') && !str_contains($row['password_hash'], 'horse'));
$session->destroy();
$check('duplicate email is refused', $auth->register('Dup', 'ONE@customer-test.invalid', $pw, $ip, 'test')['status'] === 'exists');
$check('correct password signs in (email case-insensitive)', $auth->attempt('One@Customer-Test.invalid', $pw, $ip, 'test')['status'] === 'ok' && $session->customerId === (int) $row['id']);
$session->destroy();
$check('wrong password is refused, no session', $auth->attempt('one@customer-test.invalid', 'wrong password!!', $ip, 'test')['status'] === 'invalid' && $session->customerId === null);
$check('unknown email looks identical', $auth->attempt('nobody@customer-test.invalid', 'whatever whatever', $ip, 'test')['status'] === 'invalid');
$pdo->prepare("UPDATE customers SET status = 'disabled' WHERE id = ?")->execute([$row['id']]);
$check('disabled account cannot sign in', $auth->attempt('one@customer-test.invalid', $pw, $ip, 'test')['status'] === 'invalid');
$pdo->prepare("UPDATE customers SET status = 'active' WHERE id = ?")->execute([$row['id']]);
$check('audit log never holds the raw email of a failure', $count("SELECT COUNT(*) FROM audit_logs WHERE description LIKE '%customer-test.invalid%'") === 0);

echo "Throttling\n";
$ip2 = '203.0.113.99';
for ($i = 0; $i < CustomerAuthService::MAX_LOGIN_FAILURES_PER_IP; $i++) {
    $auth->attempt("spray$i@customer-test.invalid", 'wrong password!!', $ip2, 'test');
}
$check('10 failures from one IP throttle that IP, even with the right password', $auth->attempt('one@customer-test.invalid', $pw, $ip2, 'test')['status'] === 'throttled');
$ip3 = '203.0.113.150';
for ($i = 0; $i < CustomerAuthService::MAX_REGISTRATIONS_PER_IP; $i++) {
    $auth->register('Bot', "bot$i@customer-test.invalid", $pw, $ip3, 'test');
}
$check('registrations are limited per IP', $auth->register('Bot', 'bot-last@customer-test.invalid', $pw, $ip3, 'test')['status'] === 'throttled');

echo "Google sign-in\n";
$check('Google is configured in this test', AppConfig::googleConfigured());
$session->destroy();   // the bot registrations above signed the fake session in
$session->beginGoogle('state-1', 'nonce-1');
$google->identity = ['sub' => '111', 'email' => 'new@customer-test.invalid', 'name' => 'Kofi Boateng'];
$check('a wrong state is refused', !$auth->googleFinish('wrong', 'code', $ip, 'test') && $session->customerId === null);
$session->beginGoogle('state-2', 'nonce-2');
$check('first Google sign-in creates an account without a password', $auth->googleFinish('state-2', 'code', $ip, 'test') && $session->customerId !== null
    && $count("SELECT COUNT(*) FROM customers WHERE google_id = '111' AND password_hash IS NULL") === 1);
$session->destroy();
$session->beginGoogle('state-3', 'nonce-3');
$check('same Google account signs in again (no duplicate)', $auth->googleFinish('state-3', 'code', $ip, 'test') && $count("SELECT COUNT(*) FROM customers WHERE google_id = '111'") === 1);
$session->destroy();
$check('a Google-only account cannot be entered with a password', $auth->attempt('new@customer-test.invalid', 'anything anything', $ip, 'test')['status'] === 'invalid');
$session->beginGoogle('state-4', 'nonce-4');
$google->identity = ['sub' => '222', 'email' => 'one@customer-test.invalid', 'name' => 'Test One'];
$check('Google with the email of a password account links to it', $auth->googleFinish('state-4', 'code', $ip, 'test')
    && $count("SELECT COUNT(*) FROM customers WHERE email = 'one@customer-test.invalid' AND google_id = '222'") === 1);
$session->destroy();
$session->beginGoogle('state-5', 'nonce-5');
$google->identity = ['sub' => '333', 'email' => 'one@customer-test.invalid', 'name' => 'Attacker'];
$check('a different Google account can never take over a linked email', !$auth->googleFinish('state-5', 'code', $ip, 'test') && $session->customerId === null);
$session->beginGoogle('state-6', 'nonce-6');
$google->identity = null;
$check('a failed token exchange signs nobody in', !$auth->googleFinish('state-6', 'code', $ip, 'test'));
$session->beginGoogle('state-7', 'nonce-7');
$google->identity = ['sub' => '222', 'email' => 'one@customer-test.invalid', 'name' => 'Test One'];
$check('state is single use (replayed callback fails)', $auth->googleFinish('state-7', 'code', $ip, 'test') && !$auth->googleFinish('state-7', 'code', $ip, 'test'));
$url = (new GoogleOAuthService())->authorizationUrl('S', 'N');
$check('authorization URL targets Google with state, nonce and the exact callback', str_starts_with($url, 'https://accounts.google.com/o/oauth2/v2/auth?')
    && str_contains($url, 'state=S') && str_contains($url, 'nonce=N') && str_contains($url, rawurlencode('http://localhost:8000/auth/google/callback/')) && !str_contains($url, 'test-secret'));

echo "ID token checks\n";
$b64 = static fn (array $a): string => rtrim(strtr(base64_encode(json_encode($a)), '+/', '-_'), '=');
$claims = ['iss' => 'https://accounts.google.com', 'aud' => AppConfig::googleClientId(), 'exp' => time() + 600, 'nonce' => 'n1',
    'sub' => '12345', 'email' => 'Who@Example.com', 'email_verified' => true, 'name' => 'Who Ever'];
$token = static fn (array $c): string => $b64(['alg' => 'RS256']) . '.' . $b64($c) . '.sig';
$check('a valid token yields the identity (email lowercased)', GoogleOAuthService::claimsFrom($token($claims), 'n1') === ['sub' => '12345', 'email' => 'who@example.com', 'name' => 'Who Ever']);
$check('wrong nonce rejected', GoogleOAuthService::claimsFrom($token($claims), 'other') === null);
$check('wrong audience rejected', GoogleOAuthService::claimsFrom($token(['aud' => 'x'] + $claims), 'n1') === null);
$check('wrong issuer rejected', GoogleOAuthService::claimsFrom($token(['iss' => 'https://evil.example'] + $claims), 'n1') === null);
$check('expired token rejected', GoogleOAuthService::claimsFrom($token(['exp' => time() - 5] + $claims), 'n1') === null);
$check('unverified email rejected', GoogleOAuthService::claimsFrom($token(['email_verified' => false] + $claims), 'n1') === null);
$check('string "true" for email_verified rejected', GoogleOAuthService::claimsFrom($token(['email_verified' => 'true'] + $claims), 'n1') === null);
$check('malformed tokens rejected', GoogleOAuthService::claimsFrom('not.a.jwt.at.all', 'n1') === null && GoogleOAuthService::claimsFrom('a.b.c', 'n1') === null);
$check('angle brackets in the Google name are stripped', (GoogleOAuthService::claimsFrom($token(['name' => '<b>Eve</b>'] + $claims), 'n1')['name'] ?? '') === 'bEve/b');

$pdo->rollBack();
$check('rolled back: no test customers remain', $count("SELECT COUNT(*) FROM customers WHERE email LIKE '%customer-test.invalid'") === 0);

echo "\nResult: $passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
