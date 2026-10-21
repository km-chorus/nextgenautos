<?php

declare(strict_types=1);

/**
 * Delete a vehicle image (spec 07_spec_vehicle_image.md).
 *   GET  = the confirmation page (vehicle and image ids in the URL; the image must belong to
 *          that vehicle). It only reads; opening this URL deletes nothing.
 *   POST = the deletion, after explicit confirmation, with a valid CSRF token.
 * Protected: requireAdmin() runs first.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/VehicleImageController.php';

$admin = requireAdmin();
$controller = new VehicleImageController();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    ignore_user_abort(true);   // finish the record and file removal even if the browser disconnects
    $view = $controller->delete($admin, $_POST, admin_client_ip(), admin_user_agent());
    if (isset($view['redirect'])) {
        admin_redirect($view['redirect'], 303);
    }
    require dirname(__DIR__) . '/includes/admin/vehicle_problem.php';
}

$view = $controller->confirmDelete($admin, $_GET['vehicle'] ?? null, $_GET['id'] ?? null);
if ($view['problem'] !== null) {
    require dirname(__DIR__) . '/includes/admin/vehicle_problem.php';
}

$vehicle   = $view['vehicle'];
$image     = $view['image'];
$next      = $view['next'];
$vehicleId = (int) $vehicle['id'];
$imageId   = (int) $image['id'];
$label     = admin_vehicle_label($vehicle['year'], $vehicle['brand_name'], $vehicle['model'], $vehicle['trim']);
$isPrimary = (int) $image['is_primary'] === 1;
$thumb     = admin_vehicle_image_url((string) $image['thumb_path']);

$pageTitle   = 'Delete image';
$activeNav   = 'vehicles';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Vehicles', 'vehicles.php'], [$label, 'vehicle-view.php?id=' . $vehicleId], ['Images', 'vehicle-images.php?id=' . $vehicleId], ['Delete', null]];

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<section class="card confirm-card" aria-labelledby="delete-heading">
    <h2 class="card__title" id="delete-heading">Delete this image of <?= e($label) ?>?</h2>

    <div class="confirm-image">
        <?php if ($thumb !== null): ?>
            <img src="<?= $thumb ?>" alt="<?= e(VehicleImageService::altFor($image, $label)) ?>" width="640" height="480">
        <?php endif; ?>
        <p class="gallery__name"><?= e((string) $image['original_filename']) ?></p>
    </div>

    <?php if ($isPrimary): ?>
        <div class="section-alert">
            <?= admin_alert('warning', 'This is the primary image.', 'It is the main photograph of this vehicle.') ?>
        </div>
    <?php endif; ?>

    <p class="text-muted">This action cannot be undone. The image and its thumbnail will be removed permanently.</p>

    <form method="post" action="vehicle-image-delete.php" class="stack" data-single-submit>
        <?= csrf_field() ?>
        <input type="hidden" name="vehicle_id" value="<?= $vehicleId ?>">
        <input type="hidden" name="image_id" value="<?= $imageId ?>">
        <input type="hidden" name="confirm" value="yes">
        <?php if ($isPrimary): ?>
            <?php if ($next !== null): ?>
                <label class="check">
                    <input type="checkbox" name="promote_next" value="1" checked>
                    <span class="check__text">Make the next image (<?= e((string) $next['original_filename']) ?>) the new primary image</span>
                </label>
                <p class="hint">If you clear this box, the vehicle will have no primary image until you choose one.</p>
            <?php else: ?>
                <p class="hint">This is the only image, so the vehicle will have no primary image afterwards.</p>
            <?php endif; ?>
        <?php endif; ?>
        <div class="cluster form-actions">
            <button type="submit" class="btn btn--danger" data-submit-button><?= $isPrimary ? 'Delete primary image' : 'Delete image' ?></button>
            <a class="btn btn--ghost" href="vehicle-images.php?id=<?= $vehicleId ?>">Cancel</a>
        </div>
    </form>
</section>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
