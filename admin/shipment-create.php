<?php

declare(strict_types=1);

/**
 * Add Shipment (spec 20). GET shows the form; POST creates it (and emails the customer only when ticked), then goes to
 * its page (Post/Redirect/Get). Protected: requireAdmin() runs first.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/ShipmentController.php';

$admin = requireAdmin();

$view = (new ShipmentController())->create($admin, $_SERVER['REQUEST_METHOD'] ?? 'GET', $_POST, admin_client_ip(), admin_user_agent());
if (isset($view['redirect'])) {
    admin_redirect($view['redirect'], 303);
}
if ($view['problem'] !== null && !($view['problem'] === 'csrf' && isset($view['values']))) {
    require dirname(__DIR__) . '/includes/admin/shipment_problem.php';
}
http_response_code((int) $view['http']);

$pageTitle        = 'Add Shipment';
$activeNav        = 'shipments';
$breadcrumbs      = [['Dashboard', 'dashboard.php'], ['Shipments', 'shipments.php'], ['Add Shipment', null]];
$formAction       = 'shipment-create.php';
$values           = $view['values'];
$errors           = $view['errors'];
$vehicleChoices   = $view['vehicleChoices'];
$shipment         = null;
$notifyChecked    = $view['notifyChecked'] ?? false;
$needsBackConfirm = false;
$submitLabel      = 'Create shipment';
$cancelHref       = 'shipments.php';
$formMessage      = $view['problem'] === 'csrf' ? (string) $view['message'] : ($view['formMessage'] ?? null);

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<div class="stack stack--lg">
    <div class="page-header">
        <p class="page-header__text">Record a shipment for a reserved or sold vehicle. You can add the container number later, when the shipping company provides it.</p>
    </div>
    <?php require dirname(__DIR__) . '/includes/admin/shipment_form.php'; ?>
</div>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
