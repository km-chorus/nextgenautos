<?php

declare(strict_types=1);

/**
 * Shared vehicle form (Add Vehicle and Edit Vehicle), organised in sections.
 * Presentation only: the server validates everything again (VehicleValidator),
 * so these attributes are a convenience, not a control. There is deliberately no
 * image section: image management belongs to a later specification.
 *
 * Expected variables:
 *   string  $formAction   Where the form posts.
 *   array   $values       Field values as strings (shown again after an error).
 *   array   $errors       field => message.
 *   array   $brandChoices Brands offered in the drop-down (active ones, plus the current brand on edit).
 *   array   $currencies   Currencies offered.
 *   int     $maxYear      Highest year accepted.
 *   string  $submitLabel  Button text.
 *   string  $cancelHref   Where Cancel goes.
 *   ?string $formMessage  Summary shown above the form (validation or CSRF failure).
 *   bool    $isEdit       True on the Edit form (the slug is then required).
 */

$field = static function (string $name, bool $hint = true) use ($errors): array {
    $has = isset($errors[$name]);
    $id = 'vehicle-' . str_replace('_', '-', $name);

    return [
        $has ? ' aria-invalid="true"' : '',
        ' aria-describedby="' . ($hint ? $id . '-hint ' : '') . $id . '-error"',
        '<p class="field-error" id="' . $id . '-error" role="alert"' . ($has ? '' : ' hidden') . '>'
            . admin_icon('alert-circle', 'icon--sm') . '<span data-error-text>' . e($errors[$name] ?? '') . '</span></p>',
    ];
};
$v = static fn (string $key): string => (string) ($values[$key] ?? '');

[$brandInv, $brandDesc, $brandErr] = $field('brand_id', false);
[$yearInv, $yearDesc, $yearErr] = $field('year');
[$modelInv, $modelDesc, $modelErr] = $field('model', false);
[$trimInv, $trimDesc, $trimErr] = $field('trim', false);
[$engineInv, $engineDesc, $engineErr] = $field('engine_size');
[$mileInv, $mileDesc, $mileErr] = $field('mileage');
[$unitInv, $unitDesc, $unitErr] = $field('mileage_unit', false);
[$transInv, $transDesc, $transErr] = $field('transmission', false);
[$fuelInv, $fuelDesc, $fuelErr] = $field('fuel_type', false);
[$driveInv, $driveDesc, $driveErr] = $field('drive_type', false);
[$priceInv, $priceDesc, $priceErr] = $field('price');
[$curInv, $curDesc, $curErr] = $field('currency', false);
[, , $dutyErr] = $field('duty_included');
[$descInv, $descDesc, $descErr] = $field('description');
[$featInv, $featDesc, $featErr] = $field('key_features');
[$shipInv, $shipDesc, $shipErr] = $field('shipping_info');
[$statInv, $statDesc, $statErr] = $field('availability_status');
[, , $pubErr] = $field('is_published');
[$slugInv, $slugDesc, $slugErr] = $field('slug');
[, , $accErr] = $field('is_accident');
[$dmgInv, $dmgDesc, $dmgErr] = $field('damage_description');
[, , $areaErr] = $field('damage_areas');
[$runInv, $runDesc, $runErr] = $field('runs_drives', false);
[$bagInv, $bagDesc, $bagErr] = $field('airbags_deployed', false);
[$titleInv, $titleDesc, $titleErr] = $field('title_status');
$isAccident = $v('is_accident') === '1';
$areas = array_filter(preg_split('/[,
]/', $v('damage_areas')) ?: []);
$descriptionMax = VehicleValidator::DESCRIPTION_MAX;
?>
<?php if (!empty($formMessage)): ?>
    <div class="section-alert" data-login-notice tabindex="-1"><?= admin_alert('error', $formMessage) ?></div>
<?php endif; ?>

<form method="post" action="<?= e($formAction) ?>" class="vehicle-form" novalidate data-single-submit>
    <?= csrf_field() ?>
    <p class="hint"><span aria-hidden="true">*</span> Required field</p>

    <!-- Vehicle information -->
    <fieldset class="card form-section vehicle-form__section">
        <legend class="card__title">Vehicle information</legend>
        <div class="form-row">
            <div class="field">
                <label class="label" for="vehicle-brand-id">Brand <span aria-hidden="true">*</span></label>
                <select class="select" id="vehicle-brand-id" name="brand_id" required<?= $brandInv . $brandDesc ?>>
                    <option value="">Select a brand</option>
                    <?php foreach ($brandChoices as $brand): ?>
                        <option value="<?= e((string) $brand['id']) ?>"<?= $v('brand_id') === (string) $brand['id'] ? ' selected' : '' ?>><?= e((string) $brand['name']) ?><?= $brand['status'] !== 'active' ? ' (inactive)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $brandErr ?>
            </div>
            <div class="field">
                <label class="label" for="vehicle-year">Year <span aria-hidden="true">*</span></label>
                <input class="input" type="text" id="vehicle-year" name="year" value="<?= e($v('year')) ?>" inputmode="numeric"
                       maxlength="4" autocomplete="off" required<?= $yearInv . $yearDesc ?>>
                <p class="hint" id="vehicle-year-hint">Model year, for example 2021 (<?= VehicleValidator::MIN_YEAR ?> to <?= (int) $maxYear ?>).</p>
                <?= $yearErr ?>
            </div>
        </div>
        <div class="form-row">
            <div class="field">
                <label class="label" for="vehicle-model">Model <span aria-hidden="true">*</span></label>
                <input class="input" type="text" id="vehicle-model" name="model" value="<?= e($v('model')) ?>"
                       maxlength="<?= VehicleValidator::TEXT_MAX ?>" autocomplete="off" required<?= $modelInv . $modelDesc ?>>
                <?= $modelErr ?>
            </div>
            <div class="field">
                <label class="label" for="vehicle-trim">Trim <span class="label__note">(optional)</span></label>
                <input class="input" type="text" id="vehicle-trim" name="trim" value="<?= e($v('trim')) ?>"
                       maxlength="<?= VehicleValidator::TEXT_MAX ?>" autocomplete="off"<?= $trimInv . $trimDesc ?>>
                <?= $trimErr ?>
            </div>
        </div>
    </fieldset>

    <!-- Pricing -->
    <fieldset class="card form-section vehicle-form__section">
        <legend class="card__title">Pricing</legend>
        <div class="form-row">
            <div class="field">
                <label class="label" for="vehicle-price">Price <span aria-hidden="true">*</span></label>
                <input class="input" type="text" id="vehicle-price" name="price" value="<?= e($v('price')) ?>" inputmode="decimal"
                       maxlength="13" autocomplete="off" required<?= $priceInv . $priceDesc ?>>
                <p class="hint" id="vehicle-price-hint">Digits with up to two decimals, no commas, for example 12500.00.</p>
                <?= $priceErr ?>
            </div>
            <div class="field">
                <label class="label" for="vehicle-currency">Currency <span aria-hidden="true">*</span></label>
                <select class="select" id="vehicle-currency" name="currency" required<?= $curInv . $curDesc ?>>
                    <?php foreach ($currencies as $currency): ?>
                        <option value="<?= e($currency) ?>"<?= $v('currency') === $currency ? ' selected' : '' ?>><?= e($currency) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $curErr ?>
            </div>
        </div>
        <fieldset class="form-section field" aria-describedby="vehicle-duty-included-hint vehicle-duty-included-error">
            <legend class="label">Duty included <span aria-hidden="true">*</span></legend>
            <div class="cluster">
                <label class="check"><input type="radio" name="duty_included" value="1"<?= $v('duty_included') === '1' ? ' checked' : '' ?>><span class="check__text">Yes</span></label>
                <label class="check"><input type="radio" name="duty_included" value="0"<?= $v('duty_included') !== '1' ? ' checked' : '' ?>><span class="check__text">No</span></label>
            </div>
            <p class="hint" id="vehicle-duty-included-hint">Choose Yes only if the price above already includes Ghana import duty. The website does not calculate duty.</p>
            <?= $dutyErr ?>
        </fieldset>
    </fieldset>

    <!-- Specifications -->
    <fieldset class="card form-section vehicle-form__section">
        <legend class="card__title">Specifications</legend>
        <p class="hint">Leave a field empty when the information is not available. Nothing is filled in automatically.</p>
        <div class="form-row">
            <div class="field">
                <label class="label" for="vehicle-engine-size">Engine size <span class="label__note">(optional)</span></label>
                <input class="input" type="text" id="vehicle-engine-size" name="engine_size" value="<?= e($v('engine_size')) ?>"
                       maxlength="<?= VehicleValidator::SHORT_MAX ?>" autocomplete="off"<?= $engineInv . $engineDesc ?>>
                <p class="hint" id="vehicle-engine-size-hint">For example 2.0L.</p>
                <?= $engineErr ?>
            </div>
            <div class="field">
                <label class="label" for="vehicle-transmission">Transmission <span class="label__note">(optional)</span></label>
                <input class="input" type="text" id="vehicle-transmission" name="transmission" value="<?= e($v('transmission')) ?>"
                       maxlength="<?= VehicleValidator::SHORT_MAX ?>" autocomplete="off"<?= $transInv . $transDesc ?>>
                <?= $transErr ?>
            </div>
        </div>
        <div class="form-row">
            <div class="field">
                <label class="label" for="vehicle-fuel-type">Fuel type <span class="label__note">(optional)</span></label>
                <input class="input" type="text" id="vehicle-fuel-type" name="fuel_type" value="<?= e($v('fuel_type')) ?>"
                       maxlength="<?= VehicleValidator::SHORT_MAX ?>" autocomplete="off"<?= $fuelInv . $fuelDesc ?>>
                <?= $fuelErr ?>
            </div>
            <div class="field">
                <label class="label" for="vehicle-drive-type">Drive type <span class="label__note">(optional)</span></label>
                <input class="input" type="text" id="vehicle-drive-type" name="drive_type" value="<?= e($v('drive_type')) ?>"
                       maxlength="<?= VehicleValidator::SHORT_MAX ?>" autocomplete="off"<?= $driveInv . $driveDesc ?>>
                <?= $driveErr ?>
            </div>
        </div>
        <div class="form-row">
            <div class="field">
                <label class="label" for="vehicle-mileage">Mileage <span class="label__note">(optional)</span></label>
                <input class="input" type="text" id="vehicle-mileage" name="mileage" value="<?= e($v('mileage')) ?>" inputmode="numeric"
                       maxlength="7" autocomplete="off"<?= $mileInv . $mileDesc ?>>
                <p class="hint" id="vehicle-mileage-hint">Whole number, as shown on the odometer.</p>
                <?= $mileErr ?>
            </div>
            <div class="field">
                <label class="label" for="vehicle-mileage-unit">Mileage unit</label>
                <select class="select" id="vehicle-mileage-unit" name="mileage_unit"<?= $unitInv . $unitDesc ?>>
                    <option value="km"<?= $v('mileage_unit') !== 'mi' ? ' selected' : '' ?>>Kilometres (km)</option>
                    <option value="mi"<?= $v('mileage_unit') === 'mi' ? ' selected' : '' ?>>Miles (mi)</option>
                </select>
                <?= $unitErr ?>
            </div>
        </div>
        <div class="field">
            <label class="label" for="vehicle-key-features">Key features <span class="label__note">(optional)</span></label>
            <textarea class="textarea" id="vehicle-key-features" name="key_features" rows="6"<?= $featInv . $featDesc ?>><?= e($v('key_features')) ?></textarea>
            <p class="hint" id="vehicle-key-features-hint">One feature per line, for example Sunroof. Up to <?= VehicleValidator::FEATURES_LIMIT ?> features.</p>
            <?= $featErr ?>
        </div>
    </fieldset>

    <!-- Description -->
    <fieldset class="card form-section vehicle-form__section">
        <legend class="card__title">Description</legend>
        <div class="field">
            <label class="label" for="vehicle-description">Description <span class="label__note">(optional)</span></label>
            <textarea class="textarea" id="vehicle-description" name="description" rows="6" maxlength="<?= $descriptionMax ?>"<?= $descInv . $descDesc ?>><?= e($v('description')) ?></textarea>
            <p class="hint" id="vehicle-description-hint">Plain text, up to <?= $descriptionMax ?> characters. Only describe what is true of this vehicle.</p>
            <?= $descErr ?>
        </div>
    </fieldset>

    <!-- Shipping information -->
    <fieldset class="card form-section vehicle-form__section">
        <legend class="card__title">Shipping information</legend>
        <div class="field">
            <label class="label" for="vehicle-shipping-info">Shipping information <span class="label__note">(optional)</span></label>
            <textarea class="textarea" id="vehicle-shipping-info" name="shipping_info" rows="4" maxlength="<?= VehicleValidator::SHIPPING_MAX ?>"<?= $shipInv . $shipDesc ?>><?= e($v('shipping_info')) ?></textarea>
            <p class="hint" id="vehicle-shipping-info-hint">Origin, notes or other approved shipping details. Container numbers and tracking are recorded in shipments, not here.</p>
            <?= $shipErr ?>
        </div>
    </fieldset>

    <!-- Accident damage (spec 23) -->
    <fieldset class="card form-section vehicle-form__section" aria-describedby="vehicle-is-accident-hint vehicle-is-accident-error">
        <legend class="card__title">Accident damage</legend>
        <label class="check"><input type="checkbox" name="is_accident" value="1" data-accident-toggle aria-controls="vehicle-accident-details"<?= $isAccident ? ' checked' : '' ?>><span class="check__text">This is an accident-damaged vehicle</span></label>
        <p class="hint" id="vehicle-is-accident-hint">Accident vehicles are never listed with the normal vehicles. They appear only on the Accident Vehicles page (when it is switched on in Settings), always labelled "Accident-damaged". To publish one, add at least one photo of type Damage on Manage Images.</p>
        <?= $accErr ?>
        <div class="stack" id="vehicle-accident-details" data-accident-details>
            <div class="field">
                <label class="label" for="vehicle-damage-description">Damage description <span aria-hidden="true">*</span></label>
                <textarea class="textarea" id="vehicle-damage-description" name="damage_description" rows="5" maxlength="<?= VehicleValidator::DAMAGE_MAX ?>"<?= $dmgInv . $dmgDesc ?>><?= e($v('damage_description')) ?></textarea>
                <p class="hint" id="vehicle-damage-description-hint">What happened and what is damaged, in plain words. Only what is true and known: buyers rely on it.</p>
                <?= $dmgErr ?>
            </div>
            <fieldset class="form-section field" aria-describedby="vehicle-damage-areas-hint vehicle-damage-areas-error">
                <legend class="label">Damaged areas <span aria-hidden="true">*</span></legend>
                <div class="cluster">
                    <?php foreach (VehicleValidator::DAMAGE_AREAS as $key => $areaLabel): ?>
                        <label class="check"><input type="checkbox" name="damage_areas[]" value="<?= e($key) ?>"<?= in_array($key, $areas, true) ? ' checked' : '' ?>><span class="check__text"><?= e($areaLabel) ?></span></label>
                    <?php endforeach; ?>
                </div>
                <p class="hint" id="vehicle-damage-areas-hint">Tick every area with damage.</p>
                <?= $areaErr ?>
            </fieldset>
            <div class="form-row">
                <?php foreach ([['runs_drives', 'Runs and drives', VehicleValidator::RUNS_DRIVES, $runInv . $runDesc, $runErr],
                    ['airbags_deployed', 'Airbags deployed', VehicleValidator::AIRBAGS, $bagInv . $bagDesc, $bagErr]] as [$name, $fieldLabel, $choices, $attrs, $err]): ?>
                <div class="field">
                    <label class="label" for="vehicle-<?= e(str_replace('_', '-', $name)) ?>"><?= e($fieldLabel) ?> <span aria-hidden="true">*</span></label>
                    <select class="select" id="vehicle-<?= e(str_replace('_', '-', $name)) ?>" name="<?= e($name) ?>"<?= $attrs ?>>
                        <option value="">Choose</option>
                        <?php foreach ($choices as $key => $text): ?>
                            <option value="<?= e($key) ?>"<?= $v($name) === $key ? ' selected' : '' ?>><?= e($text) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err ?>
                </div>
                <?php endforeach; ?>
                <div class="field">
                    <label class="label" for="vehicle-title-status">Title status <span aria-hidden="true">*</span></label>
                    <select class="select" id="vehicle-title-status" name="title_status"<?= $titleInv . $titleDesc ?>>
                        <option value="">Choose</option>
                        <?php foreach (VehicleValidator::TITLE_STATUSES as $key => $text): ?>
                            <option value="<?= e($key) ?>"<?= $v('title_status') === $key ? ' selected' : '' ?>><?= e($text) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="hint" id="vehicle-title-status-hint">The vehicle's Canadian title (ownership) status.</p>
                    <?= $titleErr ?>
                </div>
            </div>
        </div>
    </fieldset>

    <!-- Inventory -->
    <fieldset class="card form-section vehicle-form__section">
        <legend class="card__title">Inventory</legend>
        <div class="form-row">
            <div class="field">
                <label class="label" for="vehicle-availability-status">Status <span aria-hidden="true">*</span></label>
                <select class="select" id="vehicle-availability-status" name="availability_status" required<?= $statInv . $statDesc ?>>
                    <?php foreach (VehicleValidator::STATUSES as $status): ?>
                        <option value="<?= e($status) ?>"<?= $v('availability_status') === $status ? ' selected' : '' ?>><?= e(admin_status_label($status)) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="hint" id="vehicle-availability-status-hint">Unavailable keeps the record but takes the vehicle out of active stock.</p>
                <?= $statErr ?>
            </div>
            <fieldset class="form-section field" aria-describedby="vehicle-is-published-hint vehicle-is-published-error">
                <legend class="label">Published on website <span aria-hidden="true">*</span></legend>
                <div class="cluster">
                    <label class="check"><input type="radio" name="is_published" value="1"<?= $v('is_published') === '1' ? ' checked' : '' ?>><span class="check__text">Yes</span></label>
                    <label class="check"><input type="radio" name="is_published" value="0"<?= $v('is_published') !== '1' ? ' checked' : '' ?>><span class="check__text">No</span></label>
                </div>
                <p class="hint" id="vehicle-is-published-hint">New vehicles start unpublished. The public catalogue is built in a later step.</p>
                <?= $pubErr ?>
            </fieldset>
        </div>
        <div class="field">
            <label class="label" for="vehicle-slug">Vehicle URL (slug) <?= empty($isEdit) ? '<span class="label__note">(optional)</span>' : '<span aria-hidden="true">*</span>' ?></label>
            <input class="input" type="text" id="vehicle-slug" name="slug" value="<?= e($v('slug')) ?>"
                   maxlength="<?= VehicleValidator::SLUG_MAX ?>" autocomplete="off" autocapitalize="none" spellcheck="false"<?= empty($isEdit) ? '' : ' required' ?><?= $slugInv . $slugDesc ?>>
            <p class="hint" id="vehicle-slug-hint"><?= empty($isEdit)
                ? 'Lowercase letters, numbers and hyphens, for example 2024-toyota-camry-xse. Leave it blank to create it from the year, brand, model and trim.'
                : 'Lowercase letters, numbers and hyphens only. Changing it changes the vehicle\'s web address.' ?></p>
            <?= $slugErr ?>
        </div>
    </fieldset>

    <div class="cluster form-actions">
        <button type="submit" class="btn btn--primary" data-submit-button><?= e($submitLabel) ?></button>
        <a class="btn btn--ghost" href="<?= e($cancelHref) ?>">Cancel</a>
    </div>
</form>
