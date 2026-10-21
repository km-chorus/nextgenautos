<?php

declare(strict_types=1);

/**
 * Edit Vehicle (spec 06_spec_vehicle_managt). The id comes from the URL (untrusted):
 * VehicleController validates its format and that the vehicle exists. GET shows the
 * form; POST saves through VehicleService, then redirects (Post/Redirect/Get).
 * Protected: requireAdmin() runs first.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/VehicleController.php';

$admin = requireAdmin();

$view = (new VehicleController())->edit(
    $admin,
    $_GET['id'] ?? null,
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_POST,
    admin_client_ip(),
    admin_user_agent()
);

if (isset($view['redirect'])) {
    admin_redirect($view['redirect'], 303);
}
if ($view['problem'] !== null && !($view['problem'] === 'csrf' && isset($view['values']))) {
    require dirname(__DIR__) . '/includes/admin/vehicle_problem.php';
}
http_response_code((int) $view['http']);

$vehicle   = $view['vehicle'];
$vehicleId = (int) $vehicle['id'];
$label     = admin_vehicle_label($vehicle['year'], $vehicle['brand_name'], $vehicle['model'], $vehicle['trim']);

$pageTitle   = 'Edit Vehicle';
$activeNav   = 'vehicles';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Vehicles', 'vehicles.php'], [$label, 'vehicle-view.php?id=' . $vehicleId], ['Edit', null]];

$formAction   = 'vehicle-edit.php?id=' . $vehicleId;
$values       = $view['values'];
$errors       = $view['errors'];
$brandChoices = $view['brandChoices'];
$currencies   = $view['currencies'];
$maxYear      = $view['maxYear'];
$submitLabel  = 'Save changes';
$cancelHref   = 'vehicle-view.php?id=' . $vehicleId;
$formMessage  = $view['problem'] === 'csrf' ? (string) $view['message'] : ($view['formMessage'] ?? null);
$isEdit       = true;

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<div class="stack stack--lg">
    <div class="page-header">
        <p class="page-header__text">Editing <strong><?= e($label) ?></strong>. Fields you do not change stay as they are.</p>
    </div>
    <?php require dirname(__DIR__) . '/includes/admin/vehicle_form.php'; ?>
</div>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
