<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/helpers/html.php';

/**
 * The only place that talks to the email provider (Brevo transactional API).
 * Nothing else in the application calls Brevo.
 *
 * Credentials come from the environment (BREVO_API_KEY, MAIL_FROM_EMAIL, optional
 * MAIL_FROM_NAME) and never reach the browser, the database or Git. When they are
 * not configured, isConfigured() is false and callers must say so honestly: this
 * class never pretends a message was sent. Failures are logged WITHOUT the message
 * body (it contains a reset link) and without the API key.
 */
class EmailService
{
    private const TIMEOUT_SECONDS = 10;

    /** True only when a real message could be handed to Brevo from this environment. */
    public static function isConfigured(): bool
    {
        return AppConfig::brevoApiKey() !== ''
            && filter_var(AppConfig::mailFromEmail(), FILTER_VALIDATE_EMAIL) !== false
            && function_exists('curl_init');
    }

    /**
     * Password reset email. The link appears once (as the button/link target and as
     * a plain-text fallback); the token is never repeated separately and no password
     * is ever included. Returns true only if Brevo accepted the message.
     */
    public function sendPasswordReset(string $toEmail, string $toName, string $resetUrl, int $validMinutes): bool
    {
        $brand = AppConfig::mailFromName();
        $subject = 'Reset your ' . $brand . ' admin password';

        $text = "Hello {$toName},\n\n"
            . "We received a request to reset the password for your {$brand} administrator account.\n\n"
            . "To choose a new password, open this link:\n{$resetUrl}\n\n"
            . "This link can be used once and expires in {$validMinutes} minutes.\n\n"
            . "If you did not request this, you can ignore this email: your password will not change. "
            . "If you are concerned, contact the person who manages the website.\n\n"
            . "{$brand}\n";

        $html = '<!DOCTYPE html><html><body style="margin:0;padding:24px;background:#F8F7F4;font-family:Arial,Helvetica,sans-serif;color:#111315;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">'
            . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FFFFFF;border:1px solid #E3E3DF;">'
            . '<tr><td style="background:#111315;color:#FFFFFF;padding:20px 28px;font-size:18px;font-weight:bold;">' . e($brand) . '</td></tr>'
            . '<tr><td style="padding:28px;font-size:15px;line-height:1.6;">'
            . '<p style="margin:0 0 16px;">Hello ' . e($toName) . ',</p>'
            . '<p style="margin:0 0 16px;">We received a request to reset the password for your ' . e($brand) . ' administrator account.</p>'
            . '<p style="margin:0 0 24px;"><a href="' . e($resetUrl) . '" style="display:inline-block;background:#9E2F2F;color:#FFFFFF;text-decoration:none;padding:12px 24px;font-weight:bold;">Choose a new password</a></p>'
            . '<p style="margin:0 0 16px;color:#62666A;">This link can be used once and expires in ' . $validMinutes . ' minutes.</p>'
            . '<p style="margin:0;color:#62666A;">If you did not request this, you can ignore this email: your password will not change. '
            . 'If you are concerned, contact the person who manages the website.</p>'
            . '</td></tr></table></td></tr></table></body></html>';

        return $this->send($toEmail, $toName, $subject, $html, $text);
    }

    private function send(string $toEmail, string $toName, string $subject, string $html, string $text): bool
    {
        if (!self::isConfigured()) {
            error_log('EmailService: not configured; message not sent.');
            return false;
        }

        $payload = json_encode([
            'sender'      => ['name' => AppConfig::mailFromName(), 'email' => AppConfig::mailFromEmail()],
            'to'          => [['email' => $toEmail, 'name' => $toName]],
            'subject'     => $subject,
            'htmlContent' => $html,
            'textContent' => $text,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

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
}
