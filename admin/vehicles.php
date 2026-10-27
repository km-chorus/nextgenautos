<?php

declare(strict_types=1);

/**
 * Vehicle Management: list, search, filters and pages (spec 06_spec_vehicle_managt).
 *
 * Presentation only: data comes from VehicleController -> VehicleService ->
 * VehicleRepository -> PDO. Protected: requireAdmin() runs before anything else.
 * Search, filters and paging use GET (they change nothing). Status changes and
 * deletion are separate POST endpoints.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/VehicleController.php';

$admin = requireAdmin();

$view = (new VehicleController())->index($admin, $_GET);
if ($view['problem'] !== null) {
    require dirname(__DIR__) . '/includes/admin/vehicle_problem.php';
}

$vehicles = $view['vehicles'];
$filters  = $view['filters'];
$flash    = $view['flash'];

/** Link to the list with the current search/filters and another page number. */
$pageUrl = static function (int $page) use ($filters): string {
    $query = array_filter([
        'q'      => $filters['q'],
        'brand'  => (string) ($filters['brand'] ?? ''),
        'status' => $filters['status'] === 'all' ? '' : $filters['status'],
        'year'   => (string) ($filters['year'] ?? ''),
        'page'   => $page > 1 ? (string) $page : '',
    ], 'strlen');

    return 'vehicles.php' . ($query === [] ? '' : '?' . http_build_query($query));
};

$pageTitle   = 'Vehicles';
$activeNav   = 'vehicles';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Vehicles', null]];

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<div class="stack stack--lg">

    <?php if ($flash !== null): ?>
        <?= admin_alert($flash['type'], $flash['text'], '', true) ?>
    <?php endif; ?>

    <div class="page-header">
        <p class="page-header__text">Manage the company's vehicle inventory: details, pricing, availability and publication.</p>
        <a class="btn btn--primary" href="vehicle-create.php"><?= admin_icon('plus') ?>Add Vehicle</a>
    </div>

    <?php if ($view['total_all'] === 0): ?>
        <section class="card card--flush" aria-labelledby="vehicles-heading">
            <h2 class="sr-only" id="vehicles-heading">Vehicle list</h2>
            <?= admin_empty_state(
                'No vehicles have been added yet.',
                'Add a vehicle to start building the inventory.',
                ['label' => 'Add Vehicle', 'icon' => 'plus', 'href' => 'vehicle-create.php'],
                'car'
            ) ?>
        </section>
    <?php else: ?>
        <section class="card card--flush" aria-labelledby="vehicles-heading">
            <div class="card__header"><h2 class="card__heading" id="vehicles-heading">Vehicle list</h2></div>

            <form class="filter-bar filter-bar--vehicles" method="get" action="vehicles.php" role="search" aria-label="Search and filter vehicles">
                <div class="field filter-bar__search">
                    <label class="label" for="vehicle-q">Search</label>
                    <input class="input" type="search" id="vehicle-q" name="q" value="<?= e($filters['q']) ?>"
                           maxlength="<?= VehicleValidator::SEARCH_MAX ?>" autocomplete="off" placeholder="Brand, model, trim, year or URL">
                </div>
                <div class="field">
                    <label class="label" for="vehicle-brand">Brand</label>
                    <select class="select" id="vehicle-brand" name="brand">
                        <option value="">All brands</option>
                        <?php foreach ($view['brands'] as $brand): ?>
                            <option value="<?= e((string) $brand['id']) ?>"<?= $filters['brand'] === (int) $brand['id'] ? ' selected' : '' ?>><?= e((string) $brand['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="label" for="vehicle-status">Status</label>
                    <select class="select" id="vehicle-status" name="status">
                        <option value="all">All statuses</option>
                        <?php foreach (VehicleValidator::STATUSES as $status): ?>
                            <option value="<?= e($status) ?>"<?= $filters['status'] === $status ? ' selected' : '' ?>><?= e(admin_status_label($status)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="label" for="vehicle-year">Year</label>
                    <select class="select" id="vehicle-year" name="year">
                        <option value="">All years</option>
                        <?php foreach ($view['years'] as $year): ?>
                            <option value="<?= e((string) $year) ?>"<?= $filters['year'] === $year ? ' selected' : '' ?>><?= e((string) $year) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-bar__actions">
                    <button type="submit" class="btn btn--secondary">Apply</button>
                    <?php if ($view['filtered']): ?>
                        <a class="btn btn--ghost" href="vehicles.php">Clear</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($vehicles === []): ?>
                <?= admin_empty_state(
                    'No vehicles match your current search or filters.',
                    'Try different words, or remove a filter.',
                    ['label' => 'Clear search and filters', 'href' => 'vehicles.php'],
                    'car'
                ) ?>
            <?php else: ?>
                <p class="result-count" role="status">
                    Showing <?= e((string) $view['from']) ?> to <?= e((string) $view['to']) ?> of <?= e(admin_format_count($view['total'])) ?> <?= $view['total'] === 1 ? 'vehicle' : 'vehicles' ?><?= $view['filtered'] ? ' found' : '' ?>
                </p>
                <div class="table-wrap" role="region" aria-labelledby="vehicles-heading" tabindex="0">
                    <table class="table table--stack table--vehicles">
                        <thead>
                            <tr>
                                <th scope="col">Vehicle</th>
                                <th scope="col">Brand</th>
                                <th scope="col">Year</th>
                                <th scope="col">Price</th>
                                <th scope="col">Status</th>
                                <th scope="col">Created</th>
                                <th scope="col" class="col-actions"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vehicles as $vehicle): ?>
                                <?php
                                $id    = (int) $vehicle['id'];
                                $label = admin_vehicle_label($vehicle['year'], $vehicle['brand_name'], $vehicle['model'], $vehicle['trim']);
                                ?>
                                <tr>
                                    <td class="cell-primary">
                                        <div class="cell-text">
                                            <p class="cell-title"><a href="vehicle-view.php?id=<?= $id ?>"><?= e($label) ?></a></p>
                                            <p class="cell-sub"><?= (int) $vehicle['is_published'] === 1 ? 'Published on website' : 'Not published' ?><?= (int) ($vehicle['is_accident'] ?? 0) === 1 ? ' · Accident vehicle' : '' ?></p>
                                        </div>
                                    </td>
                                    <td data-label="Brand"><?= e((string) $vehicle['brand_name']) ?></td>
                                    <td data-label="Year" class="nowrap"><?= e((string) $vehicle['year']) ?></td>
                                    <td data-label="Price" class="nowrap"><?= e(admin_format_price((string) $vehicle['price'], (string) $vehicle['currency'])) ?></td>
                                    <td data-label="Status"><?= admin_status_badge((string) $vehicle['availability_status']) ?></td>
                                    <td data-label="Created" class="nowrap"><time datetime="<?= e(str_replace(' ', 'T', (string) $vehicle['created_at'])) ?>Z"><?= e(admin_format_date((string) $vehicle['created_at'])) ?></time></td>
                                    <td class="col-actions">
                                        <div class="row-actions">
                                            <a class="btn btn--ghost btn--sm" href="vehicle-view.php?id=<?= $id ?>">View<span class="sr-only"> <?= e($label) ?></span></a>
                                            <a class="btn btn--ghost btn--sm" href="vehicle-edit.php?id=<?= $id ?>">Edit<span class="sr-only"> <?= e($label) ?></span></a>
                                            <a class="btn btn--ghost btn--sm btn--text-danger" href="vehicle-delete.php?id=<?= $id ?>">Delete<span class="sr-only"> <?= e($label) ?></span></a>
                                            <form method="post" action="vehicle-status.php" class="status-form">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= $id ?>">
                                                <input type="hidden" name="q" value="<?= e($filters['q']) ?>">
                                                <input type="hidden" name="f_brand" value="<?= e((string) ($filters['brand'] ?? '')) ?>">
                                                <input type="hidden" name="f_status" value="<?= e($filters['status']) ?>">
                                                <input type="hidden" name="f_year" value="<?= e((string) ($filters['year'] ?? '')) ?>">
                                                <input type="hidden" name="page" value="<?= e((string) $filters['page']) ?>">
                                                <label class="sr-only" for="status-<?= $id ?>">Change status of <?= e($label) ?></label>
                                                <select class="select select--sm" id="status-<?= $id ?>" name="status">
                                                    <?php foreach (VehicleValidator::STATUSES as $status): ?>
                                                        <option value="<?= e($status) ?>"<?= $vehicle['availability_status'] === $status ? ' selected' : '' ?>><?= e(admin_status_label($status)) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="btn btn--secondary btn--sm">Set<span class="sr-only"> status of <?= e($label) ?></span></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($view['pages'] > 1): ?>
                    <nav class="pager" aria-label="Vehicle list pages">
                        <?php if ($view['page'] > 1): ?>
                            <a class="btn btn--secondary btn--sm" href="<?= e($pageUrl($view['page'] - 1)) ?>" rel="prev">Previous</a>
                        <?php else: ?>
                            <span class="btn btn--secondary btn--sm" aria-disabled="true">Previous</span>
                        <?php endif; ?>
                        <p class="pager__status">Page <?= e((string) $view['page']) ?> of <?= e((string) $view['pages']) ?></p>
                        <?php if ($view['page'] < $view['pages']): ?>
                            <a class="btn btn--secondary btn--sm" href="<?= e($pageUrl($view['page'] + 1)) ?>" rel="next">Next</a>
                        <?php else: ?>
                            <span class="btn btn--secondary btn--sm" aria-disabled="true">Next</span>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
