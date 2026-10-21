<?php

declare(strict_types=1);

/**
 * Delete Vehicle (spec 06_spec_vehicle_managt).
 *   GET  = the confirmation page. It only reads; opening this URL deletes nothing.
 *   POST = the deletion, after explicit confirmation, with a valid CSRF token.
 * The server decides again whether the vehicle may be deleted (one referenced by
 * shipments, enquiries or images never can), whatever the browser showed.
 * Protected: requireAdmin() runs first.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/VehicleController.php';

$admin = requireAdmin();
$controller = new VehicleController();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $view = $controller->delete($admin, $_POST, admin_client_ip(), admin_user_agent());
    if (isset($view['redirect'])) {
        admin_redirect($view['redirect'], 303);
    }
    require dirname(__DIR__) . '/includes/admin/vehicle_problem.php';
}

$view = $controller->confirmDelete($admin, $_GET['id'] ?? null);
if ($view['problem'] !== null) {
    require dirname(__DIR__) . '/includes/admin/vehicle_problem.php';
}

$vehicle   = $view['vehicle'];
$vehicleId = (int) $vehicle['id'];
$label     = admin_vehicle_label($vehicle['year'], $vehicle['brand_name'], $vehicle['model'], $vehicle['trim']);
$reasons   = $view['reasons'];

$pageTitle   = 'Delete Vehicle';
$activeNav   = 'vehicles';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Vehicles', 'vehicles.php'], [$label, 'vehicle-view.php?id=' . $vehicleId], ['Delete', null]];

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<section class="card confirm-card" aria-labelledby="delete-heading">
    <h2 class="card__title" id="delete-heading">Delete <?= e($label) ?>?</h2>

    <?php if ($reasons !== []): ?>
        <div class="section-alert">
            <?= admin_alert('error', VehicleController::referencedMessage($reasons)) ?>
        </div>
        <p class="text-muted">Sold or withdrawn vehicles are kept on record: change the status to Sold or Unavailable.</p>
        <div class="cluster form-actions">
            <a class="btn btn--primary" href="vehicle-edit.php?id=<?= $vehicleId ?>">Edit <?= e($label) ?></a>
            <a class="btn btn--ghost" href="vehicle-view.php?id=<?= $vehicleId ?>">Cancel</a>
        </div>
    <?php else: ?>
        <p class="text-muted">This action cannot be undone. The vehicle record will be removed permanently. To only take it out of stock, change its status instead.</p>
        <form method="post" action="vehicle-delete.php" class="cluster form-actions" data-single-submit>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $vehicleId ?>">
            <input type="hidden" name="confirm" value="yes">
            <button type="submit" class="btn btn--danger" data-submit-button>Delete <?= e($label) ?></button>
            <a class="btn btn--ghost" href="vehicle-view.php?id=<?= $vehicleId ?>">Cancel</a>
        </form>
    <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
