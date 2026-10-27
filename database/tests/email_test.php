<?php

declare(strict_types=1);

/**
 * Tests for the email infrastructure (Brevo SMTP through PHPMailer). No email is ever sent: the configuration is
 * replaced with test values, and the one "real" send goes to a closed local port so it must fail.
 * Run: php database/tests/email_test.php
 */

require_once dirname(__DIR__, 2) . '/app/services/EmailService.php';

$pass = 0;
$fail = 0;
$check = static function (string $name, bool $condition, string $detail = '') use (&$pass, &$fail): void {
    if ($condition) { $pass++; echo "  PASS  $name\n"; }
    else { $fail++; echo "  FAIL  $name" . ($detail !== '' ? "  -> $detail" : '') . "\n"; }
};
/** Sets environment values for this process only (they win over .env in AppConfig::env). */
$env = static function (array $values): void {
    foreach ($values as $k => $v) {
        putenv($k . '=' . $v);
    }
};
$secret = 'xsmtpsib-TEST-' . bin2hex(random_bytes(16));
$login = 'test-login-' . bin2hex(random_bytes(3)) . '@smtp-brevo.com';
$base = ['MAIL_MAILER' => 'smtp', 'SMTP_HOST' => 'smtp-relay.brevo.com', 'SMTP_PORT' => '587', 'SMTP_ENCRYPTION' => 'tls',
    'SMTP_USERNAME' => $login, 'SMTP_PASSWORD' => $secret, 'MAIL_FROM_ADDRESS' => 'sender@example.test', 'MAIL_FROM_NAME' => 'Test Sender'];
$env($base);

echo "Configuration\n";
$check('PHPMailer is installed', class_exists(\PHPMailer\PHPMailer\PHPMailer::class));
$check('complete SMTP configuration is accepted', EmailService::isConfigured() && EmailService::configProblem() === null, (string) EmailService::configProblem());
$check('defaults: Brevo relay, port 587, STARTTLS', AppConfig::smtpHost() === 'smtp-relay.brevo.com' && AppConfig::smtpPort() === 587 && AppConfig::smtpEncryption() === 'tls');
// A blank value is set as one space: putenv('X=') would make AppConfig fall back to the real .env.
$cases = [
    'invalid sender'            => ['MAIL_FROM_ADDRESS' => 'not-an-email'],
    'missing password'          => ['SMTP_PASSWORD' => ' '],
    'missing username'          => ['SMTP_USERNAME' => ' '],
    'missing host'              => ['SMTP_HOST' => ' '],
    'no encryption'             => ['SMTP_ENCRYPTION' => 'none'],
    'implicit ssl (not STARTTLS)' => ['SMTP_ENCRYPTION' => 'ssl'],
];
foreach ($cases as $label => $change) {
    $env($base);
    $env($change);
    $problem = EmailService::configProblem();
    $check('refused: ' . $label, $problem !== null && !EmailService::isConfigured() && !str_contains((string) $problem, $secret), (string) $problem);
}
$env($base);
$check('MAIL_FROM_EMAIL (older name) still works when MAIL_FROM_ADDRESS is absent', (static function () use ($env, $base): bool {
    putenv('MAIL_FROM_ADDRESS');
    putenv('MAIL_FROM_EMAIL=older@example.test');
    $ok = AppConfig::mailFromEmail() !== '';
    $env($base);
    putenv('MAIL_FROM_EMAIL');

    return $ok;
})());
$check('MAIL_MAILER: anything unknown means smtp; brevo_api selects the API', (static function () use ($env, $base): bool {
    $env(['MAIL_MAILER' => 'sendmail']);
    $a = AppConfig::mailMailer() === 'smtp';
    $env(['MAIL_MAILER' => 'brevo_api']);
    $b = AppConfig::mailMailer() === 'brevo_api';
    $env($base);

    return $a && $b;
})());

echo "\nInput refused before anything is sent\n";
$log = sys_get_temp_dir() . '/vip_email_test_' . bin2hex(random_bytes(4)) . '.log';
$prev = ini_set('error_log', $log);
$service = new EmailService();
$check('invalid recipient refused', $service->send('not-an-address', 'X', 'Subject', '<p>x</p>', 'x') === false);
$check('line break in the recipient name refused (header injection)', $service->send('to@example.test', "Name\r\nBcc: victim@example.test", 'Subject', '<p>x</p>', 'x') === false);
$check('line break in the subject refused (header injection)', $service->send('to@example.test', 'Name', "Hi\nBcc: victim@example.test", '<p>x</p>', 'x') === false);
$check('empty subject refused', $service->send('to@example.test', 'Name', '  ', '<p>x</p>', 'x') === false);

echo "\nFailure handling (closed local port: nothing can be sent)\n";
$env(['SMTP_HOST' => '127.0.0.1', 'SMTP_PORT' => '9']);
$start = microtime(true);
$sent = $service->send('someone@example.test', 'Someone', 'Reset link', '<p>https://example.test/reset?token=SECRETTOKEN</p>', 'https://example.test/reset?token=SECRETTOKEN');
$check('a failed send returns false (no exception reaches the caller)', $sent === false);
$check('fails within the timeout', microtime(true) - $start < 15);
$check('verifyConnection reports a short reason, no exception', is_string($service->verifyConnection()));
$env($base);
ini_set('error_log', (string) $prev);
$logged = (string) @file_get_contents($log);
@unlink($log);
$check('the failure is logged', str_contains($logged, 'EmailService: SMTP send failed'), $logged);
$check('the log never contains the SMTP password', !str_contains($logged, $secret));
$check('the log never contains the SMTP username', !str_contains($logged, $login));
$check('the log never contains the message body or the reset token', !str_contains($logged, 'SECRETTOKEN'));
$check('the log never contains the recipient address', !str_contains($logged, 'someone@example.test'));

$check('every SMTP wait is capped at 10 s (connect AND each server reply; PHPMailer would wait 300 s for a reply)', (static function (): bool {
    $m = (new ReflectionMethod(EmailService::class, 'smtpMailer'))->invoke(new EmailService());

    return $m->Timeout <= 10 && $m->getSMTPInstance()->Timelimit <= 10;
})());

echo "\nLog cleaning\n";
$check('clean() removes password, username and every address, one line, max 200', (static function () use ($secret, $login): bool {
    $c = EmailService::clean("AUTH failed for $login with $secret\nRCPT TO:<bob@example.test> " . str_repeat('x', 400));

    return !str_contains($c, $secret) && !str_contains($c, $login) && !str_contains($c, 'bob@') && !str_contains($c, "\n") && mb_strlen($c) <= 200;
})());

echo "\nTest script\n";
$script = (string) file_get_contents(dirname(__DIR__) . '/send_test_email.php');
$check('the test script refuses to run outside the command line (404)', str_contains($script, "if (PHP_SAPI !== 'cli')") && str_contains($script, 'http_response_code(404)'));
$check('the test script never prints the password', !preg_match('/echo[^;]*smtpPassword\(\)\s*[.;]/', $script));

echo "\nBranded template\n";
$brand = ['name' => 'Test <Autos>', 'logo' => 'https://cdn.example.test/logo.png', 'site' => 'https://www.example.test/', 'phones' => ['+1 416-577-3963', '055 660 5451']];
$content = ['subject' => 'Hello', 'preheader' => 'Preview line', 'eyebrow' => 'Account', 'title' => 'Title <b>x</b>', 'greeting' => 'Hello "Ama",',
    'paragraphs' => ["Line one <script>alert(1)</script>", 'Second'], 'details' => [['Price', 'GHS 1,000 & up']],
    'button' => ['label' => 'Open', 'url' => 'https://www.example.test/a?b=1&c=2'], 'note' => 'Note', 'reason' => 'Why you got this'];
$out = EmailTemplate::render($content, $brand);
$check('every value is escaped (no raw tags from content or brand)', !str_contains($out['html'], '<script>') && !str_contains($out['html'], '<b>x</b>') && !str_contains($out['html'], 'Test <Autos>') && str_contains($out['html'], 'Title &lt;b&gt;x&lt;/b&gt;'));
$check('layout parts present: accent bar, logo, eyebrow, button as a table cell, details panel, footer phones, site, reason, preview line',
    str_contains($out['html'], 'https://cdn.example.test/logo.png') && str_contains($out['html'], 'ACCOUNT') === false && str_contains($out['html'], '>Account<')
    && str_contains($out['html'], 'bgcolor="#D42A25"') && str_contains($out['html'], 'GHS 1,000 &amp; up') && str_contains($out['html'], '055 660 5451')
    && str_contains($out['html'], 'www.example.test') && str_contains($out['html'], 'Why you got this') && str_contains($out['html'], 'Preview line'));
$check('plain-text version carries the same content and the full link', str_contains($out['text'], 'Title <b>x</b>') && str_contains($out['text'], 'https://www.example.test/a?b=1&c=2')
    && str_contains($out['text'], 'Price: GHS 1,000 & up') && str_contains($out['text'], 'Phone: 055 660 5451') && !str_contains($out['text'], '<td'));
$bad = EmailTemplate::render(['title' => 'T', 'button' => ['label' => 'Bad', 'url' => 'javascript:alert(1)']], ['name' => 'B', 'logo' => 'http://insecure.test/logo.png', 'site' => 'ftp://x.test/', 'phones' => []]);
$check('unsafe links are dropped: javascript: button, http logo, non-http site', !str_contains($bad['html'], 'javascript:') && !str_contains($bad['html'], 'insecure.test') && !str_contains($bad['html'], 'ftp://') && !str_contains($bad['text'], 'javascript:'));
$check('without a public https address the header shows the brand name as text', str_contains($bad['html'], '&#9612;</span> B</span>'));
$check('the email logo file exists, is a PNG on a white background, and is small', (static function (): bool {
    $f = dirname(__DIR__, 2) . '/assets/images/email/logo.png';
    $i = is_file($f) ? getimagesize($f) : false;

    return $i !== false && $i[2] === IMAGETYPE_PNG && filesize($f) < 100 * 1024;
})());
putenv('APP_URL=http://localhost:8000');
$check('brand(): logo and site always on the production domain, even when APP_URL is localhost', EmailService::brand()['logo'] === 'https://nextgenautosandparts.com/assets/images/email/logo.png' && EmailService::brand()['site'] === 'https://nextgenautosandparts.com/');
putenv('APP_URL=https://www.example.test');
$check('brand(): APP_URL (another https address) does not change email links', EmailService::brand()['site'] === 'https://nextgenautosandparts.com/');
putenv('APP_URL');

echo "\nVersion control\n";
$gitignore = (string) file_get_contents(dirname(__DIR__, 2) . '/.gitignore');
$check('.env is ignored by Git', preg_match('/^\.env$/m', $gitignore) === 1);
$example = (string) file_get_contents(dirname(__DIR__, 2) . '/.env.example');
$check('.env.example holds no SMTP key or API key value', preg_match('/^(SMTP_PASSWORD|BREVO_API_KEY)=\S/m', $example) !== 1 && !str_contains($example, 'xsmtpsib-'));

echo "\nResult: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
