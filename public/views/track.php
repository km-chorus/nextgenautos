<?php

declare(strict_types=1);

/**
 * Track Your Vehicle (Track Vehicle page spec of 2026-10-21; spec 13 customer accounts; spec 20 shipments; business
 * rules 06 sections 7 to 9; simplified layout of 2026-10-22). Presentation only; every value is escaped here.
 *
 * Layout for a signed-in customer with a confirmed email (owner decisions 2026-10-22):
 *   1. A tracking number field (GET ?number=). The number is looked up ONLY among this customer's own shipments
 *      (ShipmentService::findContainer). Another customer's shipment is never shown.
 *   2. Before a search: "Your tracking numbers", the container number of each of the customer's shipments with a
 *      Copy button (or "not available yet"); no status is shown until the customer tracks a number (2026-10-22).
 *   3. After Track: found = the shipment update (vehicle photo and details, the long horizontal status bar of the six
 *      approved statuses, container, shipping line, estimated arrival, live tracking button); not found = "not on your
 *      account" with a link to the shipping line's official tracking page; invalid = inline error.
 *   4. One short line on how tracking works (customers are also emailed at every status change).
 * Other states: A guest (normally redirected to sign-in before this view), A2 email not confirmed, B no shipment
 * linked, D shipments could not be read. Nothing is invented: what was not recorded is named plainly. The website does
 * not track anything itself: only the shipping line's tracker shows where a container is.
 *
 * Expects $data: customer (?array), trackingUrl (validated https address of the official tracker), shipments
 * (ShipmentService::forCustomer; null = could not be read), search (ShipmentService::findContainer result), typed (the
 * number as typed, at most 40 characters, for the field after an invalid entry), csrf.
 */
$customer = $data['customer'] ?? null;
$shipments = $data['shipments'];
$search = $data['search'] ?? ['status' => 'none', 'number' => null, 'index' => null];
$trackingUrl = (string) $data['trackingUrl'];
$trackingName = (string) ($data['trackingName'] ?? 'the shipping line');   // Hapag-Lloyd (ExternalLinksService)
$verified = $customer !== null && !empty($customer['verified']);
$found = $search['status'] === 'found' && is_array($shipments) ? $shipments[$search['index']] : null;
$numberValue = $search['number'] ?? (string) ($data['typed'] ?? '');   // what the visitor typed (escaped below)
?>
<?= public_page_header('Track Your Vehicle') ?>

<div class="track-area">
<div class="container track">
<?php if ($customer === null): ?>
    <section class="track-state" aria-labelledby="track-guest-title">
        <span class="track-state__icon" aria-hidden="true"><?= public_icon('lock') ?></span>
        <div>
            <h2 class="track-state__title" id="track-guest-title">Sign in to see your shipment</h2>
            <p>Shipment details are only shown to the customer they belong to. Sign in, or create an account using the same email address you gave our team, and your shipment will appear here.</p>
            <div class="track-state__actions">
                <a class="btn btn--primary" href="/login/">Sign in</a>
                <a class="btn btn--outline" href="/register/">Create an account</a>
            </div>
        </div>
    </section>
<?php elseif (!$verified): ?>
    <section class="track-state" aria-labelledby="track-verify-title">
        <span class="track-state__icon" aria-hidden="true"><?= public_icon('mail') ?></span>
        <div>
            <h2 class="track-state__title" id="track-verify-title">Confirm your email to see your shipment</h2>
            <p>For your security, shipment details appear only once you have confirmed that <strong><?= e((string) $customer['email']) ?></strong> is your email address. Use the link we sent you.</p>
            <div class="track-state__actions">
                <form method="post" action="/account/"><input type="hidden" name="csrf_token" value="<?= e((string) ($data['csrf'] ?? '')) ?>"><input type="hidden" name="action" value="resend_verification"><button type="submit" class="btn btn--outline">Send the link again</button></form>
            </div>
        </div>
    </section>
<?php else: ?>
    <p class="track__signed-in">Signed in as <strong><?= e((string) $customer['name']) ?></strong>.</p>

    <section class="track-search" aria-labelledby="track-search-title">
        <h2 class="track-search__title" id="track-search-title">Enter your tracking number</h2>
        <form class="track-search__form" action="/track/" method="get" role="search">
            <label class="sr-only" for="track-number">Tracking number (container number)</label>
            <input class="track-search__input" type="text" id="track-number" name="number" value="<?= e($numberValue) ?>" maxlength="40"
                   placeholder="For example HLXU1234567" autocomplete="off" autocapitalize="characters" spellcheck="false" required
                   aria-describedby="track-number-hint<?= $search['status'] === 'invalid' ? ' track-number-error' : '' ?>"<?= $search['status'] === 'invalid' ? ' aria-invalid="true"' : '' ?>>
            <button class="btn btn--primary track-search__button" type="submit"><?= public_icon('search') ?><span>Track</span></button>
        </form>
        <p class="track-search__hint" id="track-number-hint">Your tracking number is the container number we sent you: 4 letters and 7 digits.</p>
        <p class="track-partner"><?= public_icon('route') ?><span>We ship our vehicles with <strong><?= e($trackingName) ?></strong>. You can also follow any container on <a href="<?= e($trackingUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($trackingName) ?>'s container tracking<span class="sr-only"> (opens <?= e($trackingName) ?>'s website in a new tab)</span></a>.</span></p>
<?php if ($search['status'] === 'invalid'): ?>
        <p class="track-search__error" id="track-number-error" role="alert"><?= public_icon('alert-circle') ?><span>That does not look like a container number. Enter 4 letters and 7 digits, for example HLXU1234567.</span></p>
<?php elseif ($search['status'] === 'not_found'): ?>
        <div class="track-search__result" role="status">
            <p><strong><?= e((string) $search['number']) ?></strong> is not a shipment on your account. Check the number, or look it up directly on <?= e($trackingName) ?>'s official tracking page.</p>
            <p class="track-search__links">
                <a class="btn btn--outline" href="<?= e($trackingUrl) ?>" target="_blank" rel="noopener noreferrer">Track on <?= e($trackingName) ?><?= public_icon('arrow-right') ?><span class="sr-only"> (opens <?= e($trackingName) ?>'s website in a new tab)</span></a>
            </p>
        </div>
<?php elseif ($search['status'] === 'found'): ?>
        <p class="track-search__found" role="status"><?= public_icon('check-circle') ?><span>Latest update for container <strong><?= e((string) $search['number']) ?></strong>.</span>
            <a class="track-search__all" href="/track/">Track another number</a>
        </p>
<?php endif; ?>
    </section>

<?php if ($shipments === null): ?>
    <p class="track__unavailable" role="status"><?= public_icon('info') ?><span>Your shipment details could not be loaded right now. Please try again shortly.</span></p>
<?php elseif ($shipments === []): ?>
    <section class="track-state" aria-labelledby="track-empty-title">
        <span class="track-state__icon" aria-hidden="true"><?= public_icon('route') ?></span>
        <div>
            <h2 class="track-state__title" id="track-empty-title">No shipment is linked to your account yet</h2>
            <p>Your shipment appears here once our team has recorded it. It is linked to the email address on your account<?= isset($customer['email']) ? ', <strong>' . e((string) $customer['email']) . '</strong>' : '' ?>.</p>
            <p>If you bought a vehicle with a different email address, or you expected to see a shipment, please let us know.</p>
            <div class="track-state__actions"><a class="btn btn--outline" href="/contact/">Contact us</a></div>
        </div>
    </section>
<?php elseif ($found !== null):
        $s = $found;
        $doneCount = count(array_filter($s['bar'], static fn (array $b): bool => $b['state'] !== 'todo'));
        $fill = $s['total'] > 1 ? round(($doneCount - 1) / ($s['total'] - 1) * 100, 2) : 100; ?>
    <section class="track-ships" aria-labelledby="ship-result-title">
        <article class="ship-card">
            <header class="ship-card__head">
                <div>
                    <h2 class="ship-card__eyebrow" id="ship-result-title">Shipment status</h2>
                    <p class="ship-card__updated">Last updated <?= e($s['updated']) ?></p>
                </div>
                <p class="ship-card__status"><span class="sr-only">Current status: </span><?= e($s['status']) ?></p>
            </header>
            <div class="ship-bar">
                <p class="sr-only">Step <?= $doneCount ?> of <?= (int) $s['total'] ?>: <?= e($s['status']) ?>.</p>
                <div class="ship-bar__track" aria-hidden="true"><span class="ship-bar__fill ship-bar__fill--<?= (int) round($fill / 20) * 20 ?>"></span></div>
                <ol class="ship-bar__steps">
<?php foreach ($s['bar'] as $step): ?>
                    <li class="ship-bar__step ship-bar__step--<?= e($step['state']) ?>"<?= $step['state'] === 'current' ? ' aria-current="step"' : '' ?>>
                        <span class="ship-bar__dot" aria-hidden="true"></span>
                        <span class="ship-bar__label"><span class="sr-only"><?= $step['state'] === 'done' ? 'Done: ' : ($step['state'] === 'current' ? 'Current: ' : 'Still to come: ') ?></span><?= e($step['label']) ?></span>
<?php if ($step['date'] !== null): ?>
                        <span class="ship-bar__date"><?= e($step['date']) ?></span>
<?php endif; ?>
                    </li>
<?php endforeach; ?>
                </ol>
            </div>

            <div class="ship-card__vehicle">
                <div class="ship-card__photo">
<?php if ($s['photo'] !== null): ?>
                    <img src="<?= e($s['photo']['url']) ?>" width="<?= (int) $s['photo']['width'] ?>" height="<?= (int) $s['photo']['height'] ?>" alt="<?= e($s['vehicle']) ?>" decoding="async">
<?php else: ?>
                    <span class="ship-card__no-photo"><?= public_icon('car') ?><span>No photo</span></span>
<?php endif; ?>
                </div>
                <div class="ship-card__intro">
                    <h3 class="ship-card__title"><?= e($s['vehicle']) ?></h3>
<?php if ($s['details'] !== []): ?>
                    <dl class="ship-card__specs">
<?php foreach ($s['details'] as [$label, $value]): ?>
                        <div><dt><?= e($label) ?></dt><dd><?= e($value) ?></dd></div>
<?php endforeach; ?>
                    </dl>
<?php endif; ?>
                </div>
            </div>

            <dl class="ship-card__facts">
                <div class="ship-card__fact">
                    <dt>Container number</dt>
                    <dd><span class="ship-card__container" data-copy-text><?= e((string) $s['container']) ?></span> <button class="ship-card__copy" type="button" data-copy hidden>Copy<span class="sr-only"> container number</span></button></dd>
                </div>
                <div class="ship-card__fact">
                    <dt>Shipping line</dt>
                    <dd<?= $s['line'] === null ? ' class="ship-card__pending"' : '' ?>><?= $s['line'] !== null ? e($s['line']) : 'Not recorded yet' ?></dd>
                </div>
<?php if ($s['estimate'] !== null): ?>
                <div class="ship-card__fact">
                    <dt>Estimated arrival <span class="ship-card__hint">(given by the shipping line; may change)</span></dt>
                    <dd><?= e($s['estimate']) ?></dd>
                </div>
<?php endif; ?>
            </dl>

<?php if ($s['tracking'] !== null): ?>
            <p class="ship-card__action"><a class="btn btn--primary" href="<?= e($s['tracking']) ?>" target="_blank" rel="noopener noreferrer">See live position on <?= $s['line'] !== null ? e($s['line']) : 'the shipping line\'s website' ?><?= public_icon('arrow-right') ?><span class="sr-only"> (opens the official tracking page in a new tab)</span></a></p>
<?php endif; ?>
        </article>
    </section>
<?php else: ?>
    <section class="track-numbers" aria-labelledby="track-numbers-title">
        <h2 class="track-numbers__title" id="track-numbers-title">Your tracking <?= count($shipments) === 1 ? 'number' : 'numbers' ?></h2>
        <p class="track-numbers__intro">Copy your tracking number and enter it above to see the latest update on your vehicle.</p>
        <ul class="track-numbers__list">
<?php foreach ($shipments as $s): ?>
            <li class="track-numbers__item">
                <span class="track-numbers__vehicle"><?= e($s['vehicle']) ?></span>
<?php if ($s['container'] !== null): ?>
                <span class="track-numbers__code"><span class="ship-card__container" data-copy-text><?= e($s['container']) ?></span><button class="ship-card__copy" type="button" data-copy hidden>Copy<span class="sr-only"> tracking number for <?= e($s['vehicle']) ?></span></button></span>
<?php else: ?>
                <span class="track-numbers__pending">Not available yet. The shipping line assigns it; we will email it to you.</span>
<?php endif; ?>
            </li>
<?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
<?php endif; ?>

    <p class="track__how"><strong>How tracking works:</strong> after purchase we arrange the shipment with <?= e($trackingName) ?>; the shipping line assigns a container and its number, which we record here and send to you. We email you every time the status changes; only the shipping line's own tracking page shows where the container is right now. Questions? <a href="/contact/">Contact us</a>.</p>
</div>
</div>
