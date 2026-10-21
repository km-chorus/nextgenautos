<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/repositories/DashboardRepository.php';

/**
 * Dashboard data for the admin overview. Returns plain data only: no markup,
 * no escaping, no SQL.
 */
class DashboardService
{
    /** Approved shipment lifecycle (business rules), in display order. */
    public const SHIPMENT_STATUSES = [
        'NOT_YET_SHIPPED',
        'SHIPMENT_BOOKED',
        'TRACKING_INFORMATION_AVAILABLE',
        'IN_TRANSIT',
        'ARRIVED',
        'DELIVERED',
    ];

    private const RECENT_LIMIT = 5;
    private const MAX_LIMIT = 20;

    public function __construct(private DashboardRepository $repository)
    {
    }

    /** Opens the database connection. Throws RuntimeException if it fails. */
    public static function create(): self
    {
        return new self(new DashboardRepository(Database::connection()));
    }

    /** @return array{total_vehicles:int,available_vehicles:int,total_shipments:int,new_enquiries:int} */
    public function getOverviewStatistics(): array
    {
        return $this->repository->overviewCounts();
    }

    /** @return list<array<string,mixed>> */
    public function getRecentVehicles(int $limit = self::RECENT_LIMIT): array
    {
        return $this->repository->recentVehicles($this->clamp($limit));
    }

    /** @return list<array<string,mixed>> */
    public function getRecentEnquiries(int $limit = self::RECENT_LIMIT): array
    {
        return $this->repository->recentEnquiries($this->clamp($limit));
    }

    /**
     * Every approved status is present (zero when no records), in lifecycle order.
     *
     * @return array{total:int,by_status:array<string,int>}
     */
    public function getShipmentSummary(): array
    {
        $counts = $this->repository->shipmentCountsByStatus();

        $byStatus = [];
        foreach (self::SHIPMENT_STATUSES as $status) {
            $byStatus[$status] = $counts[$status] ?? 0;
        }

        return ['total' => array_sum($byStatus), 'by_status' => $byStatus];
    }

    private function clamp(int $limit): int
    {
        return max(1, min(self::MAX_LIMIT, $limit));
    }
}
