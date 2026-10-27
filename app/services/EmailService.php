<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/helpers/html.php';
require_once __DIR__ . '/EmailTemplate.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/**
 * The only place that sends email. Nothing else in the application talks to Brevo.
 *
 * Transport (MAIL_MAILER):
 *   smtp      (default) Brevo SMTP relay through PHPMailer: SMTP_HOST (smtp-relay.brevo.com), SMTP_PORT (587),
 *             SMTP_ENCRYPTION (tls = STARTTLS, the only accepted value), SMTP_USERNAME, SMTP_PASSWORD.
 *   brevo_api Brevo HTTP API with BREVO_API_KEY (the original transport, kept as an option).
 * Sender: MAIL_FROM_ADDRESS (a sender verified in Brevo; MAIL_FROM_EMAIL also accepted) and MAIL_FROM_NAME.
 *
 * Credentials come from the environment only and never reach the browser, the database, the logs or Git. When the
 * configuration is incomplete, isConfigured() is false and callers must say so honestly: this class never pretends a
 * message was sent. Every failure returns false; nothing is thrown to the caller. Logged failures contain a short,
 * cleaned reason only: no message body (it may contain a reset link), no password, no username, no email address.
 */
class EmailService
{
    private const TIMEOUT_SECONDS = 10;

    /** The SMTP server's last error reply in this attempt (cleaned), for a useful failure reason. */
    private ?string $lastServerError = null;

    /** True only when a real message could be handed to the provider from this environment. */
    public static function isConfigured(): bool
    {
        return self::configProblem() === null;
    }

    /**
     * Why email cannot be sent from this environment, in plain words without any secret (for the CLI test script and
     * the logs), or null when the configuration is complete.
     */
    public static function configProblem(): ?string
    {
        if (filter_var(AppConfig::mailFromEmail(), FILTER_VALIDATE_EMAIL) === false) {
            return 'MAIL_FROM_ADDRESS is missing or is not a valid email address.';
        }
        if (AppConfig::mailMailer() === 'brevo_api') {
            if (AppConfig::brevoApiKey() === '') {
                return 'BREVO_API_KEY is not set (MAIL_MAILER=brevo_api).';
            }

            return function_exists('curl_init') ? null : 'The PHP curl extension is not enabled.';
        }
        if (!class_exists(PHPMailer::class)) {
            return 'PHPMailer is not installed (run composer install).';
        }
        if (trim(AppConfig::smtpHost()) === '') {
            return 'SMTP_HOST is not set.';
        }
        if (AppConfig::smtpEncryption() !== 'tls') {
            return 'SMTP_ENCRYPTION must be "tls" (STARTTLS on port 587).';
        }
        if (trim(AppConfig::smtpUsername()) === '' || trim(AppConfig::smtpPassword()) === '') {
            return 'SMTP_USERNAME and SMTP_PASSWORD must both be set.';
        }
        if (!extension_loaded('openssl')) {
            return 'The PHP openssl extension is not enabled (needed for STARTTLS).';
        }

        return null;
    }

    /**
     * Password reset email, in the branded layout. The link appears once (as the button and as a plain-text fallback);
     * the token is never repeated separately and no password is ever included. Returns true only if the provider
     * accepted the message.
     */
    public function sendPasswordReset(string $toEmail, string $toName, string $resetUrl, int $validMinutes): bool
    {
        $brand = AppConfig::mailFromName();

        return $this->sendTemplate($toEmail, $toName, [
            'subject'    => 'Reset your ' . $brand . ' admin password',
            'preheader'  => 'Use this link within ' . $validMinutes . ' minutes to choose a new password.',
            'eyebrow'    => 'Account security',
            'title'      => 'Reset your password',
            'greeting'   => 'Hello ' . $toName . ',',
            'paragraphs' => ['We received a request to reset the password for your ' . $brand . ' administrator account.'],
            'button'     => ['label' => 'Choose a new password', 'url' => $resetUrl],
            'note'       => 'This link can be used once and expires in ' . $validMinutes . " minutes.\nIf you did not request this, you can ignore this email: your password will not change. If you are concerned, contact the person who manages the website.",
            'reason'     => 'You received this email because a password reset was requested for your administrator account on the ' . $brand . ' website.',
        ]);
    }

    /**
     * Sends a message in the branded layout (EmailTemplate). $content uses EmailTemplate's keys (subject, preheader,
     * eyebrow, title, greeting, paragraphs, details, button, note, reason). Returns true only if it was accepted.
     */
    public function sendTemplate(string $toEmail, string $toName, array $content, ?string $replyToEmail = null, string $replyToName = ''): bool
    {
        $rendered = EmailTemplate::render($content, self::brand());

        return $this->send($toEmail, $toName, (string) $content['subject'], $rendered['html'], $rendered['text'], $replyToEmail, $replyToName);
    }

    /**
     * Brand details for the layout: the sender name, the logo and website link on the production domain
     * (AppConfig::linkUrl(), never localhost), and the company phone numbers from the admin Settings.
     *
     * @return array{name:string,logo:?string,site:?string,phones:list<string>}
     */
    public static function brand(): array
    {
        $public = AppConfig::linkUrl();   // always the production domain (2026-10-27), never localhost
        $phones = [];
        try {
            require_once __DIR__ . '/CompanyContactService.php';
            $phones = array_column(CompanyContactService::create()->phones(), 'display');
        } catch (Throwable $e) {
            error_log('EmailService: company phones unavailable for the email footer (' . get_class($e) . ').');
        }

        return [
            'name'   => AppConfig::mailFromName(),
            'logo'   => $public === null ? null : $public . '/assets/images/email/logo.png',
            'site'   => $public === null ? null : $public . '/',
            'phones' => $phones,
        ];
    }

    /**
     * Sends one transactional message (HTML with a plain-text alternative). Returns true only when the provider
     * accepted it. Invalid input (a bad address, line breaks in the name or subject) is refused before anything is sent.
     *
     * Reply-To (optional): an address answers go to, e.g. the customer who sent an enquiry. The From address is ALWAYS the
     * verified sender (MAIL_FROM_ADDRESS); a customer is never put in From. An invalid Reply-To address or name (line
     * breaks, not an email address) is refused the same way: nothing is sent.
     */
    public function send(string $toEmail, string $toName, string $subject, string $html, string $text, ?string $replyToEmail = null, string $replyToName = ''): bool
    {
        $problem = self::configProblem();
        if ($problem !== null) {
            error_log('EmailService: not configured; message not sent. ' . $problem);

            return false;
        }
        if (filter_var($toEmail, FILTER_VALIDATE_EMAIL) === false || preg_match('/[\r\n]/', $toName . $subject) === 1 || trim($subject) === '') {
            error_log('EmailService: message refused (invalid recipient, name or subject); nothing sent.');

            return false;
        }
        $replyTo = null;
        if ($replyToEmail !== null) {
            if (!self::isSafeAddress($replyToEmail) || preg_match('/[\r\n]/', $replyToName) === 1) {
                error_log('EmailService: message refused (invalid Reply-To address or name); nothing sent.');

                return false;
            }
            $replyTo = ['email' => $replyToEmail, 'name' => $replyToName];
        }

        return AppConfig::mailMailer() === 'brevo_api'
            ? $this->sendBrevoApi($toEmail, $toName, $subject, $html, $text, $replyTo)
            : $this->sendSmtp($toEmail, $toName, $subject, $html, $text, $replyTo);
    }

    /** A single, valid email address with nothing that could add a header or a second recipient. */
    public static function isSafeAddress(string $email): bool
    {
        return strlen($email) <= 255 && preg_match('/[\r\n,;<>"\s]/', $email) !== 1 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Opens the SMTP connection, upgrades it with STARTTLS and logs in, then disconnects WITHOUT sending a message.
     * For the CLI check only. Returns null on success, or a short reason without any secret.
     */
    public function verifyConnection(): ?string
    {
        $problem = self::configProblem();
        if ($problem !== null) {
            return $problem;
        }
        if (AppConfig::mailMailer() !== 'smtp') {
            return 'The connection check applies to MAIL_MAILER=smtp only.';
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(self::TIMEOUT_SECONDS * 6);
        }
        $reason = 'Could not connect or log in.';
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $mail = $this->smtpMailer();
            try {
                if ($mail->smtpConnect()) {
                    return null;
                }
                $reason = $this->reason($mail) ?: $reason;
            } catch (Throwable $e) {
                $reason = $this->reason($mail, $e->getMessage()) ?: $reason;
            } finally {
                $mail->smtpClose();
            }
            if ($this->lastServerError !== null) {
                break;   // the server answered with a refusal: retrying would not help
            }
        }

        return $reason;
    }

    /**
     * Sends through SMTP. The request gets a fresh time allowance first, so a slow mail server can never stop the page with
     * PHP's time limit (every wait is also capped at TIMEOUT_SECONDS). One retry, only when the failure happened while
     * connecting or logging in with no reply from the server (a network stall): the message had not been handed over,
     * so a retry can never send it twice.
     */
    private function sendSmtp(string $toEmail, string $toName, string $subject, string $html, string $text, ?array $replyTo = null): bool
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(self::TIMEOUT_SECONDS * 6);
        }
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $result = $this->sendSmtpOnce($toEmail, $toName, $subject, $html, $text, $replyTo);
            if ($result !== 'retry') {
                return $result === 'sent';
            }
            if ($attempt === 1) {
                error_log('EmailService: SMTP connection stalled or dropped before the message was handed over; retrying once.');
            }
        }
        error_log('EmailService: SMTP send failed. Could not connect to the mail server or log in (no reply) after 2 attempts; nothing was sent.');

        return false;
    }

    /** 'sent', 'failed', or 'retry' (connection or login stalled with no server reply: nothing was handed over). */
    private function sendSmtpOnce(string $toEmail, string $toName, string $subject, string $html, string $text, ?array $replyTo = null): string
    {
        $mail = $this->smtpMailer();
        try {
            $mail->setFrom(AppConfig::mailFromEmail(), AppConfig::mailFromName(), false);   // always the verified sender
            $mail->addAddress($toEmail, $toName);
            if ($replyTo !== null) {
                $mail->addReplyTo($replyTo['email'], $replyTo['name']);
            }
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $html;
            $mail->AltBody = $text;
            $mail->send();

            return 'sent';
        } catch (MailerException $e) {
            $summary = (string) $mail->ErrorInfo !== '' ? (string) $mail->ErrorInfo : $e->getMessage();
            if ($this->lastServerError === null && preg_match('/connect|authenticate/i', $summary) === 1) {
                return 'retry';
            }
            error_log('EmailService: SMTP send failed. ' . $this->reason($mail, $e->getMessage()));
        } catch (Throwable $e) {
            error_log('EmailService: SMTP send failed (' . get_class($e) . ').');
        } finally {
            $mail->smtpClose();
        }

        return 'failed';
    }

    /** A PHPMailer object for the Brevo SMTP relay: STARTTLS required, certificates verified, no debug output. */
    private function smtpMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = AppConfig::smtpHost();
        $mail->Port = AppConfig::smtpPort();
        $mail->SMTPAuth = true;
        $mail->Username = AppConfig::smtpUsername();
        $mail->Password = AppConfig::smtpPassword();
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;   // refuse to log in over an unencrypted connection
        $mail->SMTPAutoTLS = true;
        $mail->Timeout = self::TIMEOUT_SECONDS;                 // connecting
        $mail->getSMTPInstance()->Timelimit = self::TIMEOUT_SECONDS;   // waiting for each server reply (PHPMailer's default is 300 s)
        // The conversation is never printed or logged. Only the server's last error reply (4xx / 5xx, e.g. "525 5.7.1
        // Unauthorized IP address") is kept, because PHPMailer's own message hides it; client lines carry the encoded
        // login and are discarded immediately.
        $this->lastServerError = null;
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = function (string $line): void {
            if (preg_match('/^SERVER -> CLIENT:\s*([45]\d\d[ -].*)$/s', trim($line), $m) === 1) {
                $this->lastServerError = self::clean($m[1]);
            }
        };
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->XMailer = ' ';                                 // do not advertise the library and version
        // SMTPOptions are left at PHP's defaults: the server certificate is always verified.

        return $mail;
    }

    private function sendBrevoApi(string $toEmail, string $toName, string $subject, string $html, string $text, ?array $replyTo = null): bool
    {
        $message = [
            'sender'      => ['name' => AppConfig::mailFromName(), 'email' => AppConfig::mailFromEmail()],
            'to'          => [['email' => $toEmail, 'name' => $toName]],
            'subject'     => $subject,
            'htmlContent' => $html,
            'textContent' => $text,
        ];
        if ($replyTo !== null) {
            $message['replyTo'] = $replyTo['name'] !== '' ? $replyTo : ['email' => $replyTo['email']];
        }
        $payload = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init(AppConfig::brevoApiBase() . '/v3/smtp/email');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true, // never disabled
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'content-type: application/json',
                'api-key: ' . AppConfig::brevoApiKey(),
            ],
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno !== 0 || $status < 200 || $status >= 300) {
            // Status and curl error number only: no body, no headers, no recipient, no key.
            error_log('EmailService: Brevo send failed (HTTP ' . $status . ', curl error ' . $errno . ').');

            return false;
        }

        return $response !== false;
    }

    /**
     * Why an SMTP attempt failed: PHPMailer's summary plus the server's own last reply when there is one (for example
     * "525 5.7.1 Unauthorized IP address", which PHPMailer alone reports only as "Could not authenticate"), cleaned.
     */
    private function reason(PHPMailer $mail, string $fallback = ''): string
    {
        $summary = (string) $mail->ErrorInfo !== '' ? (string) $mail->ErrorInfo : $fallback;
        $reply = $this->lastServerError;
        if ($reply !== null && $reply !== '' && !str_contains($summary, $reply)) {
            $summary .= ' Server reply: ' . $reply;
        }

        return self::clean($summary);
    }

    /**
     * A provider message made safe for a log line: one line, at most 200 characters, with the SMTP password, the
     * username, the API key and every email address removed.
     */
    public static function clean(string $message): string
    {
        foreach ([AppConfig::smtpPassword(), AppConfig::smtpUsername(), AppConfig::brevoApiKey()] as $secret) {
            if ($secret !== '') {
                $message = str_replace($secret, '[hidden]', $message);
            }
        }
        $message = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[address]', $message) ?? '';
        $message = trim(preg_replace('/\s+/', ' ', $message) ?? '');

        return mb_substr($message, 0, 200);
    }
}
