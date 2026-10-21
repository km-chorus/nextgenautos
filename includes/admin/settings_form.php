<?php

declare(strict_types=1);

/**
 * Settings form (spec 17). Presentation only: SettingsService validates everything again (ContactSettingsValidator), so
 * the browser attributes are a convenience, not a control.
 *
 * Expected variables: array $values (key => string), array $errors (key => message), ?string $formMessage.
 */

$input = static function (string $key, string $type, string $hint, string $extra = '') use ($values, $errors): string {
    [, , $label] = ContactSettingsValidator::FIELDS[$key];
    $has = isset($errors[$key]);
    $id = 'setting-' . $key;
    $attrs = ' id="' . $id . '" name="' . $key . '" aria-describedby="' . $id . '-hint ' . $id . '-error"' . ($has ? ' aria-invalid="true"' : '') . $extra;
    $control = $type === 'textarea'
        ? '<textarea class="textarea"' . $attrs . ' rows="4" maxlength="' . ContactSettingsValidator::MAX_HOURS . '">' . e($values[$key] ?? '') . '</textarea>'
        : '<input class="input" type="' . $type . '"' . $attrs . ' value="' . e($values[$key] ?? '') . '">';

    return '<div class="field">'
        . '<label class="label" for="' . $id . '">' . e($label) . ' <span class="label__note">(optional)</span></label>'
        . $control
        . '<p class="hint" id="' . $id . '-hint">' . e($hint) . '</p>'
        . '<p class="field-error" id="' . $id . '-error" role="alert"' . ($has ? '' : ' hidden') . '>' . admin_icon('alert-circle', 'icon--sm')
        . '<span data-error-text>' . e($errors[$key] ?? '') . '</span></p>'
        . '</div>';
};
$url = ' maxlength="' . ContactSettingsValidator::MAX_URL . '" autocomplete="off" autocapitalize="none" spellcheck="false" inputmode="url"';
?>
<?php if (!empty($formMessage)): ?>
    <div class="section-alert" data-login-notice tabindex="-1"><?= admin_alert('error', $formMessage) ?></div>
<?php endif; ?>

<form method="post" action="settings.php" class="stack stack--lg settings-form" novalidate data-single-submit>
    <?= csrf_field() ?>

    <section class="card" aria-labelledby="contact-heading">
        <h2 class="card__title" id="contact-heading">Contact details</h2>
        <p class="hint">Shown on the Contact page and in the website footer. The company phone also appears at the top of every page; the WhatsApp number powers every "WhatsApp" button.</p>
        <div class="form-row">
            <?= $input('company_phone', 'tel', 'The main number, shown at the top of every page. For example 024 123 4567 or +1 416 555 0100.', ' maxlength="30" autocomplete="off"') ?>
            <?= $input('company_phone_2', 'tel', 'Another number customers can call, shown on the Contact page and in the footer.', ' maxlength="30" autocomplete="off"') ?>
        </div>
        <div class="form-row">
            <?= $input('whatsapp_number', 'tel', 'The number of your WhatsApp Business account. Customers are taken straight to a chat with it.', ' maxlength="30" autocomplete="off"') ?>
        </div>
        <?= $input('contact_email', 'email', 'Where customers can email you, for example info@yourcompany.com.', ' maxlength="255" autocomplete="off" autocapitalize="none" spellcheck="false"') ?>
        <?= $input('opening_hours', 'textarea', 'One line per day or group of days, for example "Mon - Fri: 8:00 - 17:00". Up to ' . ContactSettingsValidator::MAX_HOURS . ' characters.') ?>
    </section>

    <section class="card" aria-labelledby="social-heading">
        <h2 class="card__title" id="social-heading">Social media</h2>
        <p class="hint">The full address of each profile, starting with https://. Only filled-in profiles are shown on the website.</p>
        <div class="form-row">
            <?= $input('social_facebook', 'url', 'For example https://www.facebook.com/yourpage', $url) ?>
            <?= $input('social_instagram', 'url', 'For example https://www.instagram.com/yourname', $url) ?>
        </div>
        <div class="form-row">
            <?= $input('social_tiktok', 'url', 'For example https://www.tiktok.com/@yourname', $url) ?>
            <?= $input('social_x', 'url', 'For example https://x.com/yourname', $url) ?>
        </div>
        <div class="form-row">
            <?= $input('social_youtube', 'url', 'For example https://www.youtube.com/@yourchannel', $url) ?>
            <?= $input('social_linkedin', 'url', 'For example https://www.linkedin.com/company/yourcompany', $url) ?>
        </div>
    </section>

    <div class="cluster form-actions">
        <button type="submit" class="btn btn--primary" data-submit-button>Save settings</button>
    </div>
</form>
