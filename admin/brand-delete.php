<?php

declare(strict_types=1);

/**
 * Delete Brand (spec 05_spec_brand_crud.md).
 *   GET  = the confirmation page. It only reads; opening this URL deletes nothing.
 *   POST = the deletion, after explicit confirmation, with a valid CSRF token.
 * The server decides again whether the brand may be deleted (a brand used by
 * vehicles never can), whatever the browser showed.
 * Protected: requireAdmin() runs first.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/BrandController.php';

$admin = requireAdmin();
$controller = new BrandController();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $view = $controller->delete($admin, $_POST, admin_client_ip(), admin_user_agent());
    if (isset($view['redirect'])) {
        admin_redirect($view['redirect'], 303);
    }
    require dirname(__DIR__) . '/includes/admin/brand_problem.php';
}

$view = $controller->confirmDelete($admin, $_GET['id'] ?? null);
if ($view['problem'] !== null) {
    require dirname(__DIR__) . '/includes/admin/brand_problem.php';
}

$brand    = $view['brand'];
$brandId  = (int) $brand['id'];
$name     = (string) $brand['name'];
$vehicles = (int) $brand['vehicle_count'];

$pageTitle   = 'Delete Brand';
$activeNav   = 'brands';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Brands', 'brands.php'], [$name, 'brand-view.php?id=' . $brandId], ['Delete', null]];

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<section class="card confirm-card" aria-labelledby="delete-heading">
    <h2 class="card__title" id="delete-heading">Delete <?= e($name) ?>?</h2>

    <?php if ($vehicles > 0): ?>
        <div class="section-alert">
            <?= admin_alert('error', BrandController::MESSAGE_REFERENCED, 'This brand is used by ' . $vehicles . ($vehicles === 1 ? ' vehicle.' : ' vehicles.')) ?>
        </div>
        <div class="cluster form-actions">
            <form method="post" action="brand-status.php" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $brandId ?>">
                <input type="hidden" name="status" value="inactive">
                <input type="hidden" name="from" value="view">
                <button type="submit" class="btn btn--primary"<?= $brand['status'] === 'inactive' ? ' disabled' : '' ?>>Deactivate <?= e($name) ?></button>
            </form>
            <a class="btn btn--ghost" href="brand-view.php?id=<?= $brandId ?>">Cancel</a>
        </div>
    <?php else: ?>
        <p class="text-muted">This action cannot be undone. The brand will be removed permanently. To only hide it, deactivate it instead.</p>
        <form method="post" action="brand-delete.php" class="cluster form-actions" data-single-submit>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $brandId ?>">
            <input type="hidden" name="confirm" value="yes">
            <button type="submit" class="btn btn--danger" data-submit-button>Delete <?= e($name) ?></button>
            <a class="btn btn--ghost" href="brand-view.php?id=<?= $brandId ?>">Cancel</a>
        </form>
    <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
