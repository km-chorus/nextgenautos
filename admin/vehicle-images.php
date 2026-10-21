<?php

declare(strict_types=1);

/**
 * Vehicle images (spec 07_spec_vehicle_image.md): upload form and gallery management for
 * one vehicle. Presentation only: data comes from VehicleImageController ->
 * VehicleImageService -> VehicleImageRepository -> PDO; uploads are processed by
 * ImageService. The vehicle id comes from the URL (untrusted) and is validated by the
 * controller. Protected: requireAdmin() runs first.
 *
 * Every change on this page is a POST form to its own endpoint (vehicle-image-upload.php,
 * vehicle-image-update.php, vehicle-image-delete.php) with a CSRF token.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/VehicleImageController.php';

$admin = requireAdmin();

$view = (new VehicleImageController())->manage($admin, $_GET['id'] ?? null);
if ($view['problem'] !== null) {
    require dirname(__DIR__) . '/includes/admin/vehicle_problem.php';
}

$vehicle   = $view['vehicle'];
$images    = $view['images'];
$flash     = $view['flash'];
$report    = $view['report'];
$vehicleId = (int) $vehicle['id'];
$label     = admin_vehicle_label($vehicle['year'], $vehicle['brand_name'], $vehicle['model'], $vehicle['trim']);
$total     = count($images);
$missing   = $view['missing'];            // PHP components this server lacks (empty when image processing can work)
$canUpload = $missing === [];
// The largest file the server will really accept: the application's 10 MB, or less if PHP is configured lower.
$serverLimit = (int) $view['serverLimit'];
$effectiveMax = ($serverLimit > 0 && $serverLimit < ImageValidator::MAX_BYTES) ? $serverLimit : ImageValidator::MAX_BYTES;
$limitText = (fmod($effectiveMax / 1048576, 1) == 0.0 ? (string) intdiv($effectiveMax, 1048576) : (string) round($effectiveMax / 1048576, 1)) . ' MB';

$pageTitle   = 'Vehicle images';
$activeNav   = 'vehicles';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Vehicles', 'vehicles.php'], [$label, 'vehicle-view.php?id=' . $vehicleId], ['Images', null]];

/** The full gallery order after moving the image at $from to $to (ids, for the reorder form). */
$orderAfterMove = static function (int $from, int $to) use ($images): array {
    $ids = array_map(static fn (array $i): string => (string) $i['id'], $images);
    $moved = array_splice($ids, $from, 1);
    array_splice($ids, $to, 0, $moved);

    return $ids;
};

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<div class="stack stack--lg">

    <?php if ($flash !== null): ?>
        <?= admin_alert($flash['type'], $flash['text'], '', true) ?>
    <?php endif; ?>

    <?php if ($report !== []): ?>
        <section class="card upload-report" aria-labelledby="report-heading" data-login-notice tabindex="-1">
            <h2 class="card__title" id="report-heading">Upload results</h2>
            <ul class="report-list">
                <?php foreach ($report as $item): ?>
                    <li class="report-item report-item--<?= $item['ok'] ? 'ok' : 'failed' ?>">
                        <span class="report-item__state"><?= $item['ok'] ? 'Uploaded' : 'Not uploaded' ?></span>
                        <span class="report-item__name"><?= e($item['name'] !== '' ? $item['name'] : 'Upload') ?></span>
                        <?php if (!$item['ok'] && is_string($item['message']) && $item['message'] !== ''): ?>
                            <span class="report-item__message"><?= e($item['message']) ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <div class="page-header">
        <p class="page-header__text">Photographs for <strong><?= e($label) ?></strong>. <?= $total === 0 ? 'No images yet.' : e((string) $total) . ($total === 1 ? ' image' : ' images') . '.' ?></p>
        <a class="btn btn--secondary" href="vehicle-view.php?id=<?= $vehicleId ?>">Back to vehicle</a>
    </div>

    <!-- Upload -->
    <section class="card" aria-labelledby="upload-heading">
        <h2 class="card__title" id="upload-heading">Upload images</h2>
        <?php if (!$canUpload): ?>
            <div class="section-alert"><?= admin_alert('warning', 'Image uploads are not available on this server yet', VehicleImageController::unavailableMessage($missing)) ?></div>
        <?php elseif ($effectiveMax < ImageValidator::MAX_BYTES): ?>
            <div class="section-alert"><?= admin_alert('warning', 'This server accepts images up to ' . $limitText . ' only', 'PHP is configured below the application\'s ' . (int) (ImageValidator::MAX_BYTES / 1048576) . ' MB limit (upload_max_filesize / post_max_size). Large photographs are reduced in the browser before upload when possible. To allow the full size, raise those two settings in php.ini and restart the PHP server.') ?></div>
        <?php endif; ?>
        <form method="post" action="vehicle-image-upload.php?id=<?= $vehicleId ?>" enctype="multipart/form-data" class="upload-form"
              data-image-upload data-max-files="<?= (int) $view['maxFiles'] ?>" data-max-bytes="<?= $effectiveMax ?>" data-max-total="<?= ImageService::iniBytes('post_max_size') ?>" novalidate data-single-submit>
            <?= csrf_field() ?>
            <div class="field">
                <label class="label" for="image-files">Choose one or more images</label>
                <input class="input input--file" type="file" id="image-files" name="images[]" accept="image/jpeg,image/png,image/webp" multiple
                       aria-describedby="image-files-hint image-files-status" required<?= $canUpload ? '' : ' disabled' ?>>
                <p class="hint" id="image-files-hint">JPEG, PNG or WebP. Up to <?= (int) $view['maxFiles'] ?> images at a time, each up to <?= e($limitText) ?> and at least <?= ImageValidator::MIN_LONG_SIDE ?> x <?= ImageValidator::MIN_SHORT_SIDE ?> pixels. Images are resized to fit 1600 pixels and saved as WebP; the original is not kept.</p>
                <p class="hint" id="image-files-status" role="status" aria-live="polite" data-upload-status></p>
            </div>
            <ul class="upload-previews" data-upload-previews aria-label="Selected images"></ul>
            <div class="cluster form-actions">
                <button type="submit" class="btn btn--primary" data-submit-button<?= $canUpload ? '' : ' disabled' ?>>Upload images</button>
            </div>
        </form>
    </section>

    <!-- Gallery -->
    <section class="card" aria-labelledby="gallery-heading">
        <div class="card__row">
            <h2 class="card__title" id="gallery-heading">Gallery</h2>
            <?php if ($total > 0): ?>
                <?= $view['primary_id'] !== null ? admin_status_badge('PRIMARY_SET') : admin_status_badge('PRIMARY_NOT_SET') ?>
            <?php endif; ?>
        </div>

        <?php if ($total === 0): ?>
            <?= admin_empty_state('No vehicle images yet.', "Upload images to create this vehicle's gallery.", null, 'car') ?>
        <?php else: ?>
            <?php if ($view['primary_id'] === null): ?>
                <div class="section-alert"><?= admin_alert('warning', 'Primary image not set', 'Choose "Set as primary" on one image. Until then this vehicle shows no main photograph.') ?></div>
            <?php endif; ?>

            <ol class="gallery">
                <?php foreach ($images as $index => $image): ?>
                    <?php
                    $imageId   = (int) $image['id'];
                    $isPrimary = (int) $image['is_primary'] === 1;
                    $thumb     = admin_vehicle_image_url((string) $image['thumb_path']);
                    $alt       = VehicleImageService::altFor($image, $label);
                    $position  = $index + 1;
                    ?>
                    <li class="gallery__item<?= $isPrimary ? ' gallery__item--primary' : '' ?>" id="image-<?= $imageId ?>">
                        <div class="gallery__media">
                            <?php if ($thumb !== null): ?>
                                <img src="<?= $thumb ?>" alt="<?= e($alt) ?>" width="640" height="480" loading="lazy" decoding="async">
                            <?php else: ?>
                                <span class="gallery__missing"><?= admin_icon('alert-circle') ?>Image file unavailable</span>
                            <?php endif; ?>
                        </div>
                        <div class="gallery__body">
                            <div class="gallery__meta">
                                <?= $isPrimary ? admin_status_badge('PRIMARY') : admin_status_badge('GALLERY') ?>
                                <span class="gallery__position">Position <?= $position ?> of <?= $total ?></span>
                            </div>
                            <p class="gallery__name" title="<?= e((string) $image['original_filename']) ?>"><?= e((string) $image['original_filename']) ?></p>

                            <form method="post" action="vehicle-image-update.php" class="gallery__alt">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="type">
                                <input type="hidden" name="vehicle_id" value="<?= $vehicleId ?>">
                                <input type="hidden" name="image_id" value="<?= $imageId ?>">
                                <label class="label" for="type-<?= $imageId ?>">Photo type</label>
                                <div class="gallery__alt-row">
                                    <select class="select" id="type-<?= $imageId ?>" name="photo_type" aria-describedby="type-hint-<?= $imageId ?>">
                                        <?php foreach (VehicleImageService::TYPES as $typeKey => $typeLabel): ?>
                                            <option value="<?= e($typeKey) ?>"<?= ($image['photo_type'] ?? 'exterior') === $typeKey ? ' selected' : '' ?>><?= e($typeLabel) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn--secondary btn--sm">Save<span class="sr-only"> photo type for image <?= $position ?></span></button>
                                </div>
                                <p class="hint" id="type-hint-<?= $imageId ?>">The tab this photo appears under on the vehicle page.</p>
                            </form>

                            <form method="post" action="vehicle-image-update.php" class="gallery__alt">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="alt">
                                <input type="hidden" name="vehicle_id" value="<?= $vehicleId ?>">
                                <input type="hidden" name="image_id" value="<?= $imageId ?>">
                                <label class="label" for="alt-<?= $imageId ?>">Alt text</label>
                                <div class="gallery__alt-row">
                                    <input class="input" type="text" id="alt-<?= $imageId ?>" name="alt_text" value="<?= e((string) ($image['alt_text'] ?? '')) ?>"
                                           maxlength="<?= VehicleImageService::ALT_MAX ?>" autocomplete="off" placeholder="<?= e($label) ?>" aria-describedby="alt-hint-<?= $imageId ?>">
                                    <button type="submit" class="btn btn--secondary btn--sm">Save<span class="sr-only"> alt text for image <?= $position ?></span></button>
                                </div>
                                <p class="hint" id="alt-hint-<?= $imageId ?>">Describe what the photo shows. If left empty, the vehicle name is used.</p>
                            </form>

                            <div class="gallery__actions">
                                <?php if (!$isPrimary): ?>
                                    <form method="post" action="vehicle-image-update.php" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="primary">
                                        <input type="hidden" name="vehicle_id" value="<?= $vehicleId ?>">
                                        <input type="hidden" name="image_id" value="<?= $imageId ?>">
                                        <button type="submit" class="btn btn--secondary btn--sm">Set as primary<span class="sr-only"> image <?= $position ?></span></button>
                                    </form>
                                <?php endif; ?>
                                <?php foreach ([['up', 'Move up', $index > 0, $index - 1], ['down', 'Move down', $index < $total - 1, $index + 1]] as [$dir, $text, $enabled, $to]): ?>
                                    <?php if ($enabled): ?>
                                        <form method="post" action="vehicle-image-update.php" class="inline-form">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="reorder">
                                            <input type="hidden" name="vehicle_id" value="<?= $vehicleId ?>">
                                            <?php foreach ($orderAfterMove($index, $to) as $orderedId): ?>
                                                <input type="hidden" name="order[]" value="<?= e($orderedId) ?>">
                                            <?php endforeach; ?>
                                            <button type="submit" class="btn btn--ghost btn--sm"><?= e($text) ?><span class="sr-only"> image <?= $position ?></span></button>
                                        </form>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <a class="btn btn--ghost btn--sm btn--text-danger" href="vehicle-image-delete.php?vehicle=<?= $vehicleId ?>&amp;id=<?= $imageId ?>">Delete<span class="sr-only"> image <?= $position ?></span></a>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
</div>
<script src="<?= admin_asset('vendor/browser-image-compression.js') ?>"></script>
<script src="<?= admin_asset('js/vehicle-images.js') ?>"></script>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
