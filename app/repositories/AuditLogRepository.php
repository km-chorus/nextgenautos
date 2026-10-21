<?php

declare(strict_types=1);

/**
 * Data access for the existing `audit_logs` table (security/audit events), plus
 * the failure counting that powers login throttling.
 *
 * There is deliberately no free-form payload: callers pass a short description
 * only. Never pass passwords, hashes, session ids, CSRF tokens or other secrets.
 *
 * Login-throttle bookkeeping uses entity_type = 'login_identifier' with a
 * one-way fingerprint of the submitted email in entity_id, so the raw email of
 * a failed attempt is never stored.
 */
final class AuditLogRepository
{
    public const FAILURE_EVENTS = ['LOGIN_FAILURE', 'ACCOUNT_DISABLED_LOGIN_ATTEMPT'];
    public const IDENTIFIER_TYPE = 'login_identifier';

    public function __construct(private PDO $pdo)
    {
    }

    public function record(
        string $eventType,
        ?int $adminUserId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_logs (admin_user_id, event_type, entity_type, entity_id, description, ip_address, user_agent)
             VALUES (:admin, :event, :etype, :eid, :descr, :ip, :ua)'
        );
        $stmt->bindValue('admin', $adminUserId, $adminUserId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue('event', $eventType);
        $stmt->bindValue('etype', $entityType, $entityType === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue('eid', $entityId, $entityId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue('descr', $description === null ? null : substr($description, 0, 500));
        $stmt->bindValue('ip', $ipAddress === null ? null : substr($ipAddress, 0, 45));
        $stmt->bindValue('ua', $userAgent === null ? null : substr($userAgent, 0, 255));
        $stmt->execute();
    }

    /** Id of the latest successful login for this identifier (0 when none): failures before it no longer count. */
    public function lastSuccessId(int $identifier): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(MAX(id), 0) FROM audit_logs
             WHERE event_type = :event AND entity_type = :etype AND entity_id = :eid'
        );
        $stmt->execute(['event' => 'LOGIN_SUCCESS', 'etype' => self::IDENTIFIER_TYPE, 'eid' => $identifier]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Failures inside the window, in one query:
     *   ip         = from this IP (any account; not reset by another account's success)
     *   ipAccount  = this IP and this account fingerprint, after the account's last success
     *   account    = this account fingerprint from any IP, after the account's last success
     *
     * @return array{ip:int,ipAccount:int,account:int}
     */
    public function failureCounts(string $ip, int $identifier, int $windowMinutes, int $afterId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                COALESCE(SUM(ip_address = :ip_a), 0) AS ip_total,
                COALESCE(SUM(ip_address = :ip_b AND entity_type = :type_a AND entity_id = :id_a AND id > :after_a), 0) AS pair_total,
                COALESCE(SUM(entity_type = :type_b AND entity_id = :id_b AND id > :after_b), 0) AS account_total
             FROM audit_logs
             WHERE event_type IN (:fail_a, :fail_b)
               AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL :minutes MINUTE)
               AND (ip_address = :ip_c OR (entity_type = :type_c AND entity_id = :id_c))'
        );
        $stmt->bindValue('ip_a', $ip);
        $stmt->bindValue('ip_b', $ip);
        $stmt->bindValue('ip_c', $ip);
        foreach (['type_a', 'type_b', 'type_c'] as $name) {
            $stmt->bindValue($name, self::IDENTIFIER_TYPE);
        }
        foreach (['id_a', 'id_b', 'id_c'] as $name) {
            $stmt->bindValue($name, $identifier, PDO::PARAM_INT);
        }
        foreach (['after_a', 'after_b'] as $name) {
            $stmt->bindValue($name, $afterId, PDO::PARAM_INT);
        }
        $stmt->bindValue('fail_a', self::FAILURE_EVENTS[0]);
        $stmt->bindValue('fail_b', self::FAILURE_EVENTS[1]);
        $stmt->bindValue('minutes', $windowMinutes, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return [
            'ip'        => (int) $row['ip_total'],
            'ipAccount' => (int) $row['pair_total'],
            'account'   => (int) $row['account_total'],
        ];
    }

    /** Event groups used for throttling. */
    public const SECOND_FACTOR_FAILURES = ['2FA_FAILURE', 'REAUTH_FAILURE'];
    public const RESET_REQUESTS = ['PASSWORD_RESET_REQUESTED'];
    public const RESET_TOKEN_FAILURES = ['PASSWORD_RESET_FAILED'];

    /**
     * Counts events of the given types inside the window, for ONE subject:
     *   'admin' = events of this administrator, 'ip' = events from this address,
     *   'identifier' = events carrying this email fingerprint.
     * Only events newer than $afterId count (used so a success resets a counter).
     *
     * @param list<string> $eventTypes
     */
    public function countRecent(string $subject, string|int $value, array $eventTypes, int $windowMinutes, int $afterId = 0): int
    {
        $filter = match ($subject) {
            'admin'      => 'admin_user_id = ?',
            'ip'         => 'ip_address = ?',
            'identifier' => "entity_type = 'login_identifier' AND entity_id = ?",
            default      => throw new InvalidArgumentException('Unknown throttle subject.'),
        };
        $marks = implode(',', array_fill(0, count($eventTypes), '?'));

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM audit_logs
             WHERE event_type IN ($marks) AND $filter AND id > ?
               AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? MINUTE)"
        );
        $position = 1;
        foreach ($eventTypes as $type) {
            $stmt->bindValue($position++, $type);
        }
        $stmt->bindValue($position++, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $stmt->bindValue($position++, $afterId, PDO::PARAM_INT);
        $stmt->bindValue($position, $windowMinutes, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /** Id of an administrator's latest event of this type (0 when none). */
    public function lastEventIdForAdmin(string $eventType, int $adminId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(id), 0) FROM audit_logs WHERE event_type = :event AND admin_user_id = :admin');
        $stmt->execute(['event' => $eventType, 'admin' => $adminId]);

        return (int) $stmt->fetchColumn();
    }

    /** Used to log "rate limit triggered" once per burst instead of once per rejected request. */
    public function recentEventExists(string $eventType, string $ip, int $minutes): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM audit_logs
             WHERE event_type = :event AND ip_address = :ip
               AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL :minutes MINUTE)
             LIMIT 1'
        );
        $stmt->bindValue('event', $eventType);
        $stmt->bindValue('ip', $ip);
        $stmt->bindValue('minutes', $minutes, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }
}
