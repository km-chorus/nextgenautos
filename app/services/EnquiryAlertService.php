<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/repositories/SettingsRepository.php';
require_once dirname(__DIR__) . '/repositories/AuditLogRepository.php';
require_once dirname(__DIR__) . '/validation/EnquiryValidator.php';
require_once __DIR__ . '/EmailService.php';

/**
 * New-enquiry alert to the company (spec 15 §4, as decided by the owner on 2026-10-27: full details, because the admin
 * Enquiries inbox is not built yet). Sent AFTER the enquiry is saved and committed, so an email problem can never undo,
 * repeat or block the enquiry: the customer sees success either way.
 *
 * - Recipient: the "Notification email" setting (key notification_email, category company; never shown publicly).
 *   Empty or invalid = no alert.
 * - From: always the verified sender (MAIL_FROM_ADDRESS). Reply-To: the customer's email address, already validated by
 *   EnquiryValidator, so staff can answer by replying. The customer is never put in From.
 * - Everything the customer typed is escaped by EmailTemplate (HTML) and kept as plain text; nothing is a link.
 * - Audit: ENQUIRY_ALERT_SENT / ENQUIRY_ALERT_FAILED on the enquiry, without addresses or message text.
 *   Logs never contain the customer's details (EmailService logs a cleaned reason only).
 */
final class EnquiryAlertService
{
    public const SETTING_KEY = 'notification_email';
    public const SETTING_CATEGORY = 'company';

    public function __construct(private SettingsRepository $settings, private EmailService $email, private AuditLogRepository $audit)
    {
    }

    public static function create(): self
    {
        $pdo = Database::connection();

        return new self(new SettingsRepository($pdo), new EmailService(), new AuditLogRepository($pdo));
    }

    /** The validated alert address, or null when none is set. */
    public function recipient(): ?string
    {
        $stored = $this->settings->value(self::SETTING_KEY, self::SETTING_CATEGORY);

        return $stored !== null && EmailService::isSafeAddress(trim($stored)) ? trim($stored) : null;
    }

    /**
     * @param array{name:string,email:string,phone:?string,message:string,topic?:string} $values validated form values
     * @param string $label 'Vehicle enquiry' | 'Reservation request' | 'Contact page enquiry'
     * @return string 'sent' | 'failed' | 'no_recipient'. Never throws.
     */
    public function notify(int $enquiryId, string $label, ?string $vehicleTitle, array $values, string $ip, string $userAgent): string
    {
        try {
            $to = $this->recipient();
            if ($to === null) {
                return 'no_recipient';
            }
            $sent = $this->email->sendTemplate($to, '', self::content($enquiryId, $label, $vehicleTitle, $values), $values['email'], $values['name']);
        } catch (Throwable $e) {
            error_log('Enquiry alert failed (' . get_class($e) . ').');
            $sent = false;
        }
        try {
            $this->audit->record($sent ? 'ENQUIRY_ALERT_SENT' : 'ENQUIRY_ALERT_FAILED', null, 'enquiry', $enquiryId,
                $sent ? 'Enquiry alert emailed to the notification address' : 'Enquiry alert could not be emailed', $ip, $userAgent);
        } catch (Throwable $e) {
            error_log('Enquiry alert: could not record the result (' . get_class($e) . ').');
        }

        return $sent ? 'sent' : 'failed';
    }

    /**
     * The alert in the branded layout (EmailTemplate keys). Public for the tests.
     *
     * @param array{name:string,email:string,phone:?string,message:string,topic?:string} $values
     * @return array<string,mixed>
     */
    public static function content(int $enquiryId, string $label, ?string $vehicleTitle, array $values, ?int $now = null): array
    {
        $topic = isset($values['topic']) ? (EnquiryValidator::TOPICS[$values['topic']] ?? null) : null;
        $about = $vehicleTitle ?? $topic;
        // The subject is one line: the name and title cannot contain line breaks (validated), but are flattened anyway.
        $flat = static fn (string $s): string => trim(preg_replace('/[\p{Cc}\s]+/u', ' ', $s) ?? '');
        $subject = 'New ' . lcfirst($label === 'Contact page enquiry' ? 'enquiry' : $label) . ($about !== null ? ': ' . $flat($about) : '') . ' from ' . $flat($values['name']);

        $details = [['Type', $label]];
        if ($vehicleTitle !== null) {
            $details[] = ['Vehicle', $vehicleTitle];
        }
        if ($topic !== null) {
            $details[] = ['Topic', $topic];
        }
        $details[] = ['Name', $values['name']];
        $details[] = ['Email', $values['email']];
        $details[] = ['Phone', $values['phone'] ?? 'Not given'];
        $details[] = ['Received', gmdate('j M Y, H:i', $now ?? time()) . ' UTC'];
        $details[] = ['Reference', 'Enquiry #' . $enquiryId];

        return [
            'subject'    => mb_substr($subject, 0, 180),
            'preheader'  => $label . ' from ' . $flat($values['name']) . ($about !== null ? ' about ' . $flat($about) : ''),
            'eyebrow'    => 'New enquiry',
            'title'      => $label,
            'paragraphs' => ['A customer sent this through the website. Their message:', $values['message']],
            'details'    => $details,
            'note'       => "Reply to this email to answer the customer: your reply goes to " . $values['email'] . ".\nThis is the customer's own text: check before opening any address it mentions.",
            'reason'     => 'You received this email because this address is set as the Notification email in Admin > Settings.',
        ];
    }
}
