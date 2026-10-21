<?php

declare(strict_types=1);

/**
 * Shared team member form (Add and Edit). Presentation only: the server validates every field
 * and the real content of the photo again (TeamValidator, ImageValidator), so these attributes
 * and the browser preview are a convenience, not a control.
 *
 * Expected variables:
 *   string  $formAction   Where the form posts.
 *   array   $values       name, position, category, biography, display_order, status (strings).
 *   array   $errors       field => message (name, position, category, biography, display_order, status, photo).
 *   ?array  $member       The stored member when editing (for the current photo), else null.
 *   string  $submitLabel  Button text.
 *   string  $cancelHref   Where Cancel goes.
 *   ?string $formMessage  Summary shown above the form.
 *   array   $photoLimits  ['maxBytes' => int, 'maxTotal' => int, 'limitText' => string]
 *   ?string $photoNotice  Warning shown when the server cannot process photos (missing PHP components), else null.
 */

$field = static function (string $name, bool $hint = true) use ($errors): array {
    $has = isset($errors[$name]);
    $id = 'team-' . str_replace('_', '-', $name);

    return [
        $has ? ' aria-invalid="true"' : '',
        ' aria-describedby="' . ($hint ? $id . '-hint ' : '') . $id . '-error"',
        '<p class="field-error" id="' . $id . '-error" role="alert"' . ($has ? '' : ' hidden') . '>'
            . admin_icon('alert-circle', 'icon--sm') . '<span data-error-text>' . e($errors[$name] ?? '') . '</span></p>',
    ];
};
$v = static fn (string $key): string => (string) ($values[$key] ?? '');

[$nameInv, $nameDesc, $nameErr] = $field('name', false);
[$posInv, $posDesc, $posErr] = $field('position', false);
[$catInv, $catDesc, $catErr] = $field('category', false);
[$bioInv, $bioDesc, $bioErr] = $field('biography');
[$orderInv, $orderDesc, $orderErr] = $field('display_order');
[$statInv, $statDesc, $statErr] = $field('status');
[$photoInv, $photoDesc, $photoErr] = $field('photo');
$currentPhoto = $member !== null ? admin_team_photo_url($member['photo_path'] ?? null) : null;
?>
<?php if (!empty($formMessage)): ?>
    <div class="section-alert" data-login-notice tabindex="-1"><?= admin_alert('error', $formMessage) ?></div>
<?php endif; ?>

<form method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data" class="team-form" novalidate data-single-submit
      data-image-upload data-max-files="1" data-max-bytes="<?= (int) $photoLimits['maxBytes'] ?>" data-max-total="<?= (int) $photoLimits['maxTotal'] ?>">
    <?= csrf_field() ?>
    <p class="hint"><span aria-hidden="true">*</span> Required field</p>

    <!-- Professional photo -->
    <fieldset class="card form-section team-form__section">
        <legend class="card__title">Professional photo</legend>
        <?php if ($photoNotice !== null): ?>
            <?= admin_alert('warning', 'Photo uploads are not available on this server yet', $photoNotice) ?>
        <?php endif; ?>
        <div class="photo-field">
            <div class="photo-field__current">
                <?php if ($currentPhoto !== null): ?>
                    <img class="photo-frame" src="<?= $currentPhoto ?>" alt="<?= e(TeamService::photoAlt((string) $member['name'])) ?>" width="240" height="320">
                    <p class="hint">Current photo</p>
                <?php else: ?>
                    <span class="photo-frame photo-frame--empty"><?= admin_icon('user', 'icon--lg') ?><span>No photo</span></span>
                <?php endif; ?>
            </div>
            <div class="photo-field__controls">
                <div class="field">
                    <label class="label" for="team-photo"><?= $currentPhoto !== null ? 'Replace the photo' : 'Upload a photo' ?> <span class="label__note">(optional)</span></label>
                    <input class="input input--file" type="file" id="team-photo" name="photo" accept="image/jpeg,image/png,image/webp"<?= $photoNotice !== null ? ' disabled' : '' ?>
                           aria-describedby="team-photo-hint team-photo-status team-photo-error"<?= $photoInv ?>>
                    <p class="hint" id="team-photo-hint">JPEG, PNG or WebP, up to <?= e((string) $photoLimits['limitText']) ?>, at least <?= TeamValidator::PHOTO_MIN_LONG ?> x <?= TeamValidator::PHOTO_MIN_SHORT ?> pixels. A portrait photo works best. It is resized to fit 1200 x 1600 and saved as WebP; the original is not kept.<?= $currentPhoto !== null ? ' Leave this empty to keep the current photo.' : '' ?></p>
                    <p class="hint" id="team-photo-status" role="status" aria-live="polite" data-upload-status></p>
                    <?= $photoErr ?>
                </div>
                <ul class="upload-previews" data-upload-previews aria-label="Selected photo"></ul>
                <?php if ($currentPhoto !== null): ?>
                    <button type="submit" class="btn btn--secondary btn--sm btn--text-danger" form="team-remove-photo">Remove current photo</button>
                <?php endif; ?>
            </div>
        </div>
    </fieldset>

    <!-- Basic information -->
    <fieldset class="card form-section team-form__section">
        <legend class="card__title">Basic information</legend>
        <div class="field">
            <label class="label" for="team-name">Full name <span aria-hidden="true">*</span></label>
            <input class="input" type="text" id="team-name" name="name" value="<?= e($v('name')) ?>" maxlength="<?= TeamValidator::NAME_MAX ?>" autocomplete="off" required<?= $nameInv . $nameDesc ?>>
            <?= $nameErr ?>
        </div>
        <div class="field">
            <label class="label" for="team-position">Position <span aria-hidden="true">*</span></label>
            <input class="input" type="text" id="team-position" name="position" value="<?= e($v('position')) ?>" maxlength="<?= TeamValidator::POSITION_MAX ?>" autocomplete="off" required<?= $posInv . $posDesc ?>>
            <?= $posErr ?>
        </div>
        <div class="field">
            <label class="label" for="team-category">Category <span aria-hidden="true">*</span></label>
            <select class="select" id="team-category" name="category" required<?= $catInv . $catDesc ?>>
                <?php foreach (TeamValidator::CATEGORIES as $key => $label): ?>
                    <option value="<?= e($key) ?>"<?= $v('category') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $catErr ?>
        </div>
    </fieldset>

    <!-- Professional introduction -->
    <fieldset class="card form-section team-form__section">
        <legend class="card__title">Professional introduction</legend>
        <div class="field">
            <label class="label" for="team-biography">Biography <span class="label__note">(optional)</span></label>
            <textarea class="textarea" id="team-biography" name="biography" rows="6" maxlength="<?= TeamValidator::BIO_MAX ?>"<?= $bioInv . $bioDesc ?>><?= e($v('biography')) ?></textarea>
            <p class="hint" id="team-biography-hint">A short, professional introduction in plain text (up to <?= TeamValidator::BIO_MAX ?> characters). Only write what is true.</p>
            <?= $bioErr ?>
        </div>
    </fieldset>

    <!-- Publishing -->
    <fieldset class="card form-section team-form__section">
        <legend class="card__title">Publishing</legend>
        <div class="form-row">
            <div class="field">
                <label class="label" for="team-display-order">Display order <span aria-hidden="true">*</span></label>
                <input class="input" type="number" id="team-display-order" name="display_order" value="<?= e($v('display_order')) ?>" min="0" max="<?= TeamValidator::ORDER_MAX ?>" step="1" inputmode="numeric" required<?= $orderInv . $orderDesc ?>>
                <p class="hint" id="team-display-order-hint">Lower numbers are shown first. You can also reorder from the team list.</p>
                <?= $orderErr ?>
            </div>
            <div class="field">
                <label class="label" for="team-status">Status <span aria-hidden="true">*</span></label>
                <select class="select" id="team-status" name="status" required<?= $statInv . $statDesc ?>>
                    <option value="active"<?= $v('status') === 'active' ? ' selected' : '' ?>>Active</option>
                    <option value="inactive"<?= $v('status') === 'inactive' ? ' selected' : '' ?>>Inactive</option>
                </select>
                <p class="hint" id="team-status-hint">Inactive members are kept but are not meant to appear on the public team page.</p>
                <?= $statErr ?>
            </div>
        </div>
    </fieldset>

    <div class="cluster form-actions">
        <button type="submit" class="btn btn--primary" data-submit-button><?= e($submitLabel) ?></button>
        <a class="btn btn--ghost" href="<?= e($cancelHref) ?>">Cancel</a>
    </div>
</form>

<?php if ($currentPhoto !== null): ?>
    <form method="post" action="team-photo.php" id="team-remove-photo">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $member['id'] ?>">
    </form>
<?php endif; ?>
<script src="<?= admin_asset('vendor/browser-image-compression.js') ?>"></script>
<script src="<?= admin_asset('js/vehicle-images.js') ?>"></script>
