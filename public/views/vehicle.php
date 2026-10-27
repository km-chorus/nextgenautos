<?php

declare(strict_types=1);

/**
 * Vehicle detail page (spec 14_spec_vehicle_detail.md; layout redesigned 2026-10-21 after the owner's reference).
 * Presentation only; every value is escaped here.
 *
 * Layout: "Back to Vehicles"; the gallery (Exterior / Interior tabs when both kinds of photo exist, large photo, counter,
 * thumbnails) beside the summary (year · fuel, name, trim, price and status, "Reserve this vehicle", description, up to
 * four detail tiles, then Ask / WhatsApp / import duty); then a grey Specifications section of cards built from real
 * values only (never "N/A"); then the Reserve and Contact forms the buttons lead to.
 * Without JavaScript the first photo is shown and every thumbnail opens its photo; vehicle-detail.js adds the tabs,
 * thumbnail switching and previous / next.
 *
 * @var array<string,mixed> $data  vehicle (from PublicVehicleService::detail), form (kind, values, errors, message),
 *                                 sent ('contact'|'reserve'|null), prefill (name, email), tokens (contact, reserve: string|null),
 *                                 whatsapp (wa.me link|null)
 * @var array<string,mixed> $page  dutyUrl (approved external calculator)
 */
$v = $data['vehicle'];
$form = $data['form'];
$photos = $v['photos'];
$count = count($photos);
$dutyUrl = (string) ($page['dutyUrl'] ?? '');
$accident = $v['accident'] ?? null;   // spec 23: the damage disclosure of an accident vehicle, else null
$byType = ['exterior' => [], 'interior' => [], 'damage' => []];
foreach ($photos as $i => $photo) {
    $byType[$photo['type']][] = $i;
}
$byType = array_filter($byType);   // a tab only for a type that has photos
$tabs = count($byType) > 1;
$firstType = (string) (array_key_first($byType) ?? 'exterior');
$typeLabels = ['exterior' => 'Exterior', 'interior' => 'Interior', 'damage' => 'Damage'];
$typeIcons = ['exterior' => 'camera', 'interior' => 'image', 'damage' => 'alert-circle'];

/** Value for a field: what the visitor just sent (on an error), else a signed-in customer's details, else empty. */
$value = static function (string $kind, string $field) use ($form, $data): string {
    if ($form['kind'] === $kind) {
        return (string) ($form['values'][$field] ?? '');
    }

    return in_array($field, ['name', 'email'], true) ? (string) ($data['prefill'][$field] ?? '') : '';
};
$error = static fn (string $kind, string $field): ?string => $form['kind'] === $kind ? ($form['errors'][$field] ?? null) : null;

/** One labelled field of a form ($kind = 'reserve' | 'contact'), built by the shared public_enquiry_field(). */
$field = static fn (string $kind, string $name, string $label, string $type, bool $required, string $autocomplete, string $hint = ''): string
    => public_enquiry_field($kind, $name, $label, $type, $required, $autocomplete, $value($kind, $name), $error($kind, $name), $hint);
$hidden = static fn (string $kind, string $token): string => public_enquiry_hidden($kind, $kind, $token);
?>
<div class="vd">
    <div class="container vd__top">
<?php if ($accident !== null): ?>
        <a class="vd-back" href="/accident-vehicles/"><?= public_icon('arrow-left') ?><span>Back to Accident Vehicles</span></a>
<?php else: ?>
        <a class="vd-back" href="/vehicles/"><?= public_icon('arrow-left') ?><span>Back to Vehicles</span></a>
<?php endif; ?>

        <div class="vd__grid">
            <section class="vd-gallery" aria-label="Photos of the <?= e($v['title']) ?>" data-vd-gallery data-vd-first="<?= e($firstType) ?>">
<?php if ($tabs): ?>
                <div class="vd-tabs" data-vd-tabs hidden>
<?php foreach ($byType as $type => $indexes): ?>
                    <button class="vd-tabs__tab" type="button" id="vd-tab-<?= $type ?>" data-vd-tab="<?= $type ?>"><?= public_icon($typeIcons[$type]) ?><span><?= $typeLabels[$type] ?></span></button>
<?php endforeach; ?>
                </div>
<?php endif; ?>
<?php if ($count === 0): ?>
                <div class="vd-stage vd-stage--empty"><?= public_icon('car') ?><span>Photos coming soon</span></div>
<?php else: ?>
                <div class="vd-stage">
                    <ul class="vd-stage__slides">
<?php foreach ($photos as $i => $photo): ?>
                        <li class="vd-stage__slide<?= $i === ($byType[$firstType][0] ?? 0) ? ' is-active' : '' ?>" data-vd-slide data-vd-type="<?= $photo['type'] ?>">
                            <img src="<?= e($photo['large']['url']) ?>" width="<?= (int) $photo['large']['width'] ?>" height="<?= (int) $photo['large']['height'] ?>" alt="<?= e($photo['alt']) ?>"<?= $i === ($byType[$firstType][0] ?? 0) ? ' fetchpriority="high"' : ' loading="lazy"' ?> decoding="async">
                        </li>
<?php endforeach; ?>
                    </ul>
<?php if ($count > 1): ?>
                    <button class="vd-stage__nav vd-stage__nav--prev" type="button" data-vd-prev aria-label="Previous photo" hidden><?= public_icon('chevron-left') ?></button>
                    <button class="vd-stage__nav vd-stage__nav--next" type="button" data-vd-next aria-label="Next photo" hidden><?= public_icon('chevron-right') ?></button>
<?php endif; ?>
                    <p class="vd-stage__count" data-vd-count aria-live="polite"><?= public_icon($typeIcons[$firstType]) ?><span data-vd-count-text><?= $typeLabels[$firstType] ?> · 1/<?= count($byType[$firstType]) ?></span></p>
                </div>
<?php if ($count > 1): ?>
<?php foreach ($byType as $type => $indexes): ?>
                <div class="vd-thumbs" id="vd-panel-<?= $type ?>" data-vd-panel="<?= $type ?>">
<?php if ($tabs): ?>
                    <h2 class="vd-thumbs__title" data-vd-panel-title><?= $typeLabels[$type] ?> photos</h2>
<?php endif; ?>
                    <ul class="vd-thumbs__list">
<?php foreach ($indexes as $n => $i): $photo = $photos[$i]; ?>
                        <li><a class="vd-thumbs__item" href="<?= e($photo['large']['url']) ?>" data-vd-thumb="<?= $i ?>"<?= $i === ($byType[$firstType][0] ?? 0) ? ' aria-current="true"' : '' ?>><img src="<?= e($photo['thumb']['url']) ?>" width="<?= (int) $photo['thumb']['width'] ?>" height="<?= (int) $photo['thumb']['height'] ?>" alt="" loading="lazy" decoding="async"><span class="sr-only">Show <?= strtolower($typeLabels[$type]) ?> photo <?= $n + 1 ?> of <?= count($indexes) ?></span></a></li>
<?php endforeach; ?>
                    </ul>
                </div>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>
            </section>

            <div class="vd-summary">
                <p class="vd-summary__eyebrow"><?= e($v['eyebrow']) ?></p>
                <h1 class="vd-summary__title"><?= e($v['headline']) ?><span class="sr-only"> (<?= e($v['year']) ?>)</span></h1>
<?php if ($v['trim'] !== null): ?>
                <p class="vd-summary__trim"><?= e($v['trim']) ?></p>
<?php endif; ?>
                <div class="vd-summary__price-row">
                    <p class="vd-summary__price"><?= e($v['price']) ?></p>
                    <p class="vd-status<?= $v['reserved'] ? ' vd-status--reserved' : '' ?>"><span class="sr-only">Availability: </span><?= e($v['status']) ?></p>
                </div>
<?php if ($v['duty']): ?>
                <p class="vd-summary__duty">Price includes Ghana import duty.</p>
<?php endif; ?>
<?php if ($accident !== null): ?>
                <p class="vd-damage-flag"><?= public_icon('alert-circle') ?><span><strong>Accident-damaged.</strong> <a href="#damage">Read the damage details</a> before you reserve.</span></p>
<?php endif; ?>

<?php if (!$v['reserved']): ?>
                <a class="btn btn--primary vd-summary__cta" href="#reserve">Reserve this vehicle</a>
<?php elseif ($data['whatsapp'] !== null): ?>
                <a class="btn btn--primary vd-summary__cta" href="<?= e($data['whatsapp']) ?>" target="_blank" rel="noopener noreferrer">Ask about this vehicle<span class="sr-only"> on WhatsApp (opens WhatsApp in a new tab)</span></a>
<?php else: ?>
                <a class="btn btn--primary vd-summary__cta" href="#contact">Ask about this vehicle</a>
<?php endif; ?>

<?php if ($v['description'] !== null): ?>
                <p class="vd-summary__text"><?= nl2br(e($v['description'])) ?></p>
<?php endif; ?>

                <dl class="vd-tiles">
<?php foreach ($v['tiles'] as $tile): ?>
                    <div class="vd-tile">
                        <span class="vd-tile__icon" aria-hidden="true"><?= public_icon($tile['icon']) ?></span>
                        <dt><?= e($tile['label']) ?></dt>
                        <dd><?= e($tile['value']) ?></dd>
                    </div>
<?php endforeach; ?>
                </dl>

                <div class="vd-actions">
<?php $favSlug = (string) $v['slug']; $favTitle = (string) $v['title']; $favVariant = 'detail'; require dirname(__DIR__, 2) . '/includes/favourite_button.php'; ?>
<?php /* "Ask a question" opens WhatsApp with the Ghana number and a pre-filled message (owner request 2026-10-22); the contact form below remains for visitors without WhatsApp. */ ?>
<?php if ($data['whatsapp'] !== null): ?>
                    <a class="btn btn--outline vd-actions__btn" href="<?= e($data['whatsapp']) ?>" target="_blank" rel="noopener noreferrer"><?= public_icon('message') ?><span>Ask a question</span><span class="sr-only"> on WhatsApp about this vehicle (opens WhatsApp in a new tab)</span></a>
<?php elseif (!$v['reserved']): ?>
                    <a class="btn btn--outline vd-actions__btn" href="#contact"><?= public_icon('mail') ?><span>Ask a question</span></a>
<?php endif; ?>
<?php if ($dutyUrl !== ''): ?>
                    <a class="btn btn--outline vd-actions__btn" href="<?= e($dutyUrl) ?>" target="_blank" rel="noopener noreferrer"><?= public_icon('calculator') ?><span>Import duty</span><span class="sr-only">: calculate Ghana import duty (opens the external duty calculator in a new tab)</span></a>
<?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php if ($accident !== null): ?>
    <section class="vd-damage" id="damage" aria-labelledby="vd-damage-title">
        <div class="container">
            <h2 class="vd-spec-section__title" id="vd-damage-title">Accident damage</h2>
            <div class="vd-damage__grid">
                <div class="vd-damage__text">
                    <p><?= nl2br(e($accident['description'])) ?></p>
<?php if (isset($byType['damage'])): ?>
                    <p class="vd-damage__photos">See the <strong>Damage</strong> tab of the photos above.</p>
<?php endif; ?>
                </div>
                <dl class="vd-spec-card__rows vd-damage__facts">
<?php if ($accident['areas'] !== []): ?>
                    <div class="vd-spec-card__row"><dt>Damaged areas</dt><dd><?= e(implode(', ', $accident['areas'])) ?></dd></div>
<?php endif; ?>
<?php foreach ($accident['facts'] as [$label, $val]): ?>
                    <div class="vd-spec-card__row"><dt><?= e($label) ?></dt><dd><?= e($val) ?></dd></div>
<?php endforeach; ?>
                </dl>
            </div>
        </div>
    </section>
<?php endif; ?>

    <section class="vd-spec-section" aria-labelledby="vd-specs-title">
        <div class="container">
            <h2 class="vd-spec-section__title" id="vd-specs-title">Specifications</h2>
            <div class="vd-spec-grid">
<?php foreach ($v['groups'] as $group): ?>
                <section class="vd-spec-card">
                    <h3 class="vd-spec-card__title"><?= public_icon($group['icon']) ?><span><?= e($group['title']) ?></span></h3>
                    <dl class="vd-spec-card__rows">
<?php foreach ($group['rows'] as [$label, $val]): ?>
                        <div class="vd-spec-card__row"><dt><?= e($label) ?></dt><dd><?= e($val) ?></dd></div>
<?php endforeach; ?>
                    </dl>
                </section>
<?php endforeach; ?>
<?php if ($v['features'] !== []): ?>
                <section class="vd-spec-card">
                    <h3 class="vd-spec-card__title"><?= public_icon('check-circle') ?><span>Key features</span></h3>
                    <ul class="vd-spec-card__features">
<?php foreach ($v['features'] as $feature): ?>
                        <li><?= e($feature) ?></li>
<?php endforeach; ?>
                    </ul>
                </section>
<?php endif; ?>
                <section class="vd-spec-card">
                    <h3 class="vd-spec-card__title"><?= public_icon('route') ?><span>Shipping &amp; duty</span></h3>
<?php if ($v['shipping'] !== null): ?>
                    <p class="vd-spec-card__text"><?= nl2br(e($v['shipping'])) ?></p>
<?php endif; ?>
                    <dl class="vd-spec-card__rows">
                        <div class="vd-spec-card__row"><dt>Ghana import duty</dt><dd><?= $v['duty'] ? 'Included in the price' : 'Not included' ?></dd></div>
                    </dl>
<?php if (!$v['duty'] && $dutyUrl !== ''): ?>
                    <a class="vd-spec-card__link" href="<?= e($dutyUrl) ?>" target="_blank" rel="noopener noreferrer"><?= public_icon('calculator') ?><span>Calculate Ghana import duty</span><span class="sr-only"> (opens the external duty calculator in a new tab)</span></a>
<?php endif; ?>
                </section>
            </div>
        </div>
    </section>

<?php
// Spec 28: parts listed as compatible with this vehicle and marked In stock in the admin. Left out when there are none.
$vehicleParts = $data['vehicleParts'] ?? null;
if ($vehicleParts !== null && $vehicleParts['cards'] !== []):
    $whatsappDigits = $data['partsWhatsapp'] ?? null;
    $vehicleQuery = ['vehicle' => (string) $v['slug']];   // the contact form names this exact vehicle
    $vehicleLabel = (string) $v['title'];
?>
    <section class="vd-parts" id="parts" aria-labelledby="vd-parts-title">
        <div class="container">
            <header class="vd-parts__head">
                <div>
                    <h2 class="vd-spec-section__title" id="vd-parts-title">Parts for this vehicle</h2>
                    <p class="vd-parts__intro">Available parts listed as compatible with the <?= e($v['title']) ?>.</p>
                </div>
<?php if ($vehicleParts['total'] > count($vehicleParts['cards'])): ?>
                <a class="vd-parts__all" href="<?= e($vehicleParts['all']) ?>">See all <?= (int) $vehicleParts['total'] ?> parts<?= public_icon('arrow-right') ?></a>
<?php endif; ?>
            </header>
            <ul class="parts-grid">
<?php foreach ($vehicleParts['cards'] as $p): ?>
<?php $cardHidden = false; require dirname(__DIR__, 2) . '/includes/part_card.php'; ?>
<?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>

    <div class="container vd-forms">
        <section class="vd-form" id="reserve" aria-labelledby="reserve-title">
            <h2 class="vd-form__title" id="reserve-title">Reserve this vehicle</h2>
<?php if ($data['sent'] === 'reserve') { public_toast($page, 'success', 'Thank you. We have received your reservation request and will contact you to confirm.'); } ?>
<?php if ($v['reserved']): ?>
            <p class="vd-form__note">This vehicle is currently reserved. You can still <a href="#contact">ask us a question</a> about it.</p>
<?php elseif ($data['tokens']['reserve'] === null): ?>
            <p class="vd-form__note">Online requests are unavailable right now. Please contact us by phone or WhatsApp.</p>
<?php else: ?>
            <p class="vd-form__intro">Send a request and our team will call you to confirm. The vehicle is reserved for you once we confirm it. No payment is taken online.</p>
<?php if ($form['kind'] === 'reserve' && $form['message'] !== null) { public_toast($page, 'error', (string) $form['message']); } ?>
            <form class="form vd-form__form" method="post" action="<?= e($v['url']) ?>#reserve" novalidate data-guard-form>
                <?= $hidden('reserve', (string) $data['tokens']['reserve']) ?>
                <?= $field('reserve', 'name', 'Full name', 'text', true, 'name') ?>
                <?= $field('reserve', 'phone', 'Phone number', 'tel', true, 'tel', 'We will call this number to confirm.') ?>
                <?= $field('reserve', 'email', 'Email address', 'email', true, 'email') ?>
                <?= $field('reserve', 'message', 'Message', 'textarea', false, '') ?>
                <button class="btn btn--primary" type="submit">Send reservation request</button>
            </form>
<?php endif; ?>
        </section>

        <section class="vd-form" id="contact" aria-labelledby="contact-title">
            <h2 class="vd-form__title" id="contact-title">Ask about this vehicle</h2>
<?php if ($data['sent'] === 'contact') { public_toast($page, 'success', 'Thank you. We have received your message and will reply soon.'); } ?>
<?php if ($data['tokens']['contact'] === null): ?>
            <p class="vd-form__note">Online requests are unavailable right now. Please contact us by phone or WhatsApp.</p>
<?php else: ?>
            <p class="vd-form__intro">Questions about the vehicle, shipping or the price? Send us a message.</p>
<?php if ($form['kind'] === 'contact' && $form['message'] !== null) { public_toast($page, 'error', (string) $form['message']); } ?>
            <form class="form vd-form__form" method="post" action="<?= e($v['url']) ?>#contact" novalidate data-guard-form>
                <?= $hidden('contact', (string) $data['tokens']['contact']) ?>
                <?= $field('contact', 'name', 'Full name', 'text', true, 'name') ?>
                <?= $field('contact', 'email', 'Email address', 'email', true, 'email') ?>
                <?= $field('contact', 'phone', 'Phone number', 'tel', false, 'tel') ?>
                <?= $field('contact', 'message', 'Message', 'textarea', true, '') ?>
                <button class="btn btn--secondary" type="submit">Send message</button>
            </form>
<?php endif; ?>
        </section>
    </div>
</div>
