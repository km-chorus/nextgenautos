<?php

declare(strict_types=1);

/**
 * Parts and Accessories: list, search, filters and pages (spec 16, phase 1).
 *
 * Presentation only: data comes from PartController -> PartService -> PartRepository -> PDO. Protected: requireAdmin()
 * runs before anything else. Search, filters and paging use GET; publish, stock and archive are POSTs to part-state.php.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/PartController.php';

$admin = requireAdmin();

$view = (new PartController())->index($admin, $_GET);
if ($view['problem'] !== null) {
    require dirname(__DIR__) . '/includes/admin/part_problem.php';
}

$parts   = $view['parts'];
$filters = $view['filters'];
$flash   = $view['flash'];

$pageUrl = static function (int $page) use ($filters): string {
    $query = array_filter([
        'q' => $filters['q'], 'category' => (string) ($filters['category'] ?? ''), 'brand' => (string) ($filters['brand'] ?? ''),
        'published' => $filters['published'] === 'all' ? '' : $filters['published'], 'stock' => $filters['stock'] === 'all' ? '' : $filters['stock'],
        'page' => $page > 1 ? (string) $page : '',
    ], 'strlen');

    return 'parts.php' . ($query === [] ? '' : '?' . http_build_query($query));
};
/** Hidden fields that bring the list back to the same filters after a state change. */
$keepFilters = static function () use ($filters): string {
    $out = '';
    foreach (['q' => $filters['q'], 'f_category' => (string) ($filters['category'] ?? ''), 'f_brand' => (string) ($filters['brand'] ?? ''),
                 'f_published' => $filters['published'], 'f_stock' => $filters['stock'], 'page' => (string) $filters['page']] as $name => $value) {
        $out .= '<input type="hidden" name="' . $name . '" value="' . e($value) . '">';
    }

    return $out;
};

$pageTitle   = 'Parts';
$activeNav   = 'parts';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Parts', null]];

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<div class="stack stack--lg">

    <?php if ($flash !== null): ?>
        <?= admin_alert($flash['type'], $flash['text'], '', true) ?>
    <?php endif; ?>

    <div class="page-header">
        <p class="page-header__text">Car parts and accessories: price, condition, stock, the vehicles each part fits, and publication on the website.</p>
        <div class="cluster">
            <a class="btn btn--secondary" href="part-categories.php"><?= admin_icon('tag') ?>Categories</a>
            <a class="btn btn--primary" href="part-create.php"><?= admin_icon('plus') ?>Add Part</a>
        </div>
    </div>

    <?php if ($view['total_all'] === 0): ?>
        <section class="card card--flush" aria-labelledby="parts-heading">
            <h2 class="sr-only" id="parts-heading">Parts list</h2>
            <?= admin_empty_state('No parts have been added yet.', 'Add a part or accessory to start the catalogue. It appears on the homepage once it is published.',
                ['label' => 'Add Part', 'icon' => 'plus', 'href' => 'part-create.php'], 'wrench') ?>
        </section>
    <?php else: ?>
        <section class="card card--flush" aria-labelledby="parts-heading">
            <div class="card__header"><h2 class="card__heading" id="parts-heading">Parts list</h2></div>

            <form class="filter-bar filter-bar--parts" method="get" action="parts.php" role="search" aria-label="Search and filter parts">
                <div class="field filter-bar__search">
                    <label class="label" for="part-q">Search</label>
                    <input class="input" type="search" id="part-q" name="q" value="<?= e($filters['q']) ?>" maxlength="<?= PartValidator::SEARCH_MAX ?>" autocomplete="off" placeholder="Name or part number">
                </div>
                <div class="field">
                    <label class="label" for="part-category">Type</label>
                    <select class="select" id="part-category" name="category">
                        <option value="">All types</option>
                        <?php foreach ($view['categories'] as $c): ?>
                            <option value="<?= $c['id'] ?>"<?= $filters['category'] === $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="label" for="part-brand">Fits make</label>
                    <select class="select" id="part-brand" name="brand">
                        <option value="">All makes</option>
                        <?php foreach ($view['brands'] as $b): ?>
                            <option value="<?= $b['id'] ?>"<?= $filters['brand'] === $b['id'] ? ' selected' : '' ?>><?= e($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="label" for="part-published">Website</label>
                    <select class="select" id="part-published" name="published">
                        <option value="all">All</option>
                        <option value="yes"<?= $filters['published'] === 'yes' ? ' selected' : '' ?>>Published</option>
                        <option value="no"<?= $filters['published'] === 'no' ? ' selected' : '' ?>>Not published</option>
                    </select>
                </div>
                <div class="field">
                    <label class="label" for="part-stock">Stock</label>
                    <select class="select" id="part-stock" name="stock">
                        <option value="all">All</option>
                        <?php foreach (PartValidator::STOCK as $key => $label): ?>
                            <option value="<?= e($key) ?>"<?= $filters['stock'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-bar__actions">
                    <button type="submit" class="btn btn--secondary">Apply</button>
                    <?php if ($view['filtered']): ?>
                        <a class="btn btn--ghost" href="parts.php">Clear</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($parts === []): ?>
                <?= admin_empty_state('No parts match your current search or filters.', 'Try different words, or remove a filter.',
                    ['label' => 'Clear search and filters', 'href' => 'parts.php'], 'wrench') ?>
            <?php else: ?>
                <p class="result-count" role="status">
                    Showing <?= $view['from'] ?> to <?= $view['to'] ?> of <?= e(admin_format_count($view['total'])) ?> <?= $view['total'] === 1 ? 'part' : 'parts' ?><?= $view['filtered'] ? ' found' : '' ?>
                </p>
                <div class="table-wrap" role="region" aria-labelledby="parts-heading" tabindex="0">
                    <table class="table table--stack table--parts">
                        <thead>
                            <tr>
                                <th scope="col">Part</th>
                                <th scope="col">Type</th>
                                <th scope="col">Fits</th>
                                <th scope="col">Price</th>
                                <th scope="col">Stock</th>
                                <th scope="col" class="col-actions"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($parts as $part): $id = (int) $part['id']; $label = (string) $part['name']; $published = (int) $part['is_published'] === 1; ?>
                                <tr>
                                    <td class="cell-primary">
                                        <div class="cell-text">
                                            <p class="cell-title"><a href="part-edit.php?id=<?= $id ?>"><?= e($label) ?></a></p>
                                            <p class="cell-sub"><?= e(PartValidator::CONDITIONS[$part['condition']] ?? '') ?><?= $part['part_number'] !== null ? ' · ' . e((string) $part['part_number']) : '' ?> · <?= $published ? 'Published on website' : 'Not published' ?></p>
                                        </div>
                                    </td>
                                    <td data-label="Type"><?= e((string) $part['category_name']) ?></td>
                                    <td data-label="Fits"><?= e((string) $part['fits']) ?></td>
                                    <td data-label="Price" class="nowrap"><?= e(admin_format_price((string) $part['price'], (string) $part['currency'])) ?></td>
                                    <td data-label="Stock" class="nowrap"><?= e(PartValidator::STOCK[$part['stock_status']] ?? '') ?></td>
                                    <td class="col-actions">
                                        <div class="row-actions">
                                            <a class="btn btn--ghost btn--sm" href="part-edit.php?id=<?= $id ?>">Edit<span class="sr-only"> <?= e($label) ?></span></a>
                                            <form method="post" action="part-state.php" class="status-form">
                                                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><?= $keepFilters() ?>
                                                <button type="submit" class="btn btn--secondary btn--sm" name="action" value="<?= $published ? 'unpublish' : 'publish' ?>"><?= $published ? 'Unpublish' : 'Publish' ?><span class="sr-only"> <?= e($label) ?></span></button>
                                            </form>
                                            <form method="post" action="part-state.php" class="status-form">
                                                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><?= $keepFilters() ?>
                                                <button type="submit" class="btn btn--ghost btn--sm" name="action" value="<?= $part['stock_status'] === 'in_stock' ? 'out_of_stock' : 'in_stock' ?>">Mark <?= $part['stock_status'] === 'in_stock' ? 'out of stock' : 'in stock' ?><span class="sr-only">: <?= e($label) ?></span></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($view['pages'] > 1): ?>
                    <nav class="pager" aria-label="Parts list pages">
                        <?php if ($view['page'] > 1): ?>
                            <a class="btn btn--secondary btn--sm" href="<?= e($pageUrl($view['page'] - 1)) ?>" rel="prev">Previous</a>
                        <?php else: ?>
                            <span class="btn btn--secondary btn--sm" aria-disabled="true">Previous</span>
                        <?php endif; ?>
                        <p class="pager__status">Page <?= $view['page'] ?> of <?= $view['pages'] ?></p>
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
