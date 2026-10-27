<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/repositories/EnquiryRepository.php';
require_once dirname(__DIR__) . '/repositories/AuditLogRepository.php';
require_once dirname(__DIR__) . '/validation/EnquiryValidator.php';
require_once __DIR__ . '/PublicFormToken.php';
require_once __DIR__ . '/Transaction.php';
require_once __DIR__ . '/EnquiryAlertService.php';

/**
 * Public vehicle enquiries and reservation requests (spec 14). Order of checks:
 *   form token (session-free CSRF) -> honeypot -> validation -> minimum fill time -> rate limits -> reserved rule -> save.
 *
 * - "contact" is saved as enquiry_type 'vehicle'; "reserve" as 'purchase' (shown in the admin as "Reservation request").
 * - A reservation request NEVER changes the vehicle's status: an administrator confirms it (spec 15).
 * - A reservation for a vehicle that is already reserved is refused on the server, whatever the browser sent.
 * - Rate limits: 5 submissions per IP and 3 per email address per hour (the IP count uses the audit log, the email
 *   count the enquiries table). A burst is logged once (ENQUIRY_RATE_LIMITED).
 * - A filled honeypot is answered exactly like a success, but nothing is stored (a bot learns nothing).
 * The vehicle id comes from the server (the page's slug lookup), never from the form.
 * - After the enquiry is saved and committed, the company is emailed an alert (EnquiryAlertService, spec 15 §4). Its
 *   result never changes the outcome: an email problem is logged and audited, and the customer still sees success.
 */
final class PublicEnquiryService
{
    public const HONEYPOT = 'website';
    public const PER_IP_PER_HOUR = 5;
    public const PER_EMAIL_PER_HOUR = 3;
    private const TYPES = ['contact' => 'vehicle', 'reserve' => 'purchase', 'general' => null];   // 'general': the type is the chosen topic

    public function __construct(private PDO $pdo, private EnquiryRepository $enquiries, private AuditLogRepository $audit, private ?EnquiryAlertService $alerts = null)
    {
    }

    public static function create(): self
    {
        $pdo = Database::connection();

        return new self($pdo, new EnquiryRepository($pdo), new AuditLogRepository($pdo),
            new EnquiryAlertService(new SettingsRepository($pdo), new EmailService(), new AuditLogRepository($pdo)));
    }

    /**
     * @param array<string,mixed> $post the raw form (untrusted)
     * @return array{status:string,values:array<string,mixed>,errors:array<string,string>}
     *         status: sent | invalid (field errors) | expired (token) | retry (sent too quickly) | throttled | reserved
     */
    public function submit(?int $vehicleId, bool $vehicleReserved, string $kind, array $post, string $ip, string $userAgent, ?int $customerId = null, ?string $vehicleTitle = null): array
    {
        if (!array_key_exists($kind, self::TYPES) || ($kind === 'general') !== ($vehicleId === null)) {
            return ['status' => 'expired', 'values' => [], 'errors' => []];
        }
        $token = PublicFormToken::check($post[PublicFormToken::FIELD] ?? null, self::tokenPurpose($kind));
        $checked = EnquiryValidator::validate($kind, $post);
        $values = $checked['values'];
        if ($token === 'invalid') {
            return ['status' => 'expired', 'values' => $values, 'errors' => []];
        }
        $honeypot = $post[self::HONEYPOT] ?? '';
        if (!is_string($honeypot) || $honeypot !== '') {
            return ['status' => 'sent', 'values' => [], 'errors' => []];   // looks like success; nothing stored
        }
        if ($checked['errors'] !== []) {
            return ['status' => 'invalid', 'values' => $values, 'errors' => $checked['errors']];
        }
        if ($token === 'too_fast') {
            // Sent within seconds of the page loading (a bot, or a person using autofill): keep what they typed and ask them to press Send again.
            return ['status' => 'retry', 'values' => $values, 'errors' => []];
        }

        if ($this->audit->countRecent('ip', $ip, ['ENQUIRY_SUBMITTED'], 60) >= self::PER_IP_PER_HOUR
            || $this->enquiries->countRecentByEmail($values['email'], 60) >= self::PER_EMAIL_PER_HOUR) {
            if (!$this->audit->recentEventExists('ENQUIRY_RATE_LIMITED', $ip, 60)) {
                $this->audit->record('ENQUIRY_RATE_LIMITED', null, $vehicleId === null ? null : 'vehicle', $vehicleId, 'Public enquiry rate limit reached', $ip, $userAgent);
            }

            return ['status' => 'throttled', 'values' => $values, 'errors' => []];
        }
        if ($kind === 'reserve' && $vehicleReserved) {
            return ['status' => 'reserved', 'values' => $values, 'errors' => []];
        }

        $type = self::TYPES[$kind] ?? $values['topic'];   // the topic was checked against EnquiryValidator::TOPICS
        $label = ['contact' => 'Vehicle enquiry', 'reserve' => 'Reservation request', 'general' => 'Contact page enquiry'][$kind];
        $id = Transaction::run($this->pdo, function () use ($vehicleId, $type, $label, $values, $ip, $userAgent, $customerId): int {
            $id = $this->enquiries->insert($vehicleId, $type, $values, $customerId);
            $this->audit->record('ENQUIRY_SUBMITTED', null, 'enquiry', $id, $label, $ip, $userAgent);

            return $id;
        });
        // Outside the transaction, after the commit: the alert can neither undo nor repeat the saved enquiry.
        if ($this->alerts !== null && !$this->pdo->inTransaction()) {
            $this->alerts->notify($id, $label, $vehicleId === null ? null : $vehicleTitle, $values, $ip, $userAgent);
        }

        return ['status' => 'sent', 'values' => [], 'errors' => []];
    }

    /** Each form's token is valid for that form only (a vehicle-page token cannot be replayed on the Contact page). */
    public static function tokenPurpose(string $kind): string
    {
        return $kind === 'general' ? 'contact-page' : 'vehicle-' . $kind;
    }
}
