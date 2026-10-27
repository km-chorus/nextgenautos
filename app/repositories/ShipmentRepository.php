<?php

declare(strict_types=1);

/**
 * Data access for shipments (spec 20). Prepared statements only; LIKE wildcards in searches are escaped.
 * Customer-facing reads (forCustomer) select by the customer's ACCOUNT ID only, never by an email address.
 */
final class ShipmentRepository
{
    private const SELECT = 'SELECT s.*, v.year AS vehicle_year, v.model AS vehicle_model, v.trim AS vehicle_trim, v.slug AS vehicle_slug,
            v.mileage AS vehicle_mileage, v.mileage_unit AS vehicle_mileage_unit, v.transmission AS vehicle_transmission,
            v.fuel_type AS vehicle_fuel, v.engine_size AS vehicle_engine,
            (SELECT i.file_path FROM vehicle_images i WHERE i.vehicle_id = v.id AND i.is_primary = 1 LIMIT 1) AS vehicle_photo,
            b.name AS brand_name, c.email AS account_email, c.name AS account_name, c.status AS account_status
        FROM shipments s
        JOIN vehicles v ON v.id = s.vehicle_id
        JOIN brands b ON b.id = v.brand_id
        LEFT JOIN customers c ON c.id = s.customer_id';

    public function __construct(private PDO $pdo)
    {
    }

    /** @param array{q:string,status:string} $filters */
    public function countMatching(array $filters): int
    {
        [$where, $params] = $this->where($filters);
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM shipments s JOIN vehicles v ON v.id = s.vehicle_id JOIN brands b ON b.id = v.brand_id WHERE ' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM shipments')->fetchColumn();
    }

    /** @return list<array<string,mixed>> most recently updated first */
    public function search(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->where($filters);
        $stmt = $this->pdo->prepare(self::SELECT . ' WHERE ' . $where . ' ORDER BY s.updated_at DESC, s.id DESC LIMIT :lim OFFSET :off');
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue('off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private function where(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];
        if ($filters['q'] !== '') {
            $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
            $where[] = '(s.container_number LIKE :q1 OR s.customer_name LIKE :q2 OR s.customer_email LIKE :q3 OR v.model LIKE :q4 OR b.name LIKE :q5)';
            foreach (['q1', 'q2', 'q3', 'q4', 'q5'] as $k) {
                $params[$k] = $like;
            }
        }
        if ($filters['status'] !== 'all') {
            $where[] = 's.status = :status';
            $params['status'] = $filters['status'];
        }

        return [implode(' AND ', $where), $params];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id, bool $lock = false): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT . ' WHERE s.id = :id' . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @return list<array<string,mixed>> the customer's own shipments (by account id), most recent first */
    public function forCustomer(int $customerId): array
    {
        // Defence in depth: never for an unverified account, even if a link exists.
        $stmt = $this->pdo->prepare(self::SELECT . ' WHERE s.customer_id = :c AND c.email_verified_at IS NOT NULL ORDER BY s.created_at DESC, s.id DESC');
        $stmt->execute(['c' => $customerId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Vehicles a shipment can be for: reserved or sold, not archived (D6), plus the given one (editing keeps its vehicle).
     *
     * @return list<array{id:int,label:string,status:string}>
     */
    public function vehicleChoices(?int $currentId = null): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT v.id, v.year, b.name AS brand, v.model, v.trim, v.availability_status FROM vehicles v JOIN brands b ON b.id = v.brand_id
             WHERE (v.deleted_at IS NULL AND v.availability_status IN ('reserved', 'sold')) OR v.id = :current
             ORDER BY v.updated_at DESC, v.id DESC"
        );
        $stmt->execute(['current' => $currentId ?? 0]);

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'label' => trim($r['year'] . ' ' . $r['brand'] . ' ' . $r['model'] . ' ' . (string) $r['trim']),
            'status' => (string) $r['availability_status'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array{id:int,availability_status:string,deleted_at:?string}|null */
    public function vehicle(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, availability_status, deleted_at FROM vehicles WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : ['id' => (int) $row['id'], 'availability_status' => (string) $row['availability_status'], 'deleted_at' => $row['deleted_at']];
    }

    /** True when the vehicle already has a shipment that is not delivered (one open shipment per vehicle). */
    public function vehicleHasOpenShipment(int $vehicleId, ?int $exceptId = null): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM shipments WHERE vehicle_id = :v AND status <> 'DELIVERED' AND id <> :except");
        $stmt->execute(['v' => $vehicleId, 'except' => $exceptId ?? 0]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** The ACTIVE, email-VERIFIED customer account with exactly this email (D1 link; verification required since spec 22), or null. */
    public function activeCustomerIdByEmail(string $email): ?int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM customers WHERE email = :e AND status = 'active' AND email_verified_at IS NOT NULL");
        $stmt->execute(['e' => $email]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /** @param array<string,mixed> $v validated values (+ customer_id) */
    public function insert(array $v): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO shipments (vehicle_id, customer_id, customer_name, customer_email, container_number, shipping_company, status,
                shipped_date, estimated_arrival, arrival_date, delivered_date, tracking_url, internal_notes)
             VALUES (:vehicle_id, :customer_id, :customer_name, :customer_email, :container_number, :shipping_company, :status,
                :shipped_date, :estimated_arrival, :arrival_date, :delivered_date, :tracking_url, :internal_notes)'
        );
        $stmt->execute($this->params($v));

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $v): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE shipments SET vehicle_id = :vehicle_id, customer_id = :customer_id, customer_name = :customer_name, customer_email = :customer_email,
                container_number = :container_number, shipping_company = :shipping_company, status = :status, shipped_date = :shipped_date,
                estimated_arrival = :estimated_arrival, arrival_date = :arrival_date, delivered_date = :delivered_date, tracking_url = :tracking_url,
                internal_notes = :internal_notes
             WHERE id = :id'
        );
        $stmt->execute($this->params($v) + ['id' => $id]);
    }

    private function params(array $v): array
    {
        $out = [];
        foreach (['vehicle_id', 'customer_id', 'customer_name', 'customer_email', 'container_number', 'shipping_company', 'status',
                     'shipped_date', 'estimated_arrival', 'arrival_date', 'delivered_date', 'tracking_url', 'internal_notes'] as $k) {
            $out[$k] = $v[$k] ?? null;
        }

        return $out;
    }

    public function markNotified(int $id): void
    {
        $this->pdo->prepare('UPDATE shipments SET notified_at = UTC_TIMESTAMP() WHERE id = :id')->execute(['id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM shipments WHERE id = :id')->execute(['id' => $id]);
    }

    /** @return list<array{event_type:string,description:string,created_at:string}> the shipment's history from the audit log */
    public function history(int $id): array
    {
        $stmt = $this->pdo->prepare("SELECT event_type, description, created_at FROM audit_logs WHERE entity_type = 'shipment' AND entity_id = :id ORDER BY id DESC LIMIT 50");
        $stmt->execute(['id' => $id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
