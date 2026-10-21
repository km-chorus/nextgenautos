<?php

declare(strict_types=1);

/**
 * Shared part form (Add Part and Edit Part, spec 16 phase 1). Presentation only: PartService validates everything again,
 * so these attributes are a convenience, not a control.
 *
 * Compatible vehicles are rows of make / model / years. Without JavaScript the form shows the saved rows plus blank
 * ones; part-form.js adds an "Add another vehicle" button and a Remove button per row.
 * Photos: new photos are uploaded with the form; on the Edit page the existing photos have their own small forms
 * (outside the main form, linked with the `form` attribute) to remove one or make it the main photo.
 *
 * Expected variables: string $formAction, array $values, array $errors, list $fitments, list $categoryChoices,
 * list $brandChoices, ?array $part (null on Add), string $submitLabel, string $cancelHref, ?string $formMessage,
 * array $photoLimits (maxBytes, maxTotal, limitText, notice).
 */

$v = static fn (string $key): string => (string) ($values[$key] ?? '');
$field = static function (string $name, bool $hint = false) use ($errors): array {
    $has = isset($errors[$name]);
    $id = 'part-' . str_replace('_', '-', $name);

    return [
        ($has ? ' aria-invalid="true"' : '') . ' aria-describedby="' . ($hint ? $id . '-hint ' : '') . $id . '-error"',
        '<p class="field-error" id="' . $id . '-error" role="alert"' . ($has ? '' : ' hidden') . '>' . admin_icon('alert-circle', 'icon--sm')
            . '<span data-error-text>' . e($errors[$name] ?? '') . '</span></p>',
    ];
};
[$catAttr, $catErr] = $field('category_id');
[$nameAttr, $nameErr] = $field('name');
[$numAttr, $numErr] = $field('part_number', true);
[, $condErr] = $field('condition');
[$stockAttr, $stockErr] = $field('stock_status');
[$priceAttr, $priceErr] = $field('price', true);
[$curAttr, $curErr] = $field('currency');
[$descAttr, $descErr] = $field('description', true);
[$photoAttr, $photoErr] = $field('photos', true);
$images = $part['images'] ?? [];
$rows = $fitments;
for ($n = 0; $n < PartController::BLANK_FITMENT_ROWS; $n++) {
    $rows[] = ['brand_id' => '', 'model' => '', 'year_from' => '', 'year_to' => ''];
}
$rows = array_slice($rows, 0, PartValidator::FITMENTS_MAX);
$room = PartValidator::PHOTOS_MAX - count($images);

/** One make / model / years row. $n is its index; $row its values; the template row uses "__N__". */
$fitmentRow = static function (int|string $n, array $row) use ($brandChoices, $errors): string {
    $error = is_int($n) ? ($errors['fitment_' . $n] ?? null) : null;
    $id = 'fit-' . $n;
    $options = '<option value="">Make</option>';
    foreach ($brandChoices as $b) {
        $options .= '<option value="' . $b['id'] . '"' . ((string) $b['id'] === $row['brand_id'] ? ' selected' : '') . '>' . e($b['name']) . ($b['status'] !== 'active' ? ' (inactive)' : '') . '</option>';
    }
    $invalid = $error !== null ? ' aria-invalid="true"' : '';
    $describe = ' aria-describedby="' . $id . '-error"';

    return '<li class="fitment-row" data-fitment-row>'
        . '<div class="field"><label class="label" for="' . $id . '-brand">Make</label><select class="select" id="' . $id . '-brand" name="fitments[' . $n . '][brand_id]"' . $invalid . $describe . '>' . $options . '</select></div>'
        . '<div class="field"><label class="label" for="' . $id . '-model">Model <span class="label__note">(empty = all models)</span></label><input class="input" type="text" id="' . $id . '-model" name="fitments[' . $n . '][model]" value="' . e($row['model']) . '" maxlength="' . PartValidator::MODEL_MAX . '" autocomplete="off"' . $invalid . $describe . '></div>'
        . '<div class="field"><label class="label" for="' . $id . '-from">From year</label><input class="input" type="text" inputmode="numeric" id="' . $id . '-from" name="fitments[' . $n . '][year_from]" value="' . e($row['year_from']) . '" maxlength="4" autocomplete="off" placeholder="Any"' . $invalid . $describe . '></div>'
        . '<div class="field"><label class="label" for="' . $id . '-to">To year</label><input class="input" type="text" inputmode="numeric" id="' . $id . '-to" name="fitments[' . $n . '][year_to]" value="' . e($row['year_to']) . '" maxlength="4" autocomplete="off" placeholder="Any"' . $invalid . $describe . '></div>'
        . '<button type="button" class="btn btn--ghost btn--sm btn--text-danger fitment-row__remove" data-fitment-remove hidden>Remove</button>'
        . '<p class="field-error fitment-row__error" id="' . $id . '-error" role="alert"' . ($error === null ? ' hidden' : '') . '>' . admin_icon('alert-circle', 'icon--sm') . '<span data-error-text>' . e((string) $error) . '</span></p>'
        . '</li>';
};
?>
<?php if (!empty($formMessage)): ?>
    <div class="section-alert" data-login-notice tabindex="-1"><?= admin_alert('error', $formMessage) ?></div>
<?php endif; ?>

<form method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data" class="part-form" id="part-form" novalidate data-single-submit
      data-image-upload data-max-files="<?= max(0, $room) ?>" data-max-bytes="<?= (int) $photoLimits['maxBytes'] ?>" data-max-total="<?= (int) $photoLimits['maxTotal'] ?>">
    <?= csrf_field() ?>
    <p class="hint"><span aria-hidden="true">*</span> Required field</p>

    <fieldset class="card form-section">
        <legend class="card__title">Part details</legend>
        <div class="form-row">
            <div class="field">
                <label class="label" for="part-name">Name <span aria-hidden="true">*</span></label>
                <input class="input" type="text" id="part-name" name="name" value="<?= e($v('name')) ?>" maxlength="<?= PartValidator::NAME_MAX ?>" autocomplete="off" required<?= $nameAttr ?>>
                <?= $nameErr ?>
            </div>
            <div class="field">
                <label class="label" for="part-category-id">Type <span aria-hidden="true">*</span></label>
                <select class="select" id="part-category-id" name="category_id" required<?= $catAttr ?>>
                    <option value="">Choose a type</option>
                    <?php foreach ($categoryChoices as $c): ?>
                        <option value="<?= $c['id'] ?>"<?= $v('category_id') === (string) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?><?= $c['status'] !== 'active' ? ' (inactive)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $catErr ?>
            </div>
        </div>
        <div class="form-row">
            <div class="field">
                <label class="label" for="part-part-number">Part number <span class="label__note">(optional)</span></label>
                <input class="input" type="text" id="part-part-number" name="part_number" value="<?= e($v('part_number')) ?>" maxlength="<?= PartValidator::PART_NUMBER_MAX ?>" autocomplete="off" spellcheck="false"<?= $numAttr ?>>
                <p class="hint" id="part-part-number-hint">The manufacturer's (OEM) number, for example 04465-02220.</p>
                <?= $numErr ?>
            </div>
            <div class="field">
                <label class="label" for="part-stock-status">Stock <span aria-hidden="true">*</span></label>
                <select class="select" id="part-stock-status" name="stock_status"<?= $stockAttr ?>>
                    <?php foreach (PartValidator::STOCK as $key => $label): ?>
                        <option value="<?= e($key) ?>"<?= $v('stock_status') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $stockErr ?>
            </div>
        </div>
        <fieldset class="form-section field" aria-describedby="part-condition-error">
            <legend class="label">Condition <span aria-hidden="true">*</span></legend>
            <div class="cluster">
                <?php foreach (PartValidator::CONDITIONS as $key => $label): ?>
                    <label class="check"><input type="radio" name="condition" value="<?= e($key) ?>"<?= $v('condition') === $key ? ' checked' : '' ?>><span class="check__text"><?= e($label) ?></span></label>
                <?php endforeach; ?>
            </div>
            <?= $condErr ?>
        </fieldset>
    </fieldset>

    <fieldset class="card form-section" aria-describedby="part-fitments-hint part-fitments-error">
        <legend class="card__title">Compatible vehicles</legend>
        <p class="hint" id="part-fitments-hint">The vehicles this part fits, one per row. Leave the model empty when it fits every model of that make, and a year empty when there is no limit. Leave the rows empty for a universal accessory: the website then asks customers to confirm compatibility with you.</p>
        <?php if (isset($errors['fitments'])): ?>
            <p class="field-error" id="part-fitments-error" role="alert"><?= admin_icon('alert-circle', 'icon--sm') ?><span data-error-text><?= e($errors['fitments']) ?></span></p>
        <?php endif; ?>
        <ol class="fitment-list" data-fitment-list data-fitment-max="<?= PartValidator::FITMENTS_MAX ?>">
            <?php foreach ($rows as $n => $row): ?>
                <?= $fitmentRow($n, $row) . "\n" ?>
            <?php endforeach; ?>
        </ol>
        <template data-fitment-template><?= $fitmentRow('__N__', ['brand_id' => '', 'model' => '', 'year_from' => '', 'year_to' => '']) ?></template>
        <button type="button" class="btn btn--secondary btn--sm" data-fitment-add hidden><?= admin_icon('plus') ?>Add another vehicle</button>
    </fieldset>

    <fieldset class="card form-section">
        <legend class="card__title">Price</legend>
        <div class="form-row">
            <div class="field">
                <label class="label" for="part-price">Price <span aria-hidden="true">*</span></label>
                <input class="input" type="text" inputmode="decimal" id="part-price" name="price" value="<?= e($v('price')) ?>" maxlength="16" autocomplete="off" required<?= $priceAttr ?>>
                <p class="hint" id="part-price-hint">The public price, for example 450 or 450.50.</p>
                <?= $priceErr ?>
            </div>
            <div class="field">
                <label class="label" for="part-currency">Currency</label>
                <select class="select" id="part-currency" name="currency"<?= $curAttr ?>>
                    <option value="<?= PartValidator::DEFAULT_CURRENCY ?>" selected><?= PartValidator::DEFAULT_CURRENCY ?></option>
                </select>
                <?= $curErr ?>
            </div>
        </div>
    </fieldset>

    <fieldset class="card form-section">
        <legend class="card__title">Description</legend>
        <div class="field">
            <label class="label" for="part-description">Description <span class="label__note">(optional)</span></label>
            <textarea class="textarea" id="part-description" name="description" rows="5" maxlength="<?= PartValidator::DESCRIPTION_MAX ?>"<?= $descAttr ?>><?= e($v('description')) ?></textarea>
            <p class="hint" id="part-description-hint">Plain text, up to <?= PartValidator::DESCRIPTION_MAX ?> characters. Only describe what is true of this part.</p>
            <?= $descErr ?>
        </div>
    </fieldset>

    <fieldset class="card form-section" id="photos">
        <legend class="card__title">Photos</legend>
        <?php if ($photoLimits['notice'] !== null): ?>
            <?= admin_alert('warning', 'Photo uploads are not available on this server yet', (string) $photoLimits['notice']) ?>
        <?php endif; ?>
        <?php if ($images !== []): ?>
            <ul class="part-photos" aria-label="Current photos">
                <?php foreach ($images as $image): $url = admin_part_image_url($image['file_path']); ?>
                    <li class="part-photo">
                        <?php if ($url !== null): ?><img src="<?= $url ?>" alt="<?= e((string) ($image['alt_text'] ?? '')) ?>" width="160" height="120" loading="lazy"><?php endif; ?>
                        <?php if ($image['is_primary'] === 1): ?>
                            <span class="badge badge--success">Main photo</span>
                        <?php else: ?>
                            <button type="submit" class="btn btn--ghost btn--sm" form="photo-primary-<?= $image['id'] ?>">Make main<span class="sr-only"> photo</span></button>
                        <?php endif; ?>
                        <button type="submit" class="btn btn--ghost btn--sm btn--text-danger" form="photo-remove-<?= $image['id'] ?>">Remove<span class="sr-only"> this photo</span></button>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($room > 0): ?>
            <div class="field">
                <label class="label" for="part-photos"><?= $images === [] ? 'Upload photos' : 'Add more photos' ?> <span class="label__note">(optional)</span></label>
                <input class="input input--file" type="file" id="part-photos" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple<?= $photoLimits['notice'] !== null ? ' disabled' : '' ?>
                       aria-describedby="part-photos-hint part-photos-status part-photos-error"<?= isset($errors['photos']) ? ' aria-invalid="true"' : '' ?>>
                <p class="hint" id="part-photos-hint">Up to <?= $room ?> more, JPEG, PNG or WebP, up to <?= e((string) $photoLimits['limitText']) ?> each, at least <?= PartService::PHOTO_MIN_LONG ?> x <?= PartService::PHOTO_MIN_SHORT ?> pixels. They are saved as WebP; the originals are not kept.<?= $images === [] ? ' The first photo becomes the main photo.' : '' ?></p>
                <p class="hint" id="part-photos-status" role="status" aria-live="polite" data-upload-status></p>
                <?= $photoErr ?>
            </div>
            <ul class="upload-previews" data-upload-previews aria-label="Selected photos"></ul>
        <?php else: ?>
            <p class="hint">This part has the maximum of <?= PartValidator::PHOTOS_MAX ?> photos. Remove one to add another.</p>
            <?= $photoErr ?>
        <?php endif; ?>
    </fieldset>

    <fieldset class="card form-section">
        <legend class="card__title">Website</legend>
        <label class="check"><input type="checkbox" name="is_published" value="1"<?= $v('is_published') === '1' ? ' checked' : '' ?>><span class="check__text">Show this part on the website</span></label>
        <p class="hint">Published parts appear in Latest Parts &amp; Accessories on the homepage. Archived parts are never shown.</p>
    </fieldset>

    <div class="cluster form-actions">
        <button type="submit" class="btn btn--primary" data-submit-button><?= e($submitLabel) ?></button>
        <a class="btn btn--ghost" href="<?= e($cancelHref) ?>">Cancel</a>
    </div>
</form>

<?php foreach ($images as $image): ?>
    <form method="post" action="part-photo.php" id="photo-remove-<?= $image['id'] ?>" hidden><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $part['id'] ?>"><input type="hidden" name="image" value="<?= $image['id'] ?>"><input type="hidden" name="action" value="remove"></form>
    <?php if ($image['is_primary'] !== 1): ?>
        <form method="post" action="part-photo.php" id="photo-primary-<?= $image['id'] ?>" hidden><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $part['id'] ?>"><input type="hidden" name="image" value="<?= $image['id'] ?>"><input type="hidden" name="action" value="primary"></form>
    <?php endif; ?>
<?php endforeach; ?>

<script src="<?= admin_asset('vendor/browser-image-compression.js') ?>"></script>
<script src="<?= admin_asset('js/vehicle-images.js') ?>"></script>
<script src="<?= admin_asset('js/part-form.js') ?>"></script>
