<?php

declare(strict_types=1);

/**
 * Brand Management: list, search and status filter (spec 05_spec_brand_crud.md).
 *
 * Presentation only: data comes from BrandController -> BrandService ->
 * BrandRepository -> PDO. Protected: requireAdmin() runs before anything else.
 * Search and filter use GET (they change nothing). Status changes and deletion
 * are separate POST endpoints.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/BrandController.php';

$admin = requireAdmin();

$view = (new BrandController())->index($admin, $_GET);
if ($view['problem'] !== null) {
    require dirname(__DIR__) . '/includes/admin/brand_problem.php';
}

$brands  = $view['brands'];
$filters = $view['filters'];
$flash   = $view['flash'];

$pageTitle   = 'Brands';
$activeNav   = 'brands';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Brands', null]];

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<div class="stack stack--lg">

    <?php if ($flash !== null): ?>
        <?= admin_alert($flash['type'], $flash['text'], '', true) ?>
    <?php endif; ?>

    <div class="page-header">
        <p class="page-header__text">Manage the vehicle brands available on the platform. Inactive brands stay on record but are not offered for new vehicles.</p>
        <a class="btn btn--primary" href="brand-create.php"><?= admin_icon('plus') ?>Add Brand</a>
    </div>

    <?php if ($view['total_all'] === 0): ?>
        <section class="card card--flush" aria-labelledby="brands-heading">
            <h2 class="sr-only" id="brands-heading">Brand list</h2>
            <?= admin_empty_state(
                'No brands have been added yet.',
                'Add a brand so vehicles can be assigned to it.',
                ['label' => 'Add Brand', 'icon' => 'plus', 'href' => 'brand-create.php'],
                'tag'
            ) ?>
        </section>
    <?php else: ?>
        <section class="card card--flush" aria-labelledby="brands-heading">
            <div class="card__header"><h2 class="card__heading" id="brands-heading">Brand list</h2></div>

            <form class="filter-bar" method="get" action="brands.php" role="search" aria-label="Search and filter brands">
                <div class="field filter-bar__search">
                    <label class="label" for="brand-q">Search by name or slug</label>
                    <input class="input" type="search" id="brand-q" name="q" value="<?= e($filters['q']) ?>"
                           maxlength="<?= BrandValidator::SEARCH_MAX ?>" autocomplete="off" placeholder="For example Toyota">
                </div>
                <div class="field filter-bar__status">
                    <label class="label" for="brand-status">Status</label>
                    <select class="select" id="brand-status" name="status">
                        <?php foreach (['all' => 'All', 'active' => 'Active', 'inactive' => 'Inactive'] as $value => $label): ?>
                            <option value="<?= e($value) ?>"<?= $filters['status'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-bar__actions">
                    <button type="submit" class="btn btn--secondary">Apply</button>
                    <?php if ($view['filtered']): ?>
                        <a class="btn btn--ghost" href="brands.php">Clear</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($brands === []): ?>
                <?= admin_empty_state(
                    'No brands match your search.',
                    'Try a different name or slug, or show all statuses.',
                    ['label' => 'Clear search and filters', 'href' => 'brands.php'],
                    'tag'
                ) ?>
            <?php else: ?>
                <p class="result-count" role="status">
                    <?= e((string) count($brands)) ?> <?= count($brands) === 1 ? 'brand' : 'brands' ?><?= $view['filtered'] ? ' found' : '' ?>
                </p>
                <div class="table-wrap" role="region" aria-labelledby="brands-heading" tabindex="0">
                    <table class="table table--stack table--brands">
                        <thead>
                            <tr>
                                <th scope="col">Brand</th>
                                <th scope="col">Slug</th>
                                <th scope="col">Status</th>
                                <th scope="col">Display order</th>
                                <th scope="col">Created</th>
                                <th scope="col" class="col-actions"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($brands as $brand): ?>
                                <?php
                                $id       = (int) $brand['id'];
                                $name     = (string) $brand['name'];
                                $isActive = $brand['status'] === 'active';
                                ?>
                                <tr>
                                    <td class="cell-primary">
                                        <div class="cell-text">
                                            <p class="cell-title"><a href="brand-view.php?id=<?= $id ?>"><?= e($name) ?></a></p>
                                            <?php if ((int) $brand['vehicle_count'] > 0): ?>
                                                <p class="cell-sub"><?= e((string) $brand['vehicle_count']) ?> <?= (int) $brand['vehicle_count'] === 1 ? 'vehicle' : 'vehicles' ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td data-label="Slug"><code class="slug"><?= e((string) $brand['slug']) ?></code></td>
                                    <td data-label="Status"><?= admin_status_badge((string) $brand['status']) ?></td>
                                    <td data-label="Display order" class="nowrap"><?= e((string) $brand['display_order']) ?></td>
                                    <td data-label="Created" class="nowrap"><time datetime="<?= e(str_replace(' ', 'T', (string) $brand['created_at'])) ?>Z"><?= e(admin_format_date((string) $brand['created_at'])) ?></time></td>
                                    <td class="col-actions">
                                        <div class="row-actions">
                                            <a class="btn btn--ghost btn--sm" href="brand-view.php?id=<?= $id ?>">View<span class="sr-only"> <?= e($name) ?></span></a>
                                            <a class="btn btn--ghost btn--sm" href="brand-edit.php?id=<?= $id ?>">Edit<span class="sr-only"> <?= e($name) ?></span></a>
                                            <form method="post" action="brand-status.php" class="inline-form">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= $id ?>">
                                                <input type="hidden" name="status" value="<?= $isActive ? 'inactive' : 'active' ?>">
                                                <input type="hidden" name="q" value="<?= e($filters['q']) ?>">
                                                <input type="hidden" name="filter_status" value="<?= e($filters['status']) ?>">
                                                <button type="submit" class="btn btn--ghost btn--sm"><?= $isActive ? 'Deactivate' : 'Activate' ?><span class="sr-only"> <?= e($name) ?></span></button>
                                            </form>
                                            <a class="btn btn--ghost btn--sm btn--text-danger" href="brand-delete.php?id=<?= $id ?>">Delete<span class="sr-only"> <?= e($name) ?></span></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
