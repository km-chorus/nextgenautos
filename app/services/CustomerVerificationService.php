<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/repositories/AuditLogRepository.php';
require_once __DIR__ . '/SecretBox.php';
require_once __DIR__ . '/EmailService.php';
require_once __DIR__ . '/Transaction.php';

/**
 * Customer email verification (spec 22, owner decision 2026-10-21). Password sign-ups never proved they own their email,
 * yet shipments are linked to an account by email: so only VERIFIED accounts are linked to shipments and see them.
 * Google sign-ins are verified by Google.
 *
 * The link carries "<customer id>.<expiry>.<HMAC(APP_KEY-derived key, id|email|expiry)>": nothing is stored, the link
 * stops working after 48 hours, and it is tied to the email it was sent to (a changed email invalidates it).
 * Confirming links the customer's waiting shipments (recorded with that email, not yet linked to any account).
 */
class CustomerVerificationService
{
    public const LIFETIME_SECONDS = 172800;      // 48 hours
    public const MAX_SENDS_PER_HOUR = 3;

    public const SENT = 'sent';
    public const FAILED = 'failed';
    public const THROTTLED = 'throttled';
    public const ALREADY = 'already';
    public const UNAVAILABLE = 'unavailable';    // no APP_KEY or APP_URL: a link cannot be made

    public function __construct(private PDO $pdo, private AuditLogRepository $audit, private EmailService $email)
    {
    }

    public static function create(): self
    {
        $pdo = Database::connection();

        return new self($pdo, new AuditLogRepository($pdo), new EmailService());
    }

    public static function token(int $customerId, string $email, ?int $expires = null): ?string
    {
        if (AppConfig::appKey() === null) {
            return null;
        }
        $expires ??= time() + self::LIFETIME_SECONDS;

        return $customerId . '.' . $expires . '.' . hash_hmac('sha256', $customerId . '|' . strtolower($email) . '|' . $expires, SecretBox::derive('customer-email-verify'));
    }

    /** Sends (or re-sends) the confirmation email. A SENT | FAILED | THROTTLED | ALREADY | UNAVAILABLE code. */
    public function send(int $customerId, string $ip, string $userAgent): string
    {
        $customer = $this->customer($customerId);
        if ($customer === null) {
            return self::FAILED;
        }
        if ($customer['email_verified_at'] !== null) {
            return self::ALREADY;
        }
        $site = AppConfig::linkUrl();
        $token = self::token($customerId, (string) $customer['email']);
        if ($token === null) {
            return self::UNAVAILABLE;
        }
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE event_type = 'CUSTOMER_VERIFICATION_SENT' AND entity_type = 'customer' AND entity_id = ?
            AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 60 MINUTE)");
        $stmt->execute([$customerId]);
        if ((int) $stmt->fetchColumn() >= self::MAX_SENDS_PER_HOUR) {
            return self::THROTTLED;
        }
        $brand = AppConfig::mailFromName();
        $sent = $this->email->sendTemplate((string) $customer['email'], (string) $customer['name'], [
            'subject'    => 'Confirm your email address for ' . $brand,
            'preheader'  => 'One click to confirm your email and see your shipments.',
            'eyebrow'    => 'Your account',
            'title'      => 'Confirm your email address',
            'greeting'   => 'Hello ' . $customer['name'] . ',',
            'paragraphs' => ['Thank you for creating an account on the ' . $brand . ' website. Please confirm that this is your email address.',
                'Once it is confirmed, the shipments our team records for you appear in your account.'],
            'button'     => ['label' => 'Confirm my email', 'url' => $site . '/verify-email/?t=' . rawurlencode($token)],
            'note'       => 'This link works for 48 hours. If you did not create an account, you can ignore this email.',
            'reason'     => 'You received this email because an account was created with this address on the ' . $brand . ' website.',
        ]);
        $this->audit->record($sent ? 'CUSTOMER_VERIFICATION_SENT' : 'CUSTOMER_VERIFICATION_FAILED', null, 'customer', $customerId,
            $sent ? 'Email confirmation link sent' : 'Email confirmation link could not be sent', $ip, $userAgent);

        return $sent ? self::SENT : self::FAILED;
    }

    /**
     * Checks a link and, when valid, marks the email verified and links the customer's waiting shipments.
     * 'verified' | 'already' | 'expired' | 'invalid'. Nothing about the account is revealed for an invalid link.
     */
    public function verify(mixed $token, string $ip, string $userAgent): string
    {
        if (!is_string($token) || preg_match('/^([1-9][0-9]{0,9})\.([0-9]{10})\.([a-f0-9]{64})$/D', $token, $m) !== 1) {
            return 'invalid';
        }
        $customer = $this->customer((int) $m[1]);
        $expected = $customer === null ? null : self::token((int) $m[1], (string) $customer['email'], (int) $m[2]);
        if ($customer === null || $expected === null || !hash_equals($expected, $token) || $customer['status'] !== 'active') {
            return 'invalid';
        }
        if ((int) $m[2] < time()) {
            return 'expired';
        }
        if ($customer['email_verified_at'] !== null) {
            return 'already';
        }
        $id = (int) $customer['id'];
        Transaction::run($this->pdo, function () use ($id, $customer, $ip, $userAgent): void {
            $this->pdo->prepare('UPDATE customers SET email_verified_at = UTC_TIMESTAMP() WHERE id = ? AND email_verified_at IS NULL')->execute([$id]);
            $linked = $this->linkWaitingShipments($id);
            $this->audit->record('CUSTOMER_EMAIL_VERIFIED', null, 'customer', $id, 'Email confirmed' . ($linked > 0 ? '; ' . $linked . ' shipment(s) linked' : ''), $ip, $userAgent);
        });

        return 'verified';
    }

    /**
     * Links shipments recorded with this customer's email (and not yet linked) to the account, but only when the account
     * is active and its email verified. Returns the number linked.
     */
    public function linkWaitingShipments(int $customerId): int
    {
        $stmt = $this->pdo->prepare("UPDATE shipments s JOIN customers c ON c.id = ? SET s.customer_id = c.id
            WHERE s.customer_id IS NULL AND s.customer_email = c.email AND c.status = 'active' AND c.email_verified_at IS NOT NULL");
        $stmt->execute([$customerId]);

        return $stmt->rowCount();
    }

    /** @return array<string,mixed>|null */
    private function customer(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, email, status, email_verified_at FROM customers WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }
}
