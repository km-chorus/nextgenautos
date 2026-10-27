<?php

declare(strict_types=1);

/**
 * Brevo email integration (2026-10-27). CLI only:
 *   php database/tests/email_integration_test.php
 * NO real email is sent: the Brevo API transport is pointed at a local stub server started by this test (it records the
 * request), and the enquiry alert uses a recording EmailService. Database rows are written inside transactions that are
 * rolled back. The real SMTP delivery check is separate: php database/send_test_email.php --check / <address>.
 *
 *  - EmailService: Reply-To support; From is always the verified sender; unsafe addresses refused (header injection).
 *  - EnquiryAlertService: content, escaping, recipient setting, success / failure / exception handling, audit.
 *  - PublicEnquiryService: an alert only for an enquiry that was really saved; never inside an open transaction;
 *    the signed-in customer's id is stored with the enquiry (regression fix).
 *  - The notification address is a private setting (never read by the public contact details).
 *  - .htaccess blocks .env, *.log and cPanel's error_log files.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);
require_once $root . '/app/services/EmailService.php';
require_once $root . '/app/services/EnquiryAlertService.php';
require_once $root . '/app/services/PublicEnquiryService.php';
require_once $root . '/app/services/CompanyContactService.php';
require_once $root . '/app/services/SettingsService.php';

$passed = 0;
$failed = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$passed, &$failed): void {
    $ok ? $passed++ : $failed++;
    echo '  ' . ($ok ? 'PASS' : 'FAIL') . "  $label" . (!$ok && $detail !== '' ? " -> $detail" : '') . "\n";
};

// Test-only mail settings (never the developer's real ones): API transport to a local stub, synthetic sender.
putenv('MAIL_MAILER=brevo_api');
putenv('BREVO_API_KEY=test-key-not-real');
putenv('MAIL_FROM_ADDRESS=noreply@example.test');
putenv('MAIL_FROM_EMAIL=noreply@example.test');
putenv('MAIL_FROM_NAME=Next Gen Autos and Parts');
putenv('SMTP_USERNAME=');
putenv('SMTP_PASSWORD=');

// --- local stub of the Brevo API: records each request body and the api-key header, answers 201.
$stubDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'brevo-stub-' . bin2hex(random_bytes(4));
mkdir($stubDir);
$capture = $stubDir . DIRECTORY_SEPARATOR . 'requests.jsonl';
file_put_contents($stubDir . DIRECTORY_SEPARATOR . 'router.php', '<?php
file_put_contents(' . var_export($capture, true) . ', json_encode(["path" => $_SERVER["REQUEST_URI"], "key" => $_SERVER["HTTP_API_KEY"] ?? null, "body" => file_get_contents("php://input")]) . "\n", FILE_APPEND);
http_response_code(201); header("Content-Type: application/json"); echo "{\"messageId\":\"<stub@example.test>\"}";');
$sock = stream_socket_server('tcp://127.0.0.1:0');
$port = (int) substr((string) stream_socket_get_name($sock, false), strrpos((string) stream_socket_get_name($sock, false), ':') + 1);
fclose($sock);
$server = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, $stubDir . DIRECTORY_SEPARATOR . 'router.php'], [1 => ['file', $stubDir . DIRECTORY_SEPARATOR . 'out.txt', 'w'], 2 => ['file', $stubDir . DIRECTORY_SEPARATOR . 'err.txt', 'w']], $pipes);
putenv('BREVO_API_BASE=http://127.0.0.1:' . $port);
for ($i = 0; $i < 50 && @fsockopen('127.0.0.1', $port) === false; $i++) {
    usleep(100000);
}
$requests = static function () use ($capture): array {
    $lines = is_file($capture) ? file($capture, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

    return array_map(static fn (string $l): array => json_decode($l, true), $lines ?: []);
};
// error_log lines go to a temp file so the test can check them (and they are not mistaken for PHP warnings)
$logFile = $stubDir . DIRECTORY_SEPARATOR . 'php.log';
ini_set('error_log', $logFile);
$log = static fn (): string => is_file($logFile) ? (string) file_get_contents($logFile) : '';

try {
    echo "EmailService: addresses\n";
    $check('a normal address is accepted', EmailService::isSafeAddress('ama.mensah@example.com'));
    $check('a line break (header injection) is refused', !EmailService::isSafeAddress("ama@example.com\r\nBcc: x@example.com"));
    $check('a second address (comma) is refused', !EmailService::isSafeAddress('ama@example.com,x@example.com'));
    $check('spaces and angle brackets are refused', !EmailService::isSafeAddress('Ama <ama@example.com>'));
    $check('not an address is refused', !EmailService::isSafeAddress('ama@'));
    $check('over 255 characters is refused', !EmailService::isSafeAddress(str_repeat('a', 250) . '@example.com'));

    echo "\nEmailService: From and Reply-To (Brevo API stub)\n";
    $mail = new EmailService();
    $check('the stub transport is configured', EmailService::isConfigured(), (string) EmailService::configProblem());
    $ok = $mail->send('team@example.test', 'Team', 'Subject', '<p>Hi</p>', 'Hi', 'customer@example.test', 'Ama Mensah');
    $r = $requests();
    $body = json_decode((string) ($r[0]['body'] ?? '{}'), true) ?: [];
    $check('a message with Reply-To is accepted', $ok && count($r) === 1);
    $check('From is the verified sender, never the customer', ($body['sender']['email'] ?? null) === 'noreply@example.test' && ($body['sender']['name'] ?? null) === 'Next Gen Autos and Parts');
    $check('Reply-To is the customer', ($body['replyTo']['email'] ?? null) === 'customer@example.test' && ($body['replyTo']['name'] ?? null) === 'Ama Mensah');
    $check('the recipient is the team address only', ($body['to'] ?? null) === [['email' => 'team@example.test', 'name' => 'Team']]);
    $check('the API key goes in the header, not the body', ($r[0]['key'] ?? null) === 'test-key-not-real' && !str_contains((string) ($r[0]['body'] ?? ''), 'test-key-not-real'));

    $mail->send('team@example.test', '', 'Subject', '<p>Hi</p>', 'Hi');
    $body2 = json_decode((string) ($requests()[1]['body'] ?? '{}'), true) ?: [];
    $check('no Reply-To when none is given', count($requests()) === 2 && !array_key_exists('replyTo', $body2));

    $before = count($requests());
    $refused = !$mail->send('team@example.test', '', 'Subject', '<p>Hi</p>', 'Hi', "x@example.test\r\nBcc: victim@example.test", 'Ama')
        && !$mail->send('team@example.test', '', 'Subject', '<p>Hi</p>', 'Hi', 'x@example.test', "Ama\r\nBcc: victim@example.test")
        && !$mail->send('team@example.test', '', "Subject\r\nBcc: victim@example.test", '<p>Hi</p>', 'Hi');
    $check('an unsafe Reply-To address, Reply-To name or subject is refused before sending', $refused && count($requests()) === $before);
    $check('the refusal is logged without the address', str_contains($log(), 'invalid Reply-To') && !str_contains($log(), 'victim@'));

    echo "\nEnquiry alert: content\n";
    $values = ['name' => 'Ama Mensah', 'email' => 'ama@example.test', 'phone' => '+233 24 000 0000', 'message' => "Is it still available?\n<script>alert(1)</script>"];
    $c = EnquiryAlertService::content(42, 'Reservation request', '2019 Toyota Camry', $values, 1_800_000_000);
    $check('subject names the type, vehicle and customer, on one line', $c['subject'] === 'New reservation request: 2019 Toyota Camry from Ama Mensah');
    $details = array_column($c['details'], 1, 0);
    $check('details: type, vehicle, name, email, phone, time, reference', $details['Type'] === 'Reservation request' && $details['Vehicle'] === '2019 Toyota Camry'
        && $details['Name'] === 'Ama Mensah' && $details['Email'] === 'ama@example.test' && $details['Phone'] === '+233 24 000 0000'
        && $details['Received'] === '15 Jan 2027, 08:00 UTC' && $details['Reference'] === 'Enquiry #42');
    $check('the full message is included', in_array($values['message'], $c['paragraphs'], true));
    $rendered = EmailTemplate::render($c, ['name' => 'Next Gen Autos and Parts', 'logo' => null, 'site' => null, 'phones' => []]);
    $check('the customer\'s text is escaped in the HTML email', !str_contains($rendered['html'], '<script>') && str_contains($rendered['html'], '&lt;script&gt;'));
    $check('the plain-text version carries the message and details', str_contains($rendered['text'], 'Is it still available?') && str_contains($rendered['text'], 'Email: ama@example.test'));
    $general = EnquiryAlertService::content(7, 'Contact page enquiry', null, ['name' => 'Kofi', 'email' => 'k@example.test', 'phone' => null, 'message' => 'Do you ship to Kumasi?', 'topic' => 'shipping']);
    $gd = array_column($general['details'], 1, 0);
    $check('contact page: topic shown, no vehicle, phone "Not given"', $general['subject'] === 'New enquiry: Shipping & tracking from Kofi'
        && $gd['Topic'] === 'Shipping & tracking' && !isset($gd['Vehicle']) && $gd['Phone'] === 'Not given');
    $odd = EnquiryAlertService::content(8, 'Vehicle enquiry', "Car\r\nBcc: x", ['name' => "A\tB", 'email' => 'a@example.test', 'phone' => null, 'message' => 'Hello there']);
    $check('a subject can never contain a line break', preg_match('/[\r\n\t]/', $odd['subject']) === 0);

    echo "\nEnquiry alert: sending\n";
    $recording = new class extends EmailService {
        public array $sent = [];
        public bool $result = true;
        public bool $throw = false;
        public function sendTemplate(string $toEmail, string $toName, array $content, ?string $replyToEmail = null, string $replyToName = ''): bool
        {
            if ($this->throw) {
                throw new RuntimeException('simulated transport crash');
            }
            $this->sent[] = ['to' => $toEmail, 'subject' => $content['subject'], 'replyTo' => $replyToEmail, 'replyName' => $replyToName];

            return $this->result;
        }
    };
    $pdo = Database::connection();
    $settings = new SettingsRepository($pdo);
    // The setting is written only inside the rolled-back transactions below.
    $setAlertTo = static function (?string $value) use ($settings): void {
        $value === null ? $settings->remove('notification_email', 'company') : $settings->put('notification_email', 'company', $value);
    };
    $audit = new AuditLogRepository($pdo);
    $alertCount = static fn (string $event): int => (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE event_type = " . $pdo->quote($event) . " AND ip_address = '198.51.100.71'")->fetchColumn();

    $pdo->beginTransaction();
    try {
        $setAlertTo(null);
        $none = new EnquiryAlertService($settings, $recording, $audit);
        $check('no notification address: no email, no audit entry', $none->notify(1, 'Vehicle enquiry', 'Car', $values, '198.51.100.71', 'test') === 'no_recipient'
            && $recording->sent === [] && $alertCount('ENQUIRY_ALERT_SENT') + $alertCount('ENQUIRY_ALERT_FAILED') === 0);
        $setAlertTo("team@example.test\r\nBcc: x@example.test");
        $bad = new EnquiryAlertService($settings, $recording, $audit);
        $check('an unsafe stored address is ignored', $bad->notify(1, 'Vehicle enquiry', 'Car', $values, '198.51.100.71', 'test') === 'no_recipient' && $recording->sent === []);

        $setAlertTo('team@example.test');
        $alerts = new EnquiryAlertService($settings, $recording, $audit);
        $check('with an address: sent', $alerts->notify(5, 'Vehicle enquiry', '2019 Toyota Camry', $values, '198.51.100.71', 'test') === 'sent');
        $check('to the notification address, Reply-To the customer', ($recording->sent[0]['to'] ?? null) === 'team@example.test'
            && $recording->sent[0]['replyTo'] === 'ama@example.test' && $recording->sent[0]['replyName'] === 'Ama Mensah');
        $check('audited as ENQUIRY_ALERT_SENT, without addresses', $alertCount('ENQUIRY_ALERT_SENT') === 1
            && (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE ip_address = '198.51.100.71' AND (description LIKE '%@%')")->fetchColumn() === 0);

        $recording->result = false;
        $check('provider refusal: "failed", audited, no exception', $alerts->notify(6, 'Vehicle enquiry', 'Car', $values, '198.51.100.71', 'test') === 'failed' && $alertCount('ENQUIRY_ALERT_FAILED') === 1);
        $recording->throw = true;
        $threw = false;
        try {
            $result = $alerts->notify(7, 'Vehicle enquiry', 'Car', $values, '198.51.100.71', 'test');
        } catch (Throwable) {
            $threw = true;
        }
        $check('a crash in the transport never reaches the customer', !$threw && ($result ?? '') === 'failed' && $alertCount('ENQUIRY_ALERT_FAILED') === 2);
        $recording->throw = false;
        $recording->result = true;
    } finally {
        $pdo->rollBack();
    }

    echo "\nPublic enquiry form + alert\n";
    $_COOKIE[PublicFormToken::COOKIE] = bin2hex(random_bytes(16));
    $form = static fn (array $extra = []): array => $extra + ['name' => 'Zz Test Customer', 'email' => 'zz-mail-test@example.test', 'phone' => '', 'topic' => 'general',
        'message' => 'This is a synthetic test enquiry.', PublicEnquiryService::HONEYPOT => '', PublicFormToken::FIELD => PublicFormToken::issue('contact-page', time() - 10)];
    $recording->sent = [];
    $service = new PublicEnquiryService($pdo, new EnquiryRepository($pdo), $audit, new EnquiryAlertService($settings, $recording, $audit));

    $saved = static fn (): int => (int) $pdo->query("SELECT COUNT(*) FROM enquiries WHERE email = 'zz-mail-test@example.test'")->fetchColumn();
    $pdo->beginTransaction();   // even a regression here could not leave a row behind
    try {
        $setAlertTo('team@example.test');
        $check('honeypot filled: answered like success, nothing saved, no alert',
            $service->submit(null, false, 'general', $form([PublicEnquiryService::HONEYPOT => 'spam']), '198.51.100.72', 'test')['status'] === 'sent' && $saved() === 0 && $recording->sent === []);
        $check('invalid form: refused, nothing saved, no alert',
            $service->submit(null, false, 'general', $form(['email' => 'nope']), '198.51.100.72', 'test')['status'] === 'invalid' && $saved() === 0 && $recording->sent === []);
    } finally {
        $pdo->rollBack();
    }

    $pdo->beginTransaction();
    try {
        $setAlertTo('team@example.test');
        $customer = (int) $pdo->query('SELECT MIN(id) FROM customers')->fetchColumn();
        $res = $service->submit(null, false, 'general', $form(), '198.51.100.72', 'test', $customer > 0 ? $customer : null);
        $check('a saved enquiry inside an open (test) transaction sends no alert: it could still be rolled back', $res['status'] === 'sent' && $recording->sent === []);
        $row = $pdo->query("SELECT customer_id FROM enquiries WHERE email = 'zz-mail-test@example.test' ORDER BY id DESC LIMIT 1")->fetch();
        $check('regression: the signed-in customer\'s id is stored with the enquiry', $customer === 0 || (int) ($row['customer_id'] ?? 0) === $customer);
    } finally {
        $pdo->rollBack();
    }
    $check('rolled back: no test enquiry remains', (int) $pdo->query("SELECT COUNT(*) FROM enquiries WHERE email = 'zz-mail-test@example.test'")->fetchColumn() === 0);

    echo "\nLinks sent to people use the production domain\n";
    putenv('APP_URL=http://localhost:8000');
    $check('linkUrl() is the production domain even when APP_URL is localhost', AppConfig::linkUrl() === 'https://nextgenautosandparts.com' && AppConfig::linkUrl() === AppConfig::SITE_URL);
    $check('email logo and website link use the production domain', EmailService::brand()['site'] === 'https://nextgenautosandparts.com/');
    putenv('APP_URL');
    $users = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS)) as $f) {
        if (str_ends_with($f->getFilename(), '.php') && str_contains((string) file_get_contents($f->getPathname()), 'appUrl()')) {
            $users[] = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
        }
    }
    $check('APP_URL is used only for the Google sign-in callback (no email or message link is built from it)', $users === ['app/config/app.php']
        && substr_count((string) file_get_contents($root . '/app/config/app.php'), 'self::appUrl()') === 1, implode(', ', $users));
    foreach (['app/services/PasswordResetService.php' => "linkUrl() . '/admin/reset-password.php?token='", 'app/services/CustomerPasswordResetService.php' => "linkUrl() . '/reset-password/?t='",
        'app/services/CustomerVerificationService.php' => "\$site . '/verify-email/?t='", 'app/services/ShipmentEmail.php' => "\$site . '/track/'"] as $file => $needle) {
        $check('link route in ' . basename($file), str_contains((string) file_get_contents($root . '/' . $file), $needle));
    }

    echo "\nPrivate setting and public files\n";
    $check('the Settings form has the Notification email field (category company)', (ContactSettingsValidator::FIELDS['notification_email'] ?? null) === ['company', 'email', 'Notification email', []]
        && in_array('company', ContactSettingsValidator::CATEGORIES, true));
    $v = ContactSettingsValidator::validate(['notification_email' => 'not-an-address']);
    $check('the Notification email is validated', isset($v['errors']['notification_email']));
    $check('the public contact details never include it', !array_key_exists('notification_email', CompanyContactService::create()->details())
        && !str_contains((string) file_get_contents($root . '/app/services/CompanyContactService.php'), "'company'"));
    $ht = (string) file_get_contents($root . '/.htaccess');
    $check('.htaccess blocks hidden files (.env), *.log and error_log', str_contains($ht, 'RedirectMatch 404 (^|/)\.(?!well-known/)')
        && str_contains($ht, '\.(md|sql|log|lock|example)$') && str_contains($ht, 'RedirectMatch 404 (^|/)error_log$') && str_contains($ht, '^error_log$'));
    $example = (string) file_get_contents($root . '/.env.example');
    preg_match_all('/^(SMTP_USERNAME|SMTP_PASSWORD|BREVO_API_KEY|DB_PASSWORD|APP_KEY|GOOGLE_CLIENT_SECRET)=(.*)$/m', $example, $m);
    $check('.env.example: every secret is an empty placeholder', count($m[1]) === 6 && array_filter($m[2], static fn ($x) => trim($x) !== '') === []);
} finally {
    proc_terminate($server);
    proc_close($server);
    foreach (glob($stubDir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($stubDir);
}

echo "\nResult: $passed passed, $failed failed\n";
exit($failed > 0 ? 1 : 0);
