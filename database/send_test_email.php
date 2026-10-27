<?php

declare(strict_types=1);

/**
 * Email delivery check for the Brevo SMTP integration. COMMAND LINE ONLY: over the web it answers 404 and does nothing.
 *
 *   php database/send_test_email.php --check
 *       Shows the mail settings (no secrets), then connects to the SMTP server, upgrades to STARTTLS and logs in,
 *       and disconnects. NO email is sent.
 *
 *   php database/send_test_email.php you@example.com
 *       Sends ONE short test message, in the branded layout, to that address only (use an address you control).
 *
 *   php database/send_test_email.php --preview
 *       Writes the test email and a sample password-reset email as HTML files to look at in a browser. Sends nothing.
 *
 * Everything goes through EmailService, exactly as the website sends mail. Passwords and keys are never printed.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/services/EmailService.php';

$arg = $argv[1] ?? '';

/** The test message in the branded layout (EmailTemplate). */
$testContent = static function (): array {
    $brand = AppConfig::mailFromName();

    return [
        'subject'    => 'Test email from ' . $brand,
        'preheader'  => 'Email delivery from the website is working.',
        'eyebrow'    => 'Test message',
        'title'      => 'Email delivery is working',
        'greeting'   => 'Hello,',
        'paragraphs' => ['This is a test email from the ' . $brand . ' website. If you can read it, messages sent through Brevo are reaching your inbox.',
            'This panel shows how details look in emails from the website:'],
        'details'    => [['Sent', gmdate('j M Y, H:i') . ' UTC'], ['Transport', 'Brevo SMTP (STARTTLS)'], ['Message type', 'Test only']],
        'note'       => 'No action is needed. This message was sent by the website administrator to check email delivery.',
        'reason'     => 'You received this email because the website administrator sent a test message to this address.',
    ];
};

if ($arg === '--preview') {
    $dir = sys_get_temp_dir();
    $files = [
        'test'  => [$testContent(), $dir . DIRECTORY_SEPARATOR . 'email-preview-test.html'],
        'reset' => [[
            'subject' => 'Reset your ' . AppConfig::mailFromName() . ' admin password', 'preheader' => 'Use this link within 30 minutes to choose a new password.',
            'eyebrow' => 'Account security', 'title' => 'Reset your password', 'greeting' => 'Hello Ama,',
            'paragraphs' => ['We received a request to reset the password for your ' . AppConfig::mailFromName() . ' administrator account.'],
            'button' => ['label' => 'Choose a new password', 'url' => 'https://example.test/admin/reset-password.php?token=PREVIEW-ONLY'],
            'note' => "This link can be used once and expires in 30 minutes.
If you did not request this, you can ignore this email: your password will not change.",
            'reason' => 'You received this email because a password reset was requested for your administrator account.',
        ], $dir . DIRECTORY_SEPARATOR . 'email-preview-reset.html'],
    ];
    foreach ($files as $name => [$content, $path]) {
        file_put_contents($path, EmailTemplate::render($content, EmailService::brand())['html']);
        echo 'Preview (' . $name . '): ' . $path . "
";
    }
    echo "Open these files in a browser. Nothing was sent. (The reset link in the preview is a dummy.)
";
    exit(0);
}
if ($arg === '' || $arg === '--help') {
    fwrite(STDERR, "Usage:\n  php database/send_test_email.php --check              (log in to the SMTP server, send nothing)\n  php database/send_test_email.php you@example.com     (send one test email to that address)\n  php database/send_test_email.php --preview           (write the email designs to HTML files, send nothing)\n");
    exit(1);
}

$masked = static function (string $value): string {
    if ($value === '') {
        return '(not set)';
    }
    $at = strpos($value, '@');

    return $at === false ? substr($value, 0, 2) . str_repeat('*', 6) : substr($value, 0, 2) . str_repeat('*', 4) . substr($value, $at);
};

echo "Mail settings (secrets are never shown)\n";
echo '  Transport:   ' . AppConfig::mailMailer() . "\n";
if (AppConfig::mailMailer() === 'smtp') {
    echo '  SMTP server: ' . AppConfig::smtpHost() . ':' . AppConfig::smtpPort() . ' (' . (AppConfig::smtpEncryption() === 'tls' ? 'STARTTLS' : AppConfig::smtpEncryption()) . ")\n";
    echo '  SMTP login:  ' . $masked(AppConfig::smtpUsername()) . ', password ' . (AppConfig::smtpPassword() === '' ? 'NOT set' : 'set') . "\n";
}
echo '  Sender:      ' . AppConfig::mailFromName() . ' <' . AppConfig::mailFromEmail() . ">\n\n";

$problem = EmailService::configProblem();
if ($problem !== null) {
    fwrite(STDERR, "Not configured: $problem\n");
    exit(1);
}

$service = new EmailService();

if ($arg === '--check') {
    if (AppConfig::mailMailer() !== 'smtp') {
        echo "Configuration complete. The connection check applies to MAIL_MAILER=smtp only.\n";
        exit(0);
    }
    echo "Connecting, starting TLS and logging in (no email is sent)...\n";
    $error = $service->verifyConnection();
    if ($error !== null) {
        fwrite(STDERR, "FAILED: $error\n");
        if (stripos($error, 'Unauthorized IP') !== false) {
            fwrite(STDERR, "\nBrevo refused this computer's internet (IP) address. In Brevo open Settings > Security > Authorized IPs and\n"
                . "either authorize this IP address (Brevo also emails you a link to do it) or turn IP blocking off.\n"
                . "The web server's IP address must be authorized too before the live site can send email.\n");
        } elseif (stripos($error, '535') !== false || stripos($error, 'authenticate') !== false) {
            fwrite(STDERR, "\nCheck SMTP_USERNAME (the SMTP login ending in @smtp-brevo.com) and SMTP_PASSWORD (an SMTP key, not an\n"
                . "API key) in Brevo > SMTP & API > SMTP.\n");
        }
        exit(1);
    }
    echo "OK: the SMTP server accepted the connection and the login. No email was sent.\n";
    exit(0);
}

if (filter_var($arg, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "That is not a valid email address.\n");
    exit(1);
}

echo "Sending one test email to $arg ...\n";
if ($service->sendTemplate($arg, '', $testContent())) {
    echo "Accepted by the mail server. Check the inbox (and the spam folder) of $arg.\n";
    exit(0);
}
fwrite(STDERR, "FAILED: the message was not accepted. The reason is in the PHP error log (no secrets are logged).\n");
exit(1);
