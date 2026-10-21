<?php

declare(strict_types=1);

/**
 * Add Brand (spec 05_spec_brand_crud.md). GET shows the form; POST creates the
 * brand through BrandController -> BrandService, then redirects (Post/Redirect/Get).
 * Protected: requireAdmin() runs first.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/BrandController.php';

$admin = requireAdmin();

$view = (new BrandController())->create(
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
    require dirname(__DIR__) . '/includes/admin/brand_problem.php';
}
http_response_code((int) $view['http']);

$pageTitle   = 'Add Brand';
$activeNav   = 'brands';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Brands', 'brands.php'], ['Add Brand', null]];

$formAction  = 'brand-create.php';
$values      = $view['values'];
$errors      = $view['errors'];
$submitLabel = 'Create brand';
$cancelHref  = 'brands.php';
$formMessage = $view['problem'] === 'csrf' ? (string) $view['message'] : ($view['formMessage'] ?? null);
$slugAuto    = true;

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<div class="stack stack--lg">
    <div class="page-header">
        <p class="page-header__text">Add a vehicle brand. It becomes available for vehicles once its status is Active.</p>
    </div>
    <?php require dirname(__DIR__) . '/includes/admin/brand_form.php'; ?>
</div>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
