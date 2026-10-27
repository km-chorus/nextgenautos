<?php

declare(strict_types=1);

/**
 * Delete Shipment (spec 20, D10). GET = confirmation (deletes nothing). POST = deletion, after confirmation, with a valid
 * CSRF token; the server decides again whether it is allowed (only "Not yet shipped" and never emailed).
 * Protected: requireAdmin() runs first.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/ShipmentController.php';

$admin = requireAdmin();
$method = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? 'POST' : 'GET';
$view = (new ShipmentController())->delete($admin, $method, $_GET['id'] ?? null, $_POST, admin_client_ip(), admin_user_agent());
if (isset($view['redirect'])) {
    admin_redirect($view['redirect'], 303);
}
if ($view['problem'] !== null) {
    require dirname(__DIR__) . '/includes/admin/shipment_problem.php';
}
$shipment = $view['shipment'];
$shipmentId = (int) $shipment['id'];

$pageTitle   = 'Delete Shipment';
$activeNav   = 'shipments';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Shipments', 'shipments.php'], [ShipmentEmail::vehicleLabel($shipment), 'shipment-edit.php?id=' . $shipmentId], ['Delete', null]];

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<section class="card stack confirm-card" aria-labelledby="delete-title">
    <h2 class="card__title" id="delete-title">Delete this shipment?</h2>
    <?php if ($view['allowed']): ?>
        <p>The shipment of the <strong><?= e(ShipmentEmail::vehicleLabel($shipment)) ?></strong> for <?= e((string) $shipment['customer_name']) ?> will be removed. This cannot be undone.</p>
        <div class="cluster form-actions">
            <form method="post" action="shipment-delete.php"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $shipmentId ?>"><button type="submit" class="btn btn--danger">Delete shipment</button></form>
            <a class="btn btn--ghost" href="shipment-edit.php?id=<?= $shipmentId ?>">Cancel</a>
        </div>
    <?php else: ?>
        <p>This shipment can no longer be deleted: it has been booked or the customer has been emailed. It is kept for the record.</p>
        <a class="btn btn--secondary" href="shipment-edit.php?id=<?= $shipmentId ?>">Back to the shipment</a>
    <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
