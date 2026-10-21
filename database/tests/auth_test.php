<?php

declare(strict_types=1);

/**
 * Authentication/security logic tests (spec 04_spec-_admin_auth.md, section 48).
 *
 * CLI only:  php database/tests/auth_test.php
 * Requires:  schema + seed loaded; DB credentials in .env.
 *
 * All rows are created inside a transaction that is rolled back. Passwords used
 * here are random, generated per run, and never printed. Output is buffered so
 * PHP sessions can start (CSRF helper tests) without "headers already sent".
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

ob_start();

require_once dirname(__DIR__, 2) . '/app/services/AuthService.php';
require_once dirname(__DIR__, 2) . '/app/services/PasswordPolicy.php';
require_once dirname(__DIR__, 2) . '/app/helpers/csrf.php';

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

/** Session double: records what AuthService asks of the session, touches no real session. */
class FakeSessions extends SessionService
{
    public ?array $data = null;
    public int $established = 0;
    public bool $destroyed = false;
    public ?array $lastEstablished = null;

    public function establish(array $admin): void
    {
        $this->established++;
        $this->lastEstablished = $admin;
        $now = time();
        $this->data = ['admin_id' => (int) $admin['id'], 'role' => $admin['role'], 'authenticated_at' => $now, 'last_activity' => $now];
    }
    public function current(): ?array { return $this->data; }
    public function touch(string $role): void { if ($this->data) { $this->data['last_activity'] = time(); $this->data['role'] = $role; } }
    public function destroy(): void { $this->destroyed = true; $this->data = null; }
}

function randomPassword(): string
{
    return 'Pw-' . bin2hex(random_bytes(10)) . '-Zz9!';
}

// ------------------------------------------------------------ configuration
section('Configuration (environment-aware)');
// These checks must not depend on the developer's own .env: hide the file values while they run.
AppConfig::env('DB_HOST'); // make sure the file has been read once
$fileCache = new ReflectionProperty(AppConfig::class, 'fileValues');
$originalFileValues = $fileCache->getValue();
$fileCache->setValue(null, []);
putenv('APP_ENV');   // unset: must default to the safe choice
check('APP_ENV unset defaults to production', AppConfig::environment() === 'production' && AppConfig::isProduction());
putenv('APP_ENV=development');
check('APP_ENV=development is honored', AppConfig::environment() === 'development');
putenv('APP_ENV=staging');
check('unknown APP_ENV falls back to production', AppConfig::environment() === 'production');

$_SERVER['HTTPS'] = '';
$_SERVER['SERVER_PORT'] = '80';
putenv('APP_ENV=development');
check('development over plain HTTP: cookie not Secure (localhost keeps working)', AppConfig::cookieSecure() === false);
$_SERVER['HTTPS'] = 'on';
check('development over HTTPS: cookie Secure', AppConfig::cookieSecure() === true);
$_SERVER['HTTPS'] = '';
putenv('APP_ENV=production');
check('production: cookie always Secure', AppConfig::cookieSecure() === true);
$params = SessionService::cookieParams();
check('session cookie is HttpOnly, SameSite=Lax, path /, browser-session lifetime',
    $params['httponly'] === true && $params['samesite'] === 'Lax' && $params['path'] === '/' && $params['lifetime'] === 0);

putenv('SESSION_TIMEOUT_SECONDS');
check('default session timeout is 30 minutes', AppConfig::sessionTimeoutSeconds() === 1800);
putenv('SESSION_TIMEOUT_SECONDS=10');
check('production ignores a short testing timeout (falls back to 30 minutes)', AppConfig::sessionTimeoutSeconds() === 1800);
putenv('SESSION_TIMEOUT_SECONDS=900');
check('production accepts a legitimate configured timeout', AppConfig::sessionTimeoutSeconds() === 900);
putenv('APP_ENV=development');
putenv('SESSION_TIMEOUT_SECONDS=10');
check('development accepts a short testing timeout', AppConfig::sessionTimeoutSeconds() === 10);
putenv('SESSION_TIMEOUT_SECONDS=999999999');
check('timeout is capped at 24 hours', AppConfig::sessionTimeoutSeconds() === 86400);
putenv('SESSION_TIMEOUT_SECONDS');
putenv('APP_ENV');

$fileCache->setValue(null, $originalFileValues); // restore: the database connection below needs the real .env

// ------------------------------------------------------------ password policy
section('Password policy');
check('11 characters rejected', PasswordPolicy::validate('Abcdef1!xyz') !== []);
check('12 characters accepted (no composition rules: passphrases welcome)', PasswordPolicy::validate('correct horse battery') === []);
check('all lowercase 12+ accepted', PasswordPolicy::validate('abcdefghijklmnop') === []);
check('over 72 bytes rejected (bcrypt truncation)', PasswordPolicy::validate(str_repeat('ab1', 30)) !== []);
check('repetitive password rejected', PasswordPolicy::validate(str_repeat('a', 20)) !== []);
check('password equal to the email rejected', PasswordPolicy::validate('someone@example.test', 'someone@example.test') !== []);
check('strength labels', PasswordPolicy::strength('short') === 'Weak' && PasswordPolicy::strength('abcdefghijklmnop1') !== 'Weak'
    && PasswordPolicy::strength('Correct-Horse-Battery-Staple-9!') === 'Strong');

// ------------------------------------------------------------ authorization
section('Authorization foundation');
check('administrator role may do everything defined', AuthorizationService::can('administrator', 'admin.access') && AuthorizationService::can('administrator', 'vehicles.manage'));
check('unknown role has no permissions (no implicit access)', !AuthorizationService::can('sales', 'admin.access') && !AuthorizationService::can('', 'admin.access'));
check('legacy schema default role "admin" is NOT silently trusted', !AuthorizationService::can('admin', 'admin.access'));

// ------------------------------------------------------------ csrf
section('CSRF helpers');
$token = csrf_token();
check('token is 64 hex chars (32 random bytes)', preg_match('/^[a-f0-9]{64}$/', $token) === 1);
check('token is stable within a session', csrf_token() === $token);
check('correct token verifies', csrf_verify($token));
check('missing/empty/wrong/array tokens are rejected',
    !csrf_verify(null) && !csrf_verify('') && !csrf_verify(str_repeat('0', 64)) && !csrf_verify(['x']) && !csrf_verify($token . 'x') && !csrf_verify(strtoupper($token)));
check('csrf_field() embeds the token as an escaped hidden input', str_contains(csrf_field(), 'type="hidden"') && str_contains(csrf_field(), $token));

// ------------------------------------------------------------ authentication
section('Authentication (temporary rows, rolled back)');
$pdo = Database::connection();
$users = new AdminUserRepository($pdo);
$audit = new AuditLogRepository($pdo);
$baselineAdmins = $users->count();
$baselineAudit = (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();

$pdo->beginTransaction();
try {
    $pwActive = randomPassword();
    $pwDisabled = randomPassword();
    $pwOld = randomPassword();
    $ids = [
        'active'   => $users->create('Test Admin', 'active.test@example.test', password_hash($pwActive, PASSWORD_DEFAULT), 'administrator'),
        'disabled' => $users->create('Disabled Admin', 'disabled.test@example.test', password_hash($pwDisabled, PASSWORD_DEFAULT), 'administrator'),
        'old'      => $users->create('Old Hash Admin', 'oldhash.test@example.test', password_hash($pwOld, PASSWORD_BCRYPT, ['cost' => 4]), 'administrator'),
        'norole'   => $users->create('No Role', 'norole.test@example.test', password_hash('unused-password-xyz', PASSWORD_BCRYPT, ['cost' => 4]), 'sales'),
    ];
    run($pdo, "UPDATE admin_users SET status = 'disabled' WHERE id = ?", [$ids['disabled']]);

    $fake = new FakeSessions();
    $auth = new AuthService($pdo, $users, $audit, $fake);
    $ipA = '203.0.113.10';
    $ipB = '203.0.113.20';
    $ua = 'PHPUnit-less test agent';

    // 48.1 success
    $r = $auth->attempt('  ACTIVE.Test@Example.Test ', $pwActive, $ipA, $ua);
    check('correct credentials succeed (email normalized: case/whitespace ignored)', $r['status'] === 'ok' && $r['admin']['id'] === $ids['active']);
    check('session established exactly once with only id/name/role (no secrets)', $fake->established === 1 && array_keys($fake->lastEstablished) === ['id', 'name', 'role'] && !str_contains(json_encode($fake->lastEstablished), $pwActive));
    $row = run($pdo, 'SELECT last_login_at FROM admin_users WHERE id = ?', [$ids['active']])->fetchColumn();
    check('last_login_at updated on success', $row !== null);
    $events = run($pdo, 'SELECT event_type, admin_user_id, ip_address, user_agent FROM audit_logs ORDER BY id DESC LIMIT 1')->fetch();
    check('LOGIN_SUCCESS logged with admin id, ip and user agent', $events['event_type'] === 'LOGIN_SUCCESS' && (int) $events['admin_user_id'] === $ids['active'] && $events['ip_address'] === $ipA && $events['user_agent'] === $ua);

    // 48.2 / 48.3 / 48.4 failures look identical to the caller
    $wrong = $auth->attempt('active.test@example.test', 'definitely-wrong-password', $ipB, $ua);
    $unknown = $auth->attempt('nobody.test@example.test', 'definitely-wrong-password', $ipB, $ua);
    $disabled = $auth->attempt('disabled.test@example.test', $pwDisabled, $ipB, $ua); // CORRECT password, disabled account
    check('wrong password / unknown email / disabled account (correct password) return the identical result',
        $wrong === $unknown && $unknown === $disabled && $wrong === ['status' => 'invalid', 'admin' => null], json_encode([$wrong, $unknown, $disabled]));
    check('no session is established for any failure', $fake->established === 1);
    check('disabled account is rejected even with the correct password', $disabled['status'] === 'invalid');
    $types = run($pdo, "SELECT event_type FROM audit_logs WHERE ip_address = ? ORDER BY id", [$ipB])->fetchAll(PDO::FETCH_COLUMN);
    check('internal log still distinguishes the three causes', $types === ['LOGIN_FAILURE', 'LOGIN_FAILURE', 'ACCOUNT_DISABLED_LOGIN_ATTEMPT'], json_encode($types));
    check('last_login_at NOT updated by failures', run($pdo, 'SELECT last_login_at FROM admin_users WHERE id = ?', [$ids['disabled']])->fetchColumn() === null);
    $malformed = $auth->attempt('not-an-email', 'x', $ipB, $ua);
    $huge = $auth->attempt('active.test@example.test', str_repeat('A', 5000), $ipB, $ua);
    check('malformed email and absurdly long password are rejected like any failure', $malformed['status'] === 'invalid' && $huge['status'] === 'invalid' && $fake->established === 1);

    // timing: unknown-email path should cost about the same as wrong-password path
    $time = function (string $email) use ($auth, $ipA, $ua): float {
        $t = hrtime(true);
        $auth->attempt($email, 'timing-probe-password', '198.51.100.' . random_int(1, 250), $ua);
        return (hrtime(true) - $t) / 1e6;
    };
    $known = [$time('active.test@example.test'), $time('active.test@example.test'), $time('active.test@example.test')];
    $miss = [$time('ghost1.test@example.test'), $time('ghost2.test@example.test'), $time('ghost3.test@example.test')];
    $ratio = min($miss) / min($known); // minimums: robust against one-off machine load
    check('unknown-email timing is within 40% of wrong-password timing (dummy-hash verification)', $ratio > 0.6 && $ratio < 1.4, sprintf('ratio %.2f', $ratio));

    // hash upgrade
    $before = run($pdo, 'SELECT password_hash FROM admin_users WHERE id = ?', [$ids['old']])->fetchColumn();
    $up = $auth->attempt('oldhash.test@example.test', $pwOld, $ipA, $ua);
    $after = run($pdo, 'SELECT password_hash FROM admin_users WHERE id = ?', [$ids['old']])->fetchColumn();
    check('weak/old password hash is upgraded to the current default on login', $up['status'] === 'ok' && $before !== $after && !password_needs_rehash($after, PASSWORD_DEFAULT) && password_verify($pwOld, $after));
    check('stored hashes are bcrypt/argon (password_hash), never plaintext', str_starts_with($after, '$2y$') && !str_contains($after, $pwOld));

    // 48.9 rate limiting
    $pdo->exec('DELETE FROM audit_logs'); // isolate the throttle scenario (rolled back with everything else)
    $ipC = '192.0.2.50';
    for ($i = 1; $i <= 5; $i++) {
        $res = $auth->attempt('active.test@example.test', 'wrong-guess-' . $i, $ipC, $ua);
    }
    check('five failures from one IP for one account are still processed as failures', $res['status'] === 'invalid');
    $sixth = $auth->attempt('active.test@example.test', $pwActive, $ipC, $ua); // even the CORRECT password is refused while throttled
    check('6th attempt (even with the correct password) is throttled', $sixth['status'] === 'throttled');
    $seventh = $auth->attempt('active.test@example.test', 'wrong', $ipC, $ua);
    check('throttled attempts are not counted again (no way to extend a lockout indefinitely)',
        $seventh['status'] === 'throttled' && (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type = 'LOGIN_FAILURE'")->fetchColumn() === 5);
    check('RATE_LIMIT_TRIGGERED is logged once per burst, not once per request',
        (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type = 'RATE_LIMIT_TRIGGERED'")->fetchColumn() === 1);
    $other = $auth->attempt('active.test@example.test', $pwActive, '192.0.2.99', $ua);
    check('the legitimate administrator can still sign in from a different IP (an attacker cannot lock the account out)', $other['status'] === 'ok');
    $again = $auth->attempt('active.test@example.test', $pwActive, $ipC, $ua);
    check('a successful sign-in resets that account\'s failure state', $again['status'] === 'ok');

    $pdo->exec('DELETE FROM audit_logs');
    for ($i = 1; $i <= 10; $i++) {
        $auth->attempt("spray{$i}.test@example.test", 'guess', '192.0.2.60', $ua);
    }
    check('10 failures from one IP across different accounts throttle that IP (password spraying)', $auth->attempt('another.test@example.test', 'guess', '192.0.2.60', $ua)['status'] === 'throttled');
    check('...but a different IP is unaffected', $auth->attempt('another.test@example.test', 'guess', '192.0.2.61', $ua)['status'] === 'invalid');

    $pdo->exec('DELETE FROM audit_logs');
    $fp = AuthService::identifier('victim.test@example.test');
    $ins = $pdo->prepare("INSERT INTO audit_logs (event_type, entity_type, entity_id, ip_address) VALUES ('LOGIN_FAILURE', 'login_identifier', ?, ?)");
    for ($i = 0; $i < 30; $i++) {
        $ins->execute([$fp, '10.0.' . intdiv($i, 250) . '.' . ($i % 250 + 1)]); // 30 different IPs
    }
    check('30 failures for one account from many IPs throttle the account (distributed attack)', $auth->attempt('victim.test@example.test', 'x', '10.9.9.9', $ua)['status'] === 'throttled');
    check('...and an unrelated account is unaffected', $auth->attempt('unrelated.test@example.test', 'x', '10.9.9.9', $ua)['status'] === 'invalid');

    $pdo->exec("UPDATE audit_logs SET created_at = DATE_SUB(created_at, INTERVAL 20 MINUTE)");
    check('failures older than the 15-minute window stop counting (lockouts are temporary)', $auth->attempt('victim.test@example.test', 'x', '10.9.9.9', $ua)['status'] === 'invalid');

    // identifiers: no raw email stored
    $stored = run($pdo, 'SELECT CONCAT_WS(" ", event_type, entity_type, entity_id, description, ip_address, user_agent) FROM audit_logs')->fetchAll(PDO::FETCH_COLUMN);
    check('audit log never contains an email address from a failed attempt', count(array_filter($stored, fn ($l) => str_contains($l, '@'))) === 0);
    check('identifier is a deterministic one-way fingerprint that fits the BIGINT column', AuthService::identifier('a@b.test') === AuthService::identifier('a@b.test') && AuthService::identifier('a@b.test') > 0 && AuthService::identifier('a@b.test') < PHP_INT_MAX);

    // session resolution
    section('Session resolution (guard logic)');
    $fake = new FakeSessions();
    $auth = new AuthService($pdo, $users, $audit, $fake);
    check('no session -> none', $auth->resolveSession($ipA, $ua)['status'] === 'none');

    $fake->establish(['id' => $ids['active'], 'role' => 'administrator']);
    $res = $auth->resolveSession($ipA, $ua);
    check('valid session -> ok with fresh name/role from the database', $res['status'] === 'ok' && array_intersect_key($res['admin'], ['id' => 1, 'name' => 1, 'role' => 1]) === ['id' => $ids['active'], 'name' => 'Test Admin', 'role' => 'administrator'] && $res['admin']['two_factor_enabled'] === false);

    $fake->data['last_activity'] = time() - AppConfig::sessionTimeoutSeconds() - 5;
    $before = (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type = 'SESSION_TIMEOUT'")->fetchColumn();
    $res = $auth->resolveSession($ipA, $ua);
    check('inactivity beyond the timeout -> expired, session destroyed, SESSION_TIMEOUT logged',
        $res['status'] === 'expired' && $fake->destroyed && $fake->data === null
        && (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type = 'SESSION_TIMEOUT'")->fetchColumn() === $before + 1);

    $fake = new FakeSessions();
    $auth = new AuthService($pdo, $users, $audit, $fake);
    $fake->establish(['id' => $ids['active'], 'role' => 'administrator']);
    $fake->data['last_activity'] = time() - 10;
    $auth->resolveSession($ipA, $ua);
    check('activity within the timeout extends the session', $fake->data['last_activity'] >= time() - 1);

    $fake->establish(['id' => $ids['disabled'], 'role' => 'administrator']);
    check('account disabled while signed in -> session invalidated immediately', $auth->resolveSession($ipA, $ua)['status'] === 'invalid' && $fake->destroyed);

    $fake = new FakeSessions();
    $auth = new AuthService($pdo, $users, $audit, $fake);
    $fake->establish(['id' => $ids['norole'], 'role' => 'administrator']); // session claims admin, database says "sales"
    check('role is re-read from the database: a tampered/stale session role grants nothing', $auth->resolveSession($ipA, $ua)['status'] === 'invalid');

    $fake = new FakeSessions();
    $auth = new AuthService($pdo, $users, $audit, $fake);
    $fake->establish(['id' => 99999999, 'role' => 'administrator']);
    check('session for a deleted/nonexistent admin -> invalid', $auth->resolveSession($ipA, $ua)['status'] === 'invalid');

    $fake->establish(['id' => $ids['active'], 'role' => 'administrator']);
    $auth->logout($ids['active'], $ipA, $ua);
    check('logout logs LOGOUT and destroys the session', $fake->destroyed && (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type = 'LOGOUT'")->fetchColumn() === 1);

    // passwords never in the log
    $everything = implode("\n", run($pdo, "SELECT CONCAT_WS('|', event_type, entity_type, entity_id, description, ip_address, user_agent) FROM audit_logs")->fetchAll(PDO::FETCH_COLUMN));
    $secrets = [$pwActive, $pwDisabled, $pwOld, 'definitely-wrong-password', 'timing-probe-password'];
    check('no test password (right or wrong) appears anywhere in the audit log', count(array_filter($secrets, fn ($s) => str_contains($everything, $s))) === 0);
    check('no password hash appears in the audit log', !str_contains($everything, '$2y$'));
} finally {
    $pdo->rollBack();
}

section('Cleanup');
check('rolled back: admin_users and audit_logs back to their original row counts',
    $users->count() === $baselineAdmins && (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() === $baselineAudit);

ob_end_clean();
echo implode("\n", $report), "\n\nResult: $passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
