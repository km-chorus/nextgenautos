<?php

declare(strict_types=1);

/**
 * The public vehicle search form (homepage hero and Vehicles page). A plain GET form to /vehicles/:
 * it works without JavaScript (assets/js/vehicle-search.js only narrows the Model list to the chosen
 * Make). Every list comes from real data (PublicVehicleService::searchOptions). Presentation only.
 *
 * Expects: $searchOptions (see the service), $filters (the validated current filters), $searchId (a unique id prefix),
 *          $vehiclesPath (the Vehicles route path).
 */

$brands = $searchOptions['brands'];
$models = $searchOptions['models'];
$years = $searchOptions['years'];
$prices = $searchOptions['prices'];
$chosen = static fn (bool $on): string => $on ? ' selected' : '';
?>
<form class="vsearch" action="<?= e($vehiclesPath) ?>" method="get" role="search" aria-label="Search vehicles" data-vsearch>
    <div class="vsearch__field">
        <label for="<?= e($searchId) ?>-brand">Make</label>
        <select id="<?= e($searchId) ?>-brand" name="brand" data-vsearch-brand>
            <option value="">Any make</option>
<?php foreach ($brands as $b): ?>
            <option value="<?= e($b['slug']) ?>"<?= $chosen($filters['brand'] === $b['slug']) ?>><?= e($b['name']) ?></option>
<?php endforeach; ?>
        </select>
    </div>
    <div class="vsearch__field">
        <label for="<?= e($searchId) ?>-model">Model</label>
        <select id="<?= e($searchId) ?>-model" name="model" data-vsearch-model<?= $models === [] ? ' disabled' : '' ?>>
            <option value="">Any model</option>
<?php foreach ($models as $slug => $group): ?>
            <optgroup label="<?= e($group['name']) ?>" data-brand="<?= e((string) $slug) ?>">
<?php foreach ($group['models'] as $m): ?>
                <option value="<?= e($m) ?>"<?= $chosen($filters['model'] !== null && strcasecmp($filters['model'], $m) === 0 && ($filters['brand'] === null || $filters['brand'] === (string) $slug)) ?>><?= e($m) ?></option>
<?php endforeach; ?>
            </optgroup>
<?php endforeach; ?>
        </select>
    </div>
    <div class="vsearch__field">
        <label for="<?= e($searchId) ?>-year">Year</label>
        <select id="<?= e($searchId) ?>-year" name="year"<?= $years === [] ? ' disabled' : '' ?>>
            <option value="">Any year</option>
<?php foreach ($years as $y): ?>
            <option value="<?= (int) $y ?>"<?= $chosen($filters['year'] === $y) ?>><?= (int) $y ?></option>
<?php endforeach; ?>
        </select>
    </div>
    <div class="vsearch__field">
        <label for="<?= e($searchId) ?>-price">Price</label>
        <select id="<?= e($searchId) ?>-price" name="max_price"<?= $prices === [] ? ' disabled' : '' ?>>
            <option value="">Any price</option>
<?php foreach ($prices as $p): ?>
            <option value="<?= (int) $p ?>"<?= $chosen($filters['max_price'] === $p) ?>>Up to GHS <?= e(number_format($p)) ?></option>
<?php endforeach; ?>
        </select>
    </div>
    <button class="btn btn--accent vsearch__submit" type="submit">Search Vehicles</button>
</form>
