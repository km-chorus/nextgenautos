<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/DashboardService.php';

/**
 * Builds the dashboard view model.
 *
 * Each section is loaded independently, so one failing query shows an error
 * state in that section only. Technical detail goes to the server error log;
 * nothing technical reaches the view.
 *
 * Authentication/authorization are out of scope for this specification and are
 * added by a later one in front of this controller.
 */
final class DashboardController
{
    public function __construct(private ?DashboardService $service = null)
    {
    }

    /**
     * @return array{
     *   stats:array{error:bool,data:?array},
     *   vehicles:array{error:bool,data:?array},
     *   enquiries:array{error:bool,data:?array},
     *   shipments:array{error:bool,data:?array}
     * }
     */
    public function index(): array
    {
        $failed = ['error' => true, 'data' => null];

        try {
            $service = $this->service ?? DashboardService::create();
        } catch (Throwable $e) {
            error_log('Dashboard: service unavailable: ' . get_class($e) . ': ' . $e->getMessage());
            return ['stats' => $failed, 'vehicles' => $failed, 'enquiries' => $failed, 'shipments' => $failed];
        }

        return [
            'stats'     => $this->load('stats', static fn () => $service->getOverviewStatistics()),
            'vehicles'  => $this->load('vehicles', static fn () => $service->getRecentVehicles()),
            'enquiries' => $this->load('enquiries', static fn () => $service->getRecentEnquiries()),
            'shipments' => $this->load('shipments', static fn () => $service->getShipmentSummary()),
        ];
    }

    /** @return array{error:bool,data:?array} */
    private function load(string $section, callable $loader): array
    {
        try {
            return ['error' => false, 'data' => $loader()];
        } catch (Throwable $e) {
            error_log("Dashboard: section '{$section}' failed: " . get_class($e) . ': ' . $e->getMessage());
            return ['error' => true, 'data' => null];
        }
    }
}
