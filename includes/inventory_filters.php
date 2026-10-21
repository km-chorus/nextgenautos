<?php

declare(strict_types=1);

/**
 * The Vehicles page filter panel (spec 11): a plain GET form to /vehicles/. Works without JavaScript; assets/js/inventory.js only
 * turns it into a drawer on small screens and vehicle-search.js narrows the Model list to the chosen Make. Every list comes from real
 * data (PublicVehicleService::searchOptions). Presentation only.
 *
 * Expects: $searchOptions, $filters (validated), $vehiclesPath, $active (bool: any filter set).
 */

$brands = $searchOptions['brands'];
$models = $searchOptions['models'];
$years = $searchOptions['years'];
$on = static fn (bool $x): string => $x ? ' selected' : '';
?>
<form class="inv-filters__form" action="<?= e($vehiclesPath) ?>" method="get" role="search" aria-label="Filter vehicles" data-vsearch>
    <div class="inv-filters__head">
        <h2 class="inv-filters__title" id="inv-filters-title">Filters</h2>
        <button class="inv-filters__close" type="button" data-inv-close aria-label="Close filters"><?= public_icon('close') ?></button>
    </div>

    <div class="inv-field">
        <label for="f-q">Search</label>
        <input id="f-q" name="q" type="search" maxlength="80" autocomplete="off" placeholder="Make, model or trim" value="<?= e((string) $filters['q']) ?>">
    </div>
    <div class="inv-field">
        <label for="f-brand">Make</label>
        <select id="f-brand" name="brand" data-vsearch-brand>
            <option value="">Any make</option>
<?php foreach ($brands as $b): ?>
            <option value="<?= e($b['slug']) ?>"<?= $on($filters['brand'] === $b['slug']) ?>><?= e($b['name']) ?></option>
<?php endforeach; ?>
        </select>
    </div>
    <div class="inv-field">
        <label for="f-model">Model</label>
        <select id="f-model" name="model" data-vsearch-model<?= $models === [] ? ' disabled' : '' ?>>
            <option value="">Any model</option>
<?php foreach ($models as $slug => $group): ?>
            <optgroup label="<?= e($group['name']) ?>" data-brand="<?= e((string) $slug) ?>">
<?php foreach ($group['models'] as $m): ?>
                <option value="<?= e($m) ?>"<?= $on($filters['model'] !== null && strcasecmp($filters['model'], $m) === 0 && ($filters['brand'] === null || $filters['brand'] === (string) $slug)) ?>><?= e($m) ?></option>
<?php endforeach; ?>
            </optgroup>
<?php endforeach; ?>
        </select>
    </div>
    <div class="inv-field">
        <label for="f-year">Year</label>
        <select id="f-year" name="year"<?= $years === [] ? ' disabled' : '' ?>>
            <option value="">Any year</option>
<?php foreach ($years as $y): ?>
            <option value="<?= (int) $y ?>"<?= $on($filters['year'] === $y) ?>><?= (int) $y ?></option>
<?php endforeach; ?>
        </select>
    </div>
    <fieldset class="inv-field inv-field--pair">
        <legend>Price (GHS)</legend>
        <div class="inv-pair">
            <div>
                <label class="sr-only" for="f-min">Minimum price in GHS</label>
                <input id="f-min" name="min_price" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="10" placeholder="Min" value="<?= e((string) $filters['min_price']) ?>">
            </div>
            <div>
                <label class="sr-only" for="f-max">Maximum price in GHS</label>
                <input id="f-max" name="max_price" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="10" placeholder="Max" value="<?= e((string) $filters['max_price']) ?>">
            </div>
        </div>
    </fieldset>
    <div class="inv-field">
        <label for="f-availability">Availability</label>
        <select id="f-availability" name="availability">
            <option value="">Available and reserved</option>
            <option value="available"<?= $on($filters['availability'] === 'available') ?>>Available</option>
            <option value="reserved"<?= $on($filters['availability'] === 'reserved') ?>>Reserved</option>
        </select>
    </div>
<?php if ($filters['sort'] !== 'newest'): ?>
    <input type="hidden" name="sort" value="<?= e($filters['sort']) ?>">
<?php endif; ?>

    <div class="inv-filters__actions">
        <button class="btn btn--accent" type="submit">Apply Filters</button>
<?php if ($active): ?>
        <a class="inv-filters__clear" href="<?= e($vehiclesPath) ?>">Clear Filters</a>
<?php endif; ?>
    </div>
</form>
