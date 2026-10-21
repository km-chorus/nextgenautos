<?php

declare(strict_types=1);

/**
 * Edit Brand (spec 05_spec_brand_crud.md). The id comes from the URL (untrusted):
 * BrandController validates its format and that the brand exists. GET shows the
 * form; POST saves through BrandService, then redirects (Post/Redirect/Get).
 * Protected: requireAdmin() runs first.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/BrandController.php';

$admin = requireAdmin();

$view = (new BrandController())->edit(
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
    require dirname(__DIR__) . '/includes/admin/brand_problem.php';
}
http_response_code((int) $view['http']);

$brand       = $view['brand'];
$brandId     = (int) $brand['id'];
$pageTitle   = 'Edit Brand';
$activeNav   = 'brands';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Brands', 'brands.php'], [(string) $brand['name'], 'brand-view.php?id=' . $brandId], ['Edit', null]];

$formAction  = 'brand-edit.php?id=' . $brandId;
$values      = $view['values'];
$errors      = $view['errors'];
$submitLabel = 'Save changes';
$cancelHref  = 'brand-view.php?id=' . $brandId;
$formMessage = $view['problem'] === 'csrf' ? (string) $view['message'] : ($view['formMessage'] ?? null);
$slugAuto    = false;

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<div class="stack stack--lg">
    <div class="page-header">
        <p class="page-header__text">Editing <strong><?= e((string) $brand['name']) ?></strong>. Fields you do not change stay as they are.</p>
    </div>
    <?php require dirname(__DIR__) . '/includes/admin/brand_form.php'; ?>
</div>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
