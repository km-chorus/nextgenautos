<?php

declare(strict_types=1);

/**
 * Add Vehicle (spec 06_spec_vehicle_managt). GET shows the form; POST creates the
 * vehicle through VehicleController -> VehicleService, then redirects (Post/Redirect/Get).
 * Protected: requireAdmin() runs first.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/VehicleController.php';

$admin = requireAdmin();

$view = (new VehicleController())->create(
    $admin,
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_POST,
    admin_client_ip(),
    admin_user_agent()
);

if (isset($view['redirect'])) {
    admin_redirect($view['redirect'], 303);
}
// A failed CSRF check keeps the typed values on screen; any other problem has its own page.
if ($view['problem'] !== null && !($view['problem'] === 'csrf' && isset($view['values']))) {
    require dirname(__DIR__) . '/includes/admin/vehicle_problem.php';
}
http_response_code((int) $view['http']);

$pageTitle   = 'Add Vehicle';
$activeNav   = 'vehicles';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Vehicles', 'vehicles.php'], ['Add Vehicle', null]];

$formAction   = 'vehicle-create.php';
$values       = $view['values'];
$errors       = $view['errors'];
$brandChoices = $view['brandChoices'];
$currencies   = $view['currencies'];
$maxYear      = $view['maxYear'];
$submitLabel  = 'Create vehicle';
$cancelHref   = 'vehicles.php';
$formMessage  = $view['problem'] === 'csrf' ? (string) $view['message'] : ($view['formMessage'] ?? null);
$isEdit       = false;

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<div class="stack stack--lg">
    <div class="page-header">
        <p class="page-header__text">Add a vehicle to the inventory. Photographs are added separately once the vehicle exists.</p>
    </div>
    <?php require dirname(__DIR__) . '/includes/admin/vehicle_form.php'; ?>
</div>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
