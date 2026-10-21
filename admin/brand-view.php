<?php

declare(strict_types=1);

/**
 * Brand details (spec 05_spec_brand_crud.md): read-only view with the actions
 * available for one brand. The id comes from the URL (untrusted) and is
 * validated by BrandController. Protected: requireAdmin() runs first.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/BrandController.php';

$admin = requireAdmin();

$view = (new BrandController())->show($admin, $_GET['id'] ?? null);
if ($view['problem'] !== null) {
    require dirname(__DIR__) . '/includes/admin/brand_problem.php';
}

$brand    = $view['brand'];
$flash    = $view['flash'];
$brandId  = (int) $brand['id'];
$name     = (string) $brand['name'];
$isActive = $brand['status'] === 'active';
$vehicles = (int) $brand['vehicle_count'];

$pageTitle   = $name;
$activeNav   = 'brands';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Brands', 'brands.php'], [$name, null]];

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<div class="stack stack--lg">

    <?php if ($flash !== null): ?>
        <?= admin_alert($flash['type'], $flash['text'], '', true) ?>
    <?php endif; ?>

    <section class="card" aria-labelledby="details-heading">
        <div class="card__row">
            <h2 class="card__title" id="details-heading">Brand details</h2>
            <?= admin_status_badge((string) $brand['status']) ?>
        </div>

        <dl class="facts facts--wide">
            <div><dt>Name</dt><dd><?= e($name) ?></dd></div>
            <div><dt>Slug</dt><dd><code class="slug"><?= e((string) $brand['slug']) ?></code></dd></div>
            <div><dt>Description</dt><dd class="facts__text"><?= trim((string) $brand['description']) === '' ? '<span class="text-muted">No description</span>' : nl2br(e((string) $brand['description']), false) ?></dd></div>
            <div><dt>Status</dt><dd><?= e(admin_status_label((string) $brand['status'])) ?></dd></div>
            <div><dt>Display order</dt><dd><?= e((string) $brand['display_order']) ?></dd></div>
            <div><dt>Vehicles using this brand</dt><dd><?= e((string) $vehicles) ?></dd></div>
            <div><dt>Created</dt><dd><?= e(admin_format_datetime((string) $brand['created_at'])) ?></dd></div>
            <div><dt>Last updated</dt><dd><?= e(admin_format_datetime((string) $brand['updated_at'])) ?></dd></div>
        </dl>

        <div class="cluster form-actions">
            <a class="btn btn--primary" href="brand-edit.php?id=<?= $brandId ?>">Edit<span class="sr-only"> <?= e($name) ?></span></a>
            <form method="post" action="brand-status.php" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $brandId ?>">
                <input type="hidden" name="status" value="<?= $isActive ? 'inactive' : 'active' ?>">
                <input type="hidden" name="from" value="view">
                <button type="submit" class="btn btn--secondary"><?= $isActive ? 'Deactivate' : 'Activate' ?><span class="sr-only"> <?= e($name) ?></span></button>
            </form>
            <a class="btn btn--secondary btn--text-danger" href="brand-delete.php?id=<?= $brandId ?>">Delete<span class="sr-only"> <?= e($name) ?></span></a>
            <a class="btn btn--ghost" href="brands.php">Back to Brands</a>
        </div>
    </section>
</div>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
