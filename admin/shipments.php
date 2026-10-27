<?php

declare(strict_types=1);

/**
 * Shipments list (spec 20): search, status filter, pages. Presentation only: ShipmentController -> ShipmentService ->
 * ShipmentRepository. Protected: requireAdmin() runs first. Search and filters use GET (they change nothing).
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/ShipmentController.php';

$admin = requireAdmin();

$view = (new ShipmentController())->index($admin, $_GET);
if ($view['problem'] !== null) {
    require dirname(__DIR__) . '/includes/admin/shipment_problem.php';
}
$filters = $view['filters'];
$pageUrl = static function (int $page) use ($filters): string {
    $q = array_filter(['q' => $filters['q'], 'status' => $filters['status'] === 'all' ? '' : $filters['status'], 'page' => $page > 1 ? (string) $page : ''], 'strlen');

    return 'shipments.php' . ($q === [] ? '' : '?' . http_build_query($q));
};

$pageTitle   = 'Shipments';
$activeNav   = 'shipments';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Shipments', null]];

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<div class="stack stack--lg">
    <?php if ($view['flash'] !== null): ?>
        <?= admin_alert($view['flash']['type'], $view['flash']['text'], '', true) ?>
    <?php endif; ?>

    <div class="page-header">
        <p class="page-header__text">Record each customer's shipment and keep its status up to date. Live location always comes from the shipping line's own tracking page.</p>
        <a class="btn btn--primary" href="shipment-create.php"><?= admin_icon('plus') ?>Add Shipment</a>
    </div>

    <?php if ($view['total_all'] === 0): ?>
        <section class="card card--flush" aria-labelledby="shipments-heading">
            <h2 class="sr-only" id="shipments-heading">Shipment list</h2>
            <?= admin_empty_state('No shipments have been recorded yet.', 'Add a shipment when a sold or reserved vehicle is being shipped to its customer.',
                ['label' => 'Add Shipment', 'icon' => 'plus', 'href' => 'shipment-create.php'], 'ship') ?>
        </section>
    <?php else: ?>
        <section class="card card--flush" aria-labelledby="shipments-heading">
            <div class="card__header"><h2 class="card__heading" id="shipments-heading">Shipment list</h2></div>
            <form class="filter-bar filter-bar--parts" method="get" action="shipments.php" role="search" aria-label="Search and filter shipments">
                <div class="field filter-bar__search">
                    <label class="label" for="ship-q">Search</label>
                    <input class="input" type="search" id="ship-q" name="q" value="<?= e($filters['q']) ?>" maxlength="<?= ShipmentValidator::SEARCH_MAX ?>" autocomplete="off" placeholder="Container number, customer or vehicle">
                </div>
                <div class="field">
                    <label class="label" for="ship-filter-status">Status</label>
                    <select class="select" id="ship-filter-status" name="status">
                        <option value="all">All statuses</option>
                        <?php foreach (ShipmentValidator::STATUSES as $key => $label): ?>
                            <option value="<?= e($key) ?>"<?= $filters['status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-bar__actions">
                    <button type="submit" class="btn btn--secondary">Apply</button>
                    <?php if ($view['filtered']): ?><a class="btn btn--ghost" href="shipments.php">Clear</a><?php endif; ?>
                </div>
            </form>

            <?php if ($view['shipments'] === []): ?>
                <?= admin_empty_state('No shipments match your search or filter.', 'Try different words, or remove the filter.', ['label' => 'Clear search and filter', 'href' => 'shipments.php'], 'ship') ?>
            <?php else: ?>
                <p class="result-count" role="status">Showing <?= $view['from'] ?> to <?= $view['to'] ?> of <?= e(admin_format_count($view['total'])) ?> <?= $view['total'] === 1 ? 'shipment' : 'shipments' ?></p>
                <div class="table-wrap" role="region" aria-labelledby="shipments-heading" tabindex="0">
                    <table class="table table--stack">
                        <thead><tr><th scope="col">Vehicle</th><th scope="col">Customer</th><th scope="col">Container</th><th scope="col">Status</th><th scope="col">Updated</th><th scope="col" class="col-actions"><span class="sr-only">Actions</span></th></tr></thead>
                        <tbody>
                            <?php foreach ($view['shipments'] as $s): $id = (int) $s['id']; $label = ShipmentEmail::vehicleLabel($s); ?>
                                <tr>
                                    <td class="cell-primary"><div class="cell-text"><p class="cell-title"><a href="shipment-edit.php?id=<?= $id ?>"><?= e($label) ?></a></p><p class="cell-sub"><?= e((string) ($s['shipping_company'] ?? 'Shipping line not set')) ?></p></div></td>
                                    <td data-label="Customer"><?= e((string) $s['customer_name']) ?><?= $s['customer_id'] !== null ? '<span class="cell-sub"> · website account</span>' : '' ?></td>
                                    <td data-label="Container" class="nowrap"><?= $s['container_number'] !== null ? e((string) $s['container_number']) : '<span class="cell-sub">Not yet assigned</span>' ?></td>
                                    <td data-label="Status"><?= admin_status_badge((string) $s['status']) ?></td>
                                    <td data-label="Updated" class="nowrap"><?= e(admin_format_date((string) $s['updated_at'])) ?><?= $s['notified_at'] !== null ? '<span class="cell-sub"><br>Emailed ' . e(admin_format_date((string) $s['notified_at'])) . '</span>' : '' ?></td>
                                    <td class="col-actions"><a class="btn btn--ghost btn--sm" href="shipment-edit.php?id=<?= $id ?>">Update<span class="sr-only"> <?= e($label) ?></span></a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($view['pages'] > 1): ?>
                    <nav class="pager" aria-label="Shipment list pages">
                        <?php if ($view['page'] > 1): ?><a class="btn btn--secondary btn--sm" href="<?= e($pageUrl($view['page'] - 1)) ?>" rel="prev">Previous</a><?php else: ?><span class="btn btn--secondary btn--sm" aria-disabled="true">Previous</span><?php endif; ?>
                        <p class="pager__status">Page <?= $view['page'] ?> of <?= $view['pages'] ?></p>
                        <?php if ($view['page'] < $view['pages']): ?><a class="btn btn--secondary btn--sm" href="<?= e($pageUrl($view['page'] + 1)) ?>" rel="next">Next</a><?php else: ?><span class="btn btn--secondary btn--sm" aria-disabled="true">Next</span><?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
