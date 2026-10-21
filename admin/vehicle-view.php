<?php

declare(strict_types=1);

/**
 * Vehicle details (spec 06_spec_vehicle_managt): read-only view of one vehicle
 * with the actions available for it. The id comes from the URL (untrusted) and is
 * validated by VehicleController. Protected: requireAdmin() runs first.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/VehicleController.php';

$admin = requireAdmin();

$view = (new VehicleController())->show($admin, $_GET['id'] ?? null);
if ($view['problem'] !== null) {
    require dirname(__DIR__) . '/includes/admin/vehicle_problem.php';
}

$vehicle   = $view['vehicle'];
$images    = $view['images'];
$flash     = $view['flash'];
$vehicleId = (int) $vehicle['id'];
$label     = admin_vehicle_label($vehicle['year'], $vehicle['brand_name'], $vehicle['model'], $vehicle['trim']);

/** "Not stated" for information that was left empty (nothing is invented). */
$notStated = '<span class="text-muted">Not stated</span>';
$show = static fn (mixed $value): string => trim((string) $value) === '' ? $notStated : e((string) $value);

$mileage = $vehicle['mileage'] === null ? null : number_format((int) $vehicle['mileage']) . ' ' . $vehicle['mileage_unit'];

$pageTitle   = $label;
$activeNav   = 'vehicles';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Vehicles', 'vehicles.php'], [$label, null]];

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<div class="stack stack--lg">

    <?php if ($flash !== null): ?>
        <?= admin_alert($flash['type'], $flash['text'], '', true) ?>
    <?php endif; ?>

    <section class="card" aria-labelledby="actions-heading">
        <div class="card__row">
            <h2 class="card__title" id="actions-heading">Inventory</h2>
            <?= admin_status_badge((string) $vehicle['availability_status']) ?>
        </div>
        <dl class="facts facts--wide">
            <div><dt>Status</dt><dd><?= e(admin_status_label((string) $vehicle['availability_status'])) ?></dd></div>
            <div><dt>Published on website</dt><dd><?= (int) $vehicle['is_published'] === 1 ? 'Yes' : 'No' ?></dd></div>
            <div><dt>Created</dt><dd><?= e(admin_format_datetime((string) $vehicle['created_at'])) ?></dd></div>
            <div><dt>Last updated</dt><dd><?= e(admin_format_datetime((string) $vehicle['updated_at'])) ?></dd></div>
        </dl>
        <div class="cluster form-actions">
            <a class="btn btn--primary" href="vehicle-edit.php?id=<?= $vehicleId ?>">Edit<span class="sr-only"> <?= e($label) ?></span></a>
            <form method="post" action="vehicle-status.php" class="status-form">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $vehicleId ?>">
                <input type="hidden" name="from" value="view">
                <label class="sr-only" for="view-status">Change status</label>
                <select class="select select--sm" id="view-status" name="status">
                    <?php foreach (VehicleValidator::STATUSES as $status): ?>
                        <option value="<?= e($status) ?>"<?= $vehicle['availability_status'] === $status ? ' selected' : '' ?>><?= e(admin_status_label($status)) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn--secondary btn--sm">Change status</button>
            </form>
            <a class="btn btn--secondary btn--text-danger" href="vehicle-delete.php?id=<?= $vehicleId ?>">Delete<span class="sr-only"> <?= e($label) ?></span></a>
            <a class="btn btn--ghost" href="vehicles.php">Back to Vehicles</a>
        </div>
    </section>

    <section class="card" aria-labelledby="identity-heading">
        <h2 class="card__title" id="identity-heading">Identity</h2>
        <dl class="facts facts--wide">
            <div><dt>Brand</dt><dd><?= e((string) $vehicle['brand_name']) ?><?= $vehicle['brand_status'] !== 'active' ? ' <span class="text-muted">(brand is inactive)</span>' : '' ?></dd></div>
            <div><dt>Year</dt><dd><?= e((string) $vehicle['year']) ?></dd></div>
            <div><dt>Model</dt><dd><?= e((string) $vehicle['model']) ?></dd></div>
            <div><dt>Trim</dt><dd><?= $show($vehicle['trim']) ?></dd></div>
            <div><dt>URL (slug)</dt><dd><code class="slug"><?= e((string) $vehicle['slug']) ?></code></dd></div>
        </dl>
    </section>

    <section class="card" aria-labelledby="pricing-heading">
        <h2 class="card__title" id="pricing-heading">Pricing</h2>
        <dl class="facts facts--wide">
            <div><dt>Price</dt><dd><?= e(admin_format_price((string) $vehicle['price'], (string) $vehicle['currency'])) ?></dd></div>
            <div><dt>Currency</dt><dd><?= e((string) $vehicle['currency']) ?></dd></div>
            <div><dt>Duty included in price</dt><dd><?= (int) $vehicle['duty_included'] === 1 ? 'Yes' : 'No' ?></dd></div>
        </dl>
        <p class="hint">The website does not calculate Ghana import duty. Customers use the approved external duty calculator.</p>
    </section>

    <section class="card" aria-labelledby="specs-heading">
        <h2 class="card__title" id="specs-heading">Specifications</h2>
        <dl class="facts facts--wide">
            <div><dt>Engine size</dt><dd><?= $show($vehicle['engine_size']) ?></dd></div>
            <div><dt>Mileage</dt><dd><?= $mileage === null ? $notStated : e($mileage) ?></dd></div>
            <div><dt>Transmission</dt><dd><?= $show($vehicle['transmission']) ?></dd></div>
            <div><dt>Fuel type</dt><dd><?= $show($vehicle['fuel_type']) ?></dd></div>
            <div><dt>Drive type</dt><dd><?= $show($vehicle['drive_type']) ?></dd></div>
        </dl>
        <h3 class="settings-form__title spaced">Key features</h3>
        <?php if ($vehicle['features'] === []): ?>
            <p class="text-muted">No features listed.</p>
        <?php else: ?>
            <ul class="feature-list">
                <?php foreach ($vehicle['features'] as $feature): ?>
                    <li><?= e($feature) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card" aria-labelledby="description-heading">
        <h2 class="card__title" id="description-heading">Description</h2>
        <?php if (trim((string) $vehicle['description']) === ''): ?>
            <p class="text-muted">No description.</p>
        <?php else: ?>
            <p class="prose"><?= nl2br(e((string) $vehicle['description']), false) ?></p>
        <?php endif; ?>
    </section>

    <section class="card" aria-labelledby="shipping-heading">
        <h2 class="card__title" id="shipping-heading">Shipping information</h2>
        <?php if (trim((string) $vehicle['shipping_info']) === ''): ?>
            <p class="text-muted">No shipping information.</p>
        <?php else: ?>
            <p class="prose"><?= nl2br(e((string) $vehicle['shipping_info']), false) ?></p>
        <?php endif; ?>
    </section>

    <section class="card" aria-labelledby="images-heading">
        <div class="card__row">
            <h2 class="card__title" id="images-heading">Images</h2>
            <a class="btn btn--secondary btn--sm" href="vehicle-images.php?id=<?= $vehicleId ?>">Manage images<span class="sr-only"> for <?= e($label) ?></span></a>
        </div>
        <?php if ($images === []): ?>
            <?= admin_empty_state('No vehicle images yet.', "Upload images to create this vehicle's gallery.", ['label' => 'Upload images', 'icon' => 'plus', 'href' => 'vehicle-images.php?id=' . $vehicleId], 'car') ?>
        <?php else: ?>
            <?php if (!array_filter($images, static fn (array $i): bool => (int) $i['is_primary'] === 1)): ?>
                <p class="hint">Primary image not set.</p>
            <?php endif; ?>
            <ul class="thumb-strip">
                <?php foreach ($images as $image): ?>
                    <?php $thumb = admin_vehicle_image_url((string) $image['thumb_path']); ?>
                    <?php if ($thumb !== null): ?>
                        <li class="thumb-strip__item">
                            <img src="<?= $thumb ?>" alt="<?= e(trim((string) ($image['alt_text'] ?? '')) !== '' ? (string) $image['alt_text'] : $label) ?>" width="640" height="480" loading="lazy" decoding="async">
                            <?php if ((int) $image['is_primary'] === 1): ?><span class="thumb-strip__flag">Primary</span><?php endif; ?>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
