<?php

declare(strict_types=1);

/**
 * Read-only aggregate queries for the admin dashboard.
 *
 * All SQL for the dashboard lives here (never in templates). Every query is a
 * PDO prepared statement; values are bound, never concatenated. The dashboard
 * accepts no user input, so there is nothing to validate beyond the limits
 * clamped in DashboardService.
 *
 * "Recent" is ordered by id DESC: ids are auto-increment, so id order is
 * creation order, and it uses the primary key (no extra index required).
 */
final class DashboardRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Vehicles exclude archived (soft-deleted) records.
     *
     * @return array{total_vehicles:int,available_vehicles:int,total_shipments:int,new_enquiries:int}
     */
    public function overviewCounts(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                (SELECT COUNT(*) FROM vehicles WHERE deleted_at IS NULL) AS total_vehicles,
                (SELECT COUNT(*) FROM vehicles WHERE deleted_at IS NULL AND availability_status = :available) AS available_vehicles,
                (SELECT COUNT(*) FROM shipments) AS total_shipments,
                (SELECT COUNT(*) FROM enquiries WHERE status = :new_status) AS new_enquiries'
        );
        $stmt->execute(['available' => 'available', 'new_status' => 'new']);
        $row = $stmt->fetch();

        return [
            'total_vehicles'     => (int) $row['total_vehicles'],
            'available_vehicles' => (int) $row['available_vehicles'],
            'total_shipments'    => (int) $row['total_shipments'],
            'new_enquiries'      => (int) $row['new_enquiries'],
        ];
    }

    /**
     * Shipment record count per status (statuses with no records are absent).
     *
     * @return array<string,int>
     */
    public function shipmentCountsByStatus(): array
    {
        $stmt = $this->pdo->prepare('SELECT status, COUNT(*) AS total FROM shipments GROUP BY status');
        $stmt->execute();

        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Latest non-archived vehicles with brand name and primary image (one
     * query, no per-row lookups). Only the columns the dashboard shows.
     *
     * @return list<array<string,mixed>>
     */
    public function recentVehicles(int $limit): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT v.id, v.year, b.name AS brand_name, v.model, v.trim,
                    v.price, v.currency, v.availability_status,
                    vi.file_path AS image_path, vi.alt_text AS image_alt
             FROM vehicles v
             JOIN brands b ON b.id = v.brand_id
             LEFT JOIN vehicle_images vi ON vi.vehicle_id = v.id AND vi.is_primary = 1
             WHERE v.deleted_at IS NULL
             ORDER BY v.id DESC
             LIMIT :row_limit'
        );
        $stmt->bindValue('row_limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Latest enquiries. Deliberately excludes email, phone and message:
     * the dashboard only needs who, about what, when and status.
     *
     * @return list<array<string,mixed>>
     */
    public function recentEnquiries(int $limit): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.id, e.customer_name, e.enquiry_type, e.status, e.created_at,
                    v.year AS vehicle_year, b.name AS brand_name, v.model AS vehicle_model
             FROM enquiries e
             LEFT JOIN vehicles v ON v.id = e.vehicle_id
             LEFT JOIN brands b ON b.id = v.brand_id
             ORDER BY e.id DESC
             LIMIT :row_limit'
        );
        $stmt->bindValue('row_limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
