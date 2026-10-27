<?php

declare(strict_types=1);

/**
 * SQL for the existing `enquiries` table (spec 14: public vehicle enquiries and reservation requests; spec 15 adds the
 * admin inbox). Every query is a prepared statement with bound values.
 */
final class EnquiryRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array{name:string,email:string,phone:?string,message:string} $values validated by EnquiryValidator
     * @return int the new enquiry id
     */
    /** $customerId: the signed-in account that sent it (from the server-side session, never from the form), or null. */
    public function insert(?int $vehicleId, string $type, array $values, ?int $customerId = null): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO enquiries (vehicle_id, customer_id, customer_name, email, phone, enquiry_type, message, status)
             VALUES (:vehicle, :customer, :name, :email, :phone, :type, :message, \'new\')'
        );
        $stmt->bindValue('vehicle', $vehicleId, $vehicleId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue('customer', $customerId, $customerId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue('name', $values['name']);
        $stmt->bindValue('email', $values['email']);
        $stmt->bindValue('phone', $values['phone'], $values['phone'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue('type', $type);
        $stmt->bindValue('message', $values['message']);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    /** Enquiries sent with this email address in the last $minutes (per-sender rate limit). */
    public function countRecentByEmail(string $email, int $minutes): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM enquiries WHERE email = :email AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL :minutes MINUTE)'
        );
        $stmt->bindValue('email', $email);
        $stmt->bindValue('minutes', $minutes, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }
}
