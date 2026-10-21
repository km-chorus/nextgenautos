<?php

declare(strict_types=1);

/**
 * Shared brand form (Add Brand and Edit Brand). Presentation only: the server
 * validates everything again (BrandValidator), so these attributes are a
 * convenience, not a control.
 *
 * Expected variables:
 *   string $formAction    Where the form posts (brand-create.php or brand-edit.php?id=N).
 *   array  $values        name, slug, description, display_order, status (strings; shown again after an error).
 *   array  $errors        field => message.
 *   string $submitLabel   Button text.
 *   string $cancelHref    Where Cancel goes.
 *   ?string $formMessage  Summary message shown above the form (validation or CSRF failure).
 *   bool   $slugAuto      True on the Add form: the slug follows the name until the user edits it.
 */

$field = static function (string $name) use ($errors): array {
    $has = isset($errors[$name]);

    return [
        $has ? ' aria-invalid="true"' : '',
        ' aria-describedby="brand-' . $name . '-hint brand-' . $name . '-error"',
        '<p class="field-error" id="brand-' . $name . '-error" role="alert"' . ($has ? '' : ' hidden') . '>'
            . admin_icon('alert-circle', 'icon--sm') . '<span data-error-text>' . e($errors[$name] ?? '') . '</span></p>',
    ];
};
[$nameInvalid, $nameDescribed, $nameError] = $field('name');
[$slugInvalid, $slugDescribed, $slugError] = $field('slug');
[$descInvalid, $descDescribed, $descError] = $field('description');
[$orderInvalid, $orderDescribed, $orderError] = $field('display_order');
[$statusInvalid, $statusDescribed, $statusError] = $field('status');
?>
<?php if (!empty($formMessage)): ?>
    <div class="section-alert" data-login-notice tabindex="-1"><?= admin_alert('error', $formMessage) ?></div>
<?php endif; ?>

<form method="post" action="<?= e($formAction) ?>" class="card brand-form" novalidate data-single-submit<?= !empty($slugAuto) ? ' data-slug-form' : '' ?>>
    <?= csrf_field() ?>
    <p class="hint"><span aria-hidden="true">*</span> Required field</p>

    <div class="field">
        <label class="label" for="brand-name">Brand name <span aria-hidden="true">*</span></label>
        <input class="input" type="text" id="brand-name" name="name" value="<?= e($values['name']) ?>"
               maxlength="<?= BrandValidator::NAME_MAX ?>" required autocomplete="off"<?= $nameInvalid . $nameDescribed ?><?= !empty($slugAuto) ? ' data-slug-source' : '' ?>>
        <p class="hint" id="brand-name-hint">For example Toyota or Mercedes-Benz. Must be unique.</p>
        <?= $nameError ?>
    </div>

    <div class="field">
        <label class="label" for="brand-slug">Slug <?= !empty($slugAuto) ? '<span class="label__note">(optional)</span>' : '<span aria-hidden="true">*</span>' ?></label>
        <input class="input" type="text" id="brand-slug" name="slug" value="<?= e($values['slug']) ?>"
               maxlength="<?= BrandValidator::SLUG_MAX ?>" autocomplete="off" autocapitalize="none" spellcheck="false"<?= $slugInvalid . $slugDescribed ?><?= !empty($slugAuto) ? ' data-slug-target' : ' required' ?>>
        <p class="hint" id="brand-slug-hint"><?= !empty($slugAuto)
            ? 'The web-safe version of the name, such as mercedes-benz. Leave it blank to create it from the name.'
            : 'Lowercase letters, numbers and hyphens only. Changing it changes the brand\'s web address.' ?></p>
        <?= $slugError ?>
    </div>

    <div class="field">
        <label class="label" for="brand-description">Description <span class="label__note">(optional)</span></label>
        <textarea class="textarea" id="brand-description" name="description" rows="4"
                  maxlength="<?= BrandValidator::DESCRIPTION_MAX ?>"<?= $descInvalid . $descDescribed ?>><?= e($values['description']) ?></textarea>
        <p class="hint" id="brand-description-hint">Up to <?= BrandValidator::DESCRIPTION_MAX ?> characters. Leave empty if there is no approved description.</p>
        <?= $descError ?>
    </div>

    <div class="form-row">
        <div class="field">
            <label class="label" for="brand-order">Display order <span aria-hidden="true">*</span></label>
            <input class="input" type="number" id="brand-order" name="display_order" value="<?= e($values['display_order']) ?>"
                   min="0" max="<?= BrandValidator::ORDER_MAX ?>" step="1" inputmode="numeric" required<?= $orderInvalid . $orderDescribed ?>>
            <p class="hint" id="brand-display_order-hint">Lower numbers are listed first.</p>
            <?= $orderError ?>
        </div>

        <div class="field">
            <label class="label" for="brand-status">Status <span aria-hidden="true">*</span></label>
            <select class="select" id="brand-status" name="status" required<?= $statusInvalid . $statusDescribed ?>>
                <option value="active"<?= $values['status'] === 'active' ? ' selected' : '' ?>>Active</option>
                <option value="inactive"<?= $values['status'] === 'inactive' ? ' selected' : '' ?>>Inactive</option>
            </select>
            <p class="hint" id="brand-status-hint">Inactive brands are not offered for new vehicles.</p>
            <?= $statusError ?>
        </div>
    </div>

    <div class="cluster form-actions">
        <button type="submit" class="btn btn--primary" data-submit-button><?= e($submitLabel) ?></button>
        <a class="btn btn--ghost" href="<?= e($cancelHref) ?>">Cancel</a>
    </div>
</form>
