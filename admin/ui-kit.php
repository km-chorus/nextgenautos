<?php

declare(strict_types=1);

/**
 * Admin component library: a development reference page that shows every
 * reusable UI component in one place so it can be reviewed and tested.
 * All content here is structural placeholder text, not business data.
 *
 * Development only: not served when APP_ENV=production (checked through the
 * central AppConfig, so a .env value counts too). Protected by the admin guard.
 * Remove before launch if no longer needed.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';

$admin = requireAdmin();

if (AppConfig::isProduction()) {
    http_response_code(404);
    exit;
}

$pageTitle   = 'Component library';
$activeNav   = '';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Component library', null]];

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<div class="stack--lg stack">

    <?= admin_alert(
        'warning',
        'Development reference',
        'This page demonstrates the admin interface components. It contains placeholder text only and is not part of the production site.'
    ) ?>

    <!-- Colors -->
    <section class="page-section" aria-labelledby="kit-colors">
        <h2 class="section-heading" id="kit-colors">Colors</h2>
        <p class="section-intro">Approved palette. The accent is used for primary actions and the selected navigation item.</p>
        <div class="kit-swatches">
            <div class="kit-swatch"><div class="kit-swatch__chip swatch-charcoal"></div><div class="kit-swatch__meta"><strong>Deep Charcoal</strong><span>#111315</span></div></div>
            <div class="kit-swatch"><div class="kit-swatch__chip swatch-warm"></div><div class="kit-swatch__meta"><strong>Warm White</strong><span>#F8F7F4</span></div></div>
            <div class="kit-swatch"><div class="kit-swatch__chip swatch-white"></div><div class="kit-swatch__meta"><strong>White</strong><span>#FFFFFF</span></div></div>
            <div class="kit-swatch"><div class="kit-swatch__chip swatch-muted"></div><div class="kit-swatch__meta"><strong>Secondary Text</strong><span>#62666A</span></div></div>
            <div class="kit-swatch"><div class="kit-swatch__chip swatch-border"></div><div class="kit-swatch__meta"><strong>Border</strong><span>#E3E3DF</span></div></div>
            <div class="kit-swatch"><div class="kit-swatch__chip swatch-accent"></div><div class="kit-swatch__meta"><strong>Primary Accent</strong><span>#9E2F2F</span></div></div>
        </div>
    </section>

    <!-- Typography -->
    <section class="page-section" aria-labelledby="kit-type">
        <h2 class="section-heading" id="kit-type">Typography</h2>
        <p class="section-intro">Manrope, weights 400 to 700. Admin screens use a compact scale.</p>
        <div class="card kit-type">
            <h3 class="card__title">Heading level 3 (18px, 700)</h3>
            <p>Body text (15px, 400). Admin interfaces favour clarity, speed and information density over marketing-style headings.</p>
            <p class="text-sm text-muted">Small and supporting text (14px, secondary color).</p>
            <p><strong>Emphasis uses weight 600 to 700</strong>, not extra colors.</p>
        </div>
    </section>

    <!-- Buttons -->
    <section class="page-section" aria-labelledby="kit-buttons">
        <h2 class="section-heading" id="kit-buttons">Buttons</h2>
        <p class="section-intro">Four variants. Each supports hover, focus, active, disabled and loading states.</p>
        <div class="card stack">
            <div>
                <p class="kit-label">Variants</p>
                <div class="cluster">
                    <button type="button" class="btn btn--primary">Primary</button>
                    <button type="button" class="btn btn--secondary">Secondary</button>
                    <button type="button" class="btn btn--ghost">Ghost</button>
                    <button type="button" class="btn btn--danger">Destructive</button>
                </div>
            </div>
            <div>
                <p class="kit-label">With icon and small size</p>
                <div class="cluster">
                    <button type="button" class="btn btn--primary"><?= admin_icon('plus') ?>Add item</button>
                    <button type="button" class="btn btn--secondary btn--sm">Small secondary</button>
                    <button type="button" class="btn btn--ghost btn--icon" aria-label="Open settings"><?= admin_icon('settings') ?></button>
                </div>
            </div>
            <div>
                <p class="kit-label">Disabled</p>
                <div class="cluster">
                    <button type="button" class="btn btn--primary" disabled>Primary</button>
                    <button type="button" class="btn btn--secondary" disabled>Secondary</button>
                    <button type="button" class="btn btn--danger" disabled>Destructive</button>
                </div>
            </div>
            <div>
                <p class="kit-label">Loading (select to preview)</p>
                <div class="cluster">
                    <button type="button" class="btn btn--primary" data-demo-loading>Save changes</button>
                    <button type="button" class="btn btn--primary is-loading" aria-busy="true" aria-disabled="true">Saving</button>
                </div>
            </div>
        </div>
    </section>

    <!-- Forms -->
    <section class="page-section" aria-labelledby="kit-forms">
        <h2 class="section-heading" id="kit-forms">Form controls</h2>
        <p class="section-intro">Every control has a visible label. Errors combine an icon and text, never color alone. The fields here do not submit anywhere.</p>
        <div class="kit-grid kit-grid--2">
            <div class="card">
                <fieldset class="form-section">
                    <legend>Default states</legend>
                    <div class="field">
                        <label class="label" for="kit-text">Text field</label>
                        <input class="input" type="text" id="kit-text" aria-describedby="kit-text-hint">
                        <p class="hint" id="kit-text-hint">Helper text explains the expected value.</p>
                    </div>
                    <div class="field">
                        <label class="label" for="kit-select">Select</label>
                        <select class="select" id="kit-select">
                            <option>First option</option>
                            <option>Second option</option>
                        </select>
                    </div>
                    <div class="field">
                        <label class="label" for="kit-textarea">Text area <span class="label__note">(optional)</span></label>
                        <textarea class="textarea" id="kit-textarea"></textarea>
                    </div>
                    <div class="field">
                        <label class="check">
                            <input type="checkbox">
                            <span class="check__text">Checkbox option</span>
                        </label>
                    </div>
                </fieldset>
            </div>

            <div class="card">
                <fieldset class="form-section">
                    <legend>Validation and disabled states</legend>
                    <div class="field">
                        <label class="label" for="kit-invalid">Field with an error</label>
                        <input class="input" type="text" id="kit-invalid" aria-invalid="true" aria-describedby="kit-invalid-error">
                        <p class="field-error" id="kit-invalid-error"><?= admin_icon('alert-circle', 'icon--sm') ?><span>Enter a value for this field.</span></p>
                    </div>
                    <div class="field">
                        <label class="label" for="kit-disabled">Disabled field</label>
                        <input class="input" type="text" id="kit-disabled" value="Not editable" disabled>
                    </div>
                    <div class="field">
                        <label class="label" for="kit-password">Password with show/hide</label>
                        <div class="input-group">
                            <input class="input" type="password" id="kit-password" autocomplete="off">
                            <button type="button" class="btn btn--ghost input-group__button" data-password-toggle
                                    aria-controls="kit-password" aria-label="Show password" aria-pressed="false" hidden>
                                <?= admin_icon('eye') ?>
                            </button>
                        </div>
                    </div>
                </fieldset>
            </div>
        </div>
    </section>

    <!-- Alerts -->
    <section class="page-section" aria-labelledby="kit-alerts">
        <h2 class="section-heading" id="kit-alerts">Alerts</h2>
        <p class="section-intro">Success, error, warning and information. Each has its own icon and a text label for assistive technology.</p>
        <div class="stack">
            <?= admin_alert('success', 'Changes saved', 'Your changes were saved successfully.') ?>
            <?= admin_alert('error', 'Something went wrong', 'The changes could not be saved. Check the highlighted fields and try again.') ?>
            <?= admin_alert('warning', 'Check before continuing', 'This action affects information shown to customers.') ?>
            <?= admin_alert('info', 'For your information', 'Informational messages are not urgent.') ?>
            <?= admin_alert('info', 'Dismissible alert', 'Select the close button to remove this message.', true) ?>
        </div>
    </section>

    <!-- Badges -->
    <section class="page-section" aria-labelledby="kit-badges">
        <h2 class="section-heading" id="kit-badges">Status badges</h2>
        <p class="section-intro">Tones are assigned by status in one place (admin_status_badge), so a status always looks the same. The label is always shown.</p>
        <div class="card">
            <div class="cluster">
                <?php foreach (admin_status_keys() as $statusKey): ?>
                    <?= admin_status_badge($statusKey) ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Table -->
    <section class="page-section" aria-labelledby="kit-table">
        <h2 class="section-heading" id="kit-table">Tables</h2>
        <p class="section-intro">Wide tables scroll horizontally. Tables marked as stacked turn each row into a labelled block on narrow screens. The row below is placeholder text.</p>
        <div class="card card--flush">
            <div class="table-wrap" role="region" aria-labelledby="kit-table-caption" tabindex="0">
                <table class="table table--stack">
                    <caption id="kit-table-caption" class="sr-only">Table structure example with placeholder content</caption>
                    <thead>
                        <tr>
                            <th scope="col" aria-sort="ascending">Name <?= admin_icon('chevron-up', 'icon--sm') ?></th>
                            <th scope="col">Status</th>
                            <th scope="col">Reference</th>
                            <th scope="col" class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td data-label="Name">Placeholder row</td>
                            <td data-label="Status"><?= admin_status_badge('PENDING') ?></td>
                            <td data-label="Reference">Not applicable</td>
                            <td class="col-actions"><button type="button" class="btn btn--secondary btn--sm" disabled>View</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Empty and loading states -->
    <section class="page-section" aria-labelledby="kit-states">
        <h2 class="section-heading" id="kit-states">Empty and loading states</h2>
        <p class="section-intro">The empty-state action is disabled until the feature that owns it exists. Skeletons are subtle placeholders with a gentle pulse.</p>
        <div class="kit-grid kit-grid--2">
            <div class="card card--flush">
                <?= admin_empty_state(
                    'Nothing here yet',
                    'Items added to this area will appear here.',
                    ['label' => 'Add item', 'icon' => 'plus', 'disabled' => true]
                ) ?>
            </div>
            <div class="card card--flush">
                <?= admin_skeleton_rows(4) ?>
            </div>
        </div>
    </section>

    <!-- Cards -->
    <section class="page-section" aria-labelledby="kit-cards">
        <h2 class="section-heading" id="kit-cards">Cards</h2>
        <p class="section-intro">Cards group related content such as a form or a summary. They are not used to wrap every piece of information.</p>
        <div class="card">
            <h3 class="card__title">Card title</h3>
            <p class="text-muted">Supporting text for a grouped section of content.</p>
        </div>
    </section>
</div>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
