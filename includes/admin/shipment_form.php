<?php

declare(strict_types=1);

/**
 * Shared shipment form (Add and Edit, spec 20). Presentation only: ShipmentService validates everything again.
 * shipment-form.js pre-ticks "Email the customer" when the status changes to one with an email (D2), shows the
 * "move back" confirmation when an earlier status is chosen (D8), and keeps the preview link on the chosen status.
 * Without JavaScript the administrator ticks the boxes themselves; the server enforces both rules.
 *
 * Expected: string $formAction, array $values, array $errors, list $vehicleChoices, ?array $shipment, ?string $formMessage,
 * bool $notifyChecked, bool $needsBackConfirm, string $submitLabel, string $cancelHref.
 */

$v = static fn (string $k): string => (string) ($values[$k] ?? '');
$err = static function (string $name) use ($errors): array {
    $has = isset($errors[$name]);
    $id = 'ship-' . str_replace('_', '-', $name);

    return [
        ($has ? ' aria-invalid="true"' : '') . ' aria-describedby="' . $id . '-hint ' . $id . '-error"',
        '<p class="field-error" id="' . $id . '-error" role="alert"' . ($has ? '' : ' hidden') . '>' . admin_icon('alert-circle', 'icon--sm') . '<span data-error-text>' . e($errors[$name] ?? '') . '</span></p>',
    ];
};
$savedStatus = $shipment !== null ? (string) $shipment['status'] : 'NOT_YET_SHIPPED';
$recipient = $shipment !== null ? ShipmentEmail::recipient($shipment) : null;
$linked = $shipment !== null && $shipment['customer_id'] !== null;
?>
<?php if (!empty($formMessage)): ?>
    <div class="section-alert" data-login-notice tabindex="-1"><?= admin_alert('error', $formMessage) ?></div>
<?php endif; ?>

<form method="post" action="<?= e($formAction) ?>" class="part-form shipment-form" novalidate data-single-submit data-shipment-form data-saved-status="<?= e($savedStatus) ?>"
      data-email-statuses="<?= e(implode(' ', ShipmentValidator::EMAIL_STATUSES)) ?>" data-status-order="<?= e(implode(' ', array_keys(ShipmentValidator::STATUSES))) ?>">
    <?= csrf_field() ?>
    <p class="hint"><span aria-hidden="true">*</span> Required field</p>

    <fieldset class="card form-section">
        <legend class="card__title">Vehicle and customer</legend>
        <?php [$a, $e] = $err('vehicle_id'); ?>
        <div class="field">
            <label class="label" for="ship-vehicle-id">Vehicle <span aria-hidden="true">*</span></label>
            <select class="select" id="ship-vehicle-id" name="vehicle_id" required<?= $a ?>>
                <option value="">Choose a vehicle</option>
                <?php foreach ($vehicleChoices as $c): ?>
                    <option value="<?= $c['id'] ?>"<?= $v('vehicle_id') === (string) $c['id'] ? ' selected' : '' ?>><?= e($c['label']) ?> (<?= e(ucfirst($c['status'])) ?>)</option>
                <?php endforeach; ?>
            </select>
            <p class="hint" id="ship-vehicle-id-hint">Only vehicles marked Reserved or Sold are listed. Mark the vehicle first on the Vehicles page if it is missing.</p>
            <?= $e ?>
        </div>
        <div class="form-row">
            <?php [$a, $e] = $err('customer_name'); ?>
            <div class="field">
                <label class="label" for="ship-customer-name">Customer name <span aria-hidden="true">*</span></label>
                <input class="input" type="text" id="ship-customer-name" name="customer_name" value="<?= e($v('customer_name')) ?>" maxlength="<?= ShipmentValidator::NAME_MAX ?>" autocomplete="off" required<?= $a ?>>
                <p class="hint" id="ship-customer-name-hint"></p>
                <?= $e ?>
            </div>
            <?php [$a, $e] = $err('customer_email'); ?>
            <div class="field">
                <label class="label" for="ship-customer-email">Customer email <span class="label__note">(optional)</span></label>
                <input class="input" type="email" id="ship-customer-email" name="customer_email" value="<?= e($v('customer_email')) ?>" maxlength="255" autocomplete="off" spellcheck="false"<?= $a ?>>
                <p class="hint" id="ship-customer-email-hint">Needed to email updates. If the customer has a website account with this email, the shipment is linked to it and they see it on Track Vehicle.<?php if ($shipment !== null): ?> <strong><?= $linked ? 'Linked to the customer\'s website account.' : 'No website account with this email.' ?></strong><?php endif; ?></p>
                <?= $e ?>
            </div>
        </div>
    </fieldset>

    <fieldset class="card form-section">
        <legend class="card__title">Shipping</legend>
        <div class="form-row">
            <?php [$a, $e] = $err('shipping_company'); ?>
            <div class="field">
                <label class="label" for="ship-shipping-company">Shipping line <span class="label__note">(optional)</span></label>
                <input class="input" type="text" id="ship-shipping-company" name="shipping_company" value="<?= e($v('shipping_company')) ?>" maxlength="<?= ShipmentValidator::LINE_MAX ?>" autocomplete="off" list="ship-lines"<?= $a ?>>
                <datalist id="ship-lines"><?php foreach (ShipmentValidator::LINE_SUGGESTIONS as $line): ?><option value="<?= e($line) ?>"></option><?php endforeach; ?></datalist>
                <p class="hint" id="ship-shipping-company-hint">We ship with Hapag-Lloyd (filled in for new shipments). Change it only if a shipment really goes with another line.</p>
                <?= $e ?>
            </div>
            <?php [$a, $e] = $err('container_number'); ?>
            <div class="field">
                <label class="label" for="ship-container-number">Container number</label>
                <input class="input" type="text" id="ship-container-number" name="container_number" value="<?= e($v('container_number')) ?>" maxlength="15" autocomplete="off" spellcheck="false" autocapitalize="characters"<?= $a ?>>
                <p class="hint" id="ship-container-number-hint">Exactly as given by the shipping company: 4 letters and 7 digits, for example HLXU1234567. Required from "Tracking information available" on. Never invent one.</p>
                <?= $e ?>
            </div>
        </div>
        <?php [$a, $e] = $err('tracking_url'); ?>
        <div class="field">
            <label class="label" for="ship-tracking-url">Official tracking address <span class="label__note">(optional)</span></label>
            <input class="input" type="url" id="ship-tracking-url" name="tracking_url" value="<?= e($v('tracking_url')) ?>" maxlength="<?= ShipmentValidator::URL_MAX ?>" autocomplete="off" spellcheck="false" inputmode="url"<?= $a ?>>
            <p class="hint" id="ship-tracking-url-hint">Leave empty: customers get Hapag-Lloyd's official container tracking automatically. Only a Hapag-Lloyd address is accepted here.</p>
            <?= $e ?>
        </div>
    </fieldset>

    <fieldset class="card form-section">
        <legend class="card__title">Status and dates</legend>
        <?php [$a, $e] = $err('status'); ?>
        <div class="field">
            <label class="label" for="ship-status">Status <span aria-hidden="true">*</span></label>
            <select class="select" id="ship-status" name="status" data-ship-status<?= $a ?>>
                <?php foreach (ShipmentValidator::STATUSES as $key => $label): ?>
                    <option value="<?= e($key) ?>"<?= $v('status') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="hint" id="ship-status-hint">What the company knows about the shipment. Live location always comes from the shipping line.</p>
            <?= $e ?>
        </div>
        <label class="check" data-ship-back<?= !empty($needsBackConfirm) ? '' : ' hidden' ?>><input type="checkbox" name="confirm_back" value="1"><span class="check__text">Yes, move the status back (for example to correct a mistake)</span></label>
        <div class="form-row">
            <?php foreach (['shipped_date' => 'Shipped', 'estimated_arrival' => 'Estimated arrival'] as $k => $label): [$a, $e] = $err($k); ?>
                <div class="field">
                    <label class="label" for="ship-<?= str_replace('_', '-', $k) ?>"><?= e($label) ?> <span class="label__note">(optional)</span></label>
                    <input class="input" type="date" id="ship-<?= str_replace('_', '-', $k) ?>" name="<?= $k ?>" value="<?= e($v($k)) ?>"<?= $a ?>>
                    <p class="hint" id="ship-<?= str_replace('_', '-', $k) ?>-hint"><?= $k === 'estimated_arrival' ? 'Only as given by the shipping line. Customers see it marked "may change".' : '' ?></p>
                    <?= $e ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="form-row">
            <?php foreach (['arrival_date' => 'Arrived', 'delivered_date' => 'Delivered'] as $k => $label): [$a, $e] = $err($k); ?>
                <div class="field">
                    <label class="label" for="ship-<?= str_replace('_', '-', $k) ?>"><?= e($label) ?> <span class="label__note">(optional)</span></label>
                    <input class="input" type="date" id="ship-<?= str_replace('_', '-', $k) ?>" name="<?= $k ?>" value="<?= e($v($k)) ?>"<?= $a ?>>
                    <p class="hint" id="ship-<?= str_replace('_', '-', $k) ?>-hint"></p>
                    <?= $e ?>
                </div>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <fieldset class="card form-section">
        <legend class="card__title">Internal notes</legend>
        <?php [$a, $e] = $err('internal_notes'); ?>
        <div class="field">
            <label class="label" for="ship-internal-notes">Notes for the team <span class="label__note">(optional, never shown to the customer)</span></label>
            <textarea class="textarea" id="ship-internal-notes" name="internal_notes" rows="3" maxlength="<?= ShipmentValidator::NOTES_MAX ?>"<?= $a ?>><?= e($v('internal_notes')) ?></textarea>
            <p class="hint" id="ship-internal-notes-hint"></p>
            <?= $e ?>
        </div>
    </fieldset>

    <fieldset class="card form-section">
        <legend class="card__title">Customer email</legend>
        <p class="hint"><strong>The customer is emailed automatically every time you change the status</strong> (and when you create a shipment), in the branded template, to <?= $recipient !== null ? '<strong>' . e($recipient) . '</strong>' : 'the email address above' ?>. Nothing is sent for "Not yet shipped" or when there is no email address.</p>
<?php if ($shipment !== null): ?>
        <label class="check"><input type="checkbox" name="notify" value="1" data-ship-notify<?= !empty($notifyChecked) ? ' checked' : '' ?>><span class="check__text">Send the email again for the current status</span></label>
<?php endif; ?>
        <p class="hint">
            <?php if ($shipment !== null): ?>
                <a href="shipment-email-preview.php?id=<?= (int) $shipment['id'] ?>&amp;status=<?= e(in_array($v('status'), ShipmentValidator::EMAIL_STATUSES, true) ? $v('status') : 'SHIPMENT_BOOKED') ?>" target="_blank" rel="noopener" data-ship-preview>Preview the email for the selected status<span class="sr-only"> (opens in a new tab)</span></a>
            <?php endif; ?>
        </p>
    </fieldset>

    <div class="cluster form-actions">
        <button type="submit" class="btn btn--primary" data-submit-button><?= e($submitLabel) ?></button>
        <a class="btn btn--ghost" href="<?= e($cancelHref) ?>">Cancel</a>
    </div>
</form>
<script src="<?= admin_asset('js/shipment-form.js') ?>"></script>
