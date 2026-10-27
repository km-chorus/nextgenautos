<?php

declare(strict_types=1);

/**
 * Vehicles (the public inventory, spec 11): filter panel on the left, a three-column grid of real vehicles, server-side
 * search, sort and pagination. Presentation only: all data arrives from PublicVehicleService.
 *
 * @var PublicRouter $router
 * @var array<string,mixed> $data  filters, result (cards, total, page, pages), searchOptions
 */

$filters = $data['filters'];
$result = $data['result'];
$searchOptions = $data['searchOptions'];
$accidentPage = ($data['mode'] ?? 'normal') === 'accident';      // spec 23: the Accident Vehicles page uses this layout too
$vehiclesPath = $router->byName($accidentPage ? 'accident-vehicles' : 'vehicles')['path'];
$active = \PublicVehicleSearchValidator::hasFilters($filters);
$base = \PublicVehicleSearchValidator::toQuery($filters);

// A link to another page of the same search (only validated values are written back).
$pageUrl = static function (int $n) use ($base, $vehiclesPath): string {
    $q = $base;
    if ($n > 1) {
        $q['page'] = $n;
    }

    return $vehiclesPath . ($q === [] ? '' : '?' . http_build_query($q));
};

// Page numbers to show: first, last, and a window around the current page.
$pages = [];
for ($n = 1; $n <= $result['pages']; $n++) {
    if ($n === 1 || $n === $result['pages'] || abs($n - $result['page']) <= 1) {
        $pages[] = $n;
    }
}
$sorts = ['newest' => 'Newest', 'price_asc' => 'Price: Low to High', 'price_desc' => 'Price: High to Low'];
?>
<?php if ($accidentPage): ?>
<?= public_page_header('Accident Vehicles') ?>
<?php else: ?>
<?= public_page_header('Vehicles for Sale') ?>
<?php endif; ?>
<div class="container inventory" data-inventory>
<?php if ($accidentPage): ?>
    <aside class="inventory__notice" aria-labelledby="accident-notice-title">
        <span class="inventory__notice-icon" aria-hidden="true"><?= public_icon('alert-triangle') ?></span>
        <div>
            <p class="inventory__notice-title" id="accident-notice-title">Every vehicle on this page has accident damage.</p>
            <p class="inventory__notice-text">Read the damage details and look at the damage photos on each vehicle's page before you reserve it, and ask us anything you are unsure about.</p>
        </div>
    </aside>
<?php endif; ?>

    <div class="inventory__layout">
        <div class="inv-backdrop" data-inv-backdrop hidden></div>
        <aside class="inv-filters" id="inv-filters" data-inv-panel aria-labelledby="inv-filters-title">
<?php require dirname(__DIR__, 2) . '/includes/inventory_filters.php'; ?>
        </aside>

        <section class="inventory__results" aria-label="Vehicle results">
            <div class="inv-toolbar">
                <p class="inv-toolbar__count" role="status"><strong><?= (int) $result['total'] ?></strong> <?= $result['total'] === 1 ? 'vehicle' : 'vehicles' ?></p>
                <button class="inv-toolbar__filters" type="button" data-inv-open aria-controls="inv-filters" aria-expanded="false" hidden>Filters</button>
                <form class="inv-toolbar__sort" action="<?= e($vehiclesPath) ?>" method="get" data-inv-sort>
<?php foreach ($base as $name => $value): if ($name === 'sort') { continue; } ?>
                    <input type="hidden" name="<?= e($name) ?>" value="<?= e((string) $value) ?>">
<?php endforeach; ?>
                    <label for="f-sort">Sort</label>
                    <select id="f-sort" name="sort">
<?php foreach ($sorts as $key => $label): ?>
                        <option value="<?= e($key) ?>"<?= $filters['sort'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
                    </select>
                    <button class="inv-toolbar__apply" type="submit">Sort</button>
                </form>
            </div>

<?php if ($result['cards'] === []): ?>
            <div class="inventory__empty">
<?php if ($active): ?>
                <h2 class="inventory__empty-title">No vehicles match your current filters.</h2>
                <p>Try adjusting your search criteria or clearing some filters.</p>
                <a class="btn btn--secondary" href="<?= e($vehiclesPath) ?>">Clear Filters</a>
<?php else: ?>
<?php if ($accidentPage): ?>
                <h2 class="inventory__empty-title">No accident vehicles right now</h2>
                <p>There are no accident-damaged vehicles on offer at the moment. You can browse our <a href="/vehicles/">other vehicles</a>.</p>
<?php else: ?>
                <h2 class="inventory__empty-title">Vehicles Coming Soon</h2>
                <p>We're preparing our latest available vehicles. Check back soon.</p>
<?php endif; ?>
<?php endif; ?>
            </div>
<?php else: ?>
            <ul class="vehicle-grid">
<?php foreach ($result['cards'] as $i => $v): ?>
<?php require dirname(__DIR__, 2) . '/includes/vehicle_card.php'; ?>
<?php endforeach; ?>
            </ul>

<?php if ($result['pages'] > 1): ?>
            <nav class="pager" aria-label="Pagination">
<?php if ($result['page'] > 1): ?>
                <a class="pager__link" href="<?= e($pageUrl($result['page'] - 1)) ?>" rel="prev">Previous</a>
<?php endif; ?>
                <ol class="pager__pages">
<?php $last = 0; foreach ($pages as $n): ?>
<?php if ($n - $last > 1): ?>
                    <li class="pager__gap" aria-hidden="true">…</li>
<?php endif; $last = $n; ?>
<?php if ($n === $result['page']): ?>
                    <li><span class="pager__num is-current" aria-current="page"><?= $n ?></span></li>
<?php else: ?>
                    <li><a class="pager__num" href="<?= e($pageUrl($n)) ?>" aria-label="Page <?= $n ?>"><?= $n ?></a></li>
<?php endif; ?>
<?php endforeach; ?>
                </ol>
<?php if ($result['page'] < $result['pages']): ?>
                <a class="pager__link" href="<?= e($pageUrl($result['page'] + 1)) ?>" rel="next">Next</a>
<?php endif; ?>
            </nav>
<?php endif; ?>
<?php endif; ?>
        </section>
    </div>
</div>
