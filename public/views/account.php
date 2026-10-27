<?php

declare(strict_types=1);

/**
 * Customer dashboard (spec 22; account page of spec 13). Presentation only; every value is escaped here.
 * Shows, for the signed-in account only: email confirmation (with a resend button), shipment tracking (only once the
 * email is confirmed), favourite vehicles and reservation requests, then the account details and sign out.
 *
 * Expects $data: customer (id, name, email, verified) or null, csrf, shipments (ShipmentService::forCustomer),
 * favourites (CustomerDashboardService::favourites), reservations (CustomerDashboardService::reservationRequests),
 * verificationNotice (?array{0:type,1:text} from a fixed list), message (?string), unavailable (bool).
 */
$c = $data['customer'] ?? null;
$csrf = (string) ($data['csrf'] ?? '');
?>
<?= public_page_header('My Account') ?>

<div class="container section dash">
<?php
if (!empty($data['verificationNotice'])) { public_toast($page, (string) $data['verificationNotice'][0], (string) $data['verificationNotice'][1]); }
if (!empty($data['message'])) { public_toast($page, 'error', (string) $data['message']); }
?>

<?php if ($c !== null): ?>
<?php if (!$c['verified']): ?>
    <section class="dash-verify" aria-labelledby="verify-title">
        <span class="dash-verify__icon" aria-hidden="true"><?= public_icon('mail') ?></span>
        <div>
            <h2 class="dash-verify__title" id="verify-title">Please confirm your email address</h2>
            <p>We sent a confirmation link to <strong><?= e((string) $c['email']) ?></strong>. Confirming it proves this address is yours, so the shipments our team records for you can appear here.</p>
            <form method="post" action="/account/" class="dash-verify__form">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="resend_verification">
                <button type="submit" class="btn btn--outline">Send the link again</button>
            </form>
        </div>
    </section>
<?php endif; ?>

    <div class="dash-grid">
        <section class="dash-panel dash-panel--wide" aria-labelledby="dash-track-title">
            <header class="dash-panel__head">
                <h2 class="dash-panel__title" id="dash-track-title"><?= public_icon('route') ?><span>Shipment tracking</span></h2>
                <a class="dash-panel__link" href="/track/">Full tracking details<?= public_icon('arrow-right') ?></a>
            </header>
<?php if (!$c['verified']): ?>
            <p class="dash-empty">Confirm your email address to see your shipments here.</p>
<?php elseif ($data['shipments'] === []): ?>
            <p class="dash-empty">No shipment is linked to your account yet. It appears here once our team has recorded it.</p>
<?php else: ?>
            <ul class="dash-list">
<?php foreach ($data['shipments'] as $s): ?>
                <li class="dash-ship">
                    <div class="dash-ship__main">
                        <p class="dash-ship__vehicle"><?= e($s['vehicle']) ?></p>
                        <p class="dash-ship__meta">Container: <?= $s['container'] !== null ? '<strong>' . e($s['container']) . '</strong>' : 'not available yet' ?> · <?= e((string) $s['line']) ?> · Updated <?= e($s['updated']) ?></p>
<?php if ($s['tracking'] !== null): ?>
                        <p class="dash-ship__track"><a href="<?= e($s['tracking']) ?>" target="_blank" rel="noopener noreferrer">Track on <?= e(ExternalLinksService::TRACKING_PROVIDER_NAME) ?><?= public_icon('arrow-right') ?><span class="sr-only"> (opens the official tracking page in a new tab)</span></a></p>
<?php endif; ?>
                    </div>
                    <p class="dash-badge"><?= e($s['status']) ?></p>
                </li>
<?php endforeach; ?>
            </ul>
<?php endif; ?>
        </section>

        <section class="dash-panel" aria-labelledby="dash-fav-title">
            <header class="dash-panel__head">
                <h2 class="dash-panel__title" id="dash-fav-title"><?= public_icon('heart') ?><span>Favourite vehicles</span></h2>
                <a class="dash-panel__link" href="/vehicles/">Browse vehicles<?= public_icon('arrow-right') ?></a>
            </header>
<?php if ($data['favourites'] === []): ?>
            <p class="dash-empty">No favourites yet. Press the heart on any vehicle to save it here.</p>
<?php else: ?>
            <ul class="dash-list">
<?php foreach ($data['favourites'] as $f): ?>
                <li class="dash-fav<?= $f['available'] ? '' : ' dash-fav--gone' ?>">
                    <span class="dash-fav__thumb" aria-hidden="true">
<?php if ($f['image'] !== null): ?>
                        <img src="<?= e($f['image']['url']) ?>" width="<?= (int) $f['image']['width'] ?>" height="<?= (int) $f['image']['height'] ?>" alt="" loading="lazy" decoding="async">
<?php else: ?>
                        <?= public_icon('car') ?>
<?php endif; ?>
                    </span>
                    <div class="dash-fav__main">
                        <p class="dash-fav__title"><?php if ($f['url'] !== null): ?><a href="<?= e($f['url']) ?>"><?= e($f['title']) ?></a><?php else: ?><?= e($f['title']) ?><?php endif; ?></p>
                        <p class="dash-fav__meta"><?= $f['price'] !== null ? e($f['price']) . ' · ' : '' ?><?= e($f['status']) ?></p>
                    </div>
                    <form method="post" action="/favourites/">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="vehicle" value="<?= e($f['slug']) ?>">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="return" value="/account/">
                        <button type="submit" class="dash-remove">Remove<span class="sr-only"> <?= e($f['title']) ?> from favourites</span></button>
                    </form>
                </li>
<?php endforeach; ?>
            </ul>
<?php endif; ?>
        </section>

        <section class="dash-panel" aria-labelledby="dash-res-title">
            <header class="dash-panel__head">
                <h2 class="dash-panel__title" id="dash-res-title"><?= public_icon('lock') ?><span>Reservation requests</span></h2>
            </header>
<?php if ($data['reservations'] === []): ?>
            <p class="dash-empty">No reservation requests yet. Requests you send from a vehicle page while signed in appear here.</p>
<?php else: ?>
            <ul class="dash-list">
<?php foreach ($data['reservations'] as $res): ?>
                <li class="dash-ship">
                    <div class="dash-ship__main">
                        <p class="dash-ship__vehicle"><?php if ($res['url'] !== null): ?><a href="<?= e($res['url']) ?>"><?= e($res['title']) ?></a><?php else: ?><?= e($res['title']) ?><?php endif; ?></p>
                        <p class="dash-ship__meta">Sent <?= e($res['sent']) ?></p>
                    </div>
                    <p class="dash-badge dash-badge--light"><?= e($res['status']) ?></p>
                </li>
<?php endforeach; ?>
            </ul>
            <p class="dash-note">A request is a reservation once our team has confirmed it with you by phone.</p>
<?php endif; ?>
        </section>
    </div>

    <section class="dash-account" aria-labelledby="dash-account-title">
        <h2 class="dash-account__title" id="dash-account-title">Account details</h2>
        <dl class="dash-account__details">
            <div><dt>Name</dt><dd><?= e((string) $c['name']) ?></dd></div>
            <div><dt>Email</dt><dd><?= e((string) $c['email']) ?> <span class="dash-badge <?= $c['verified'] ? 'dash-badge--ok' : 'dash-badge--light' ?>"><?= $c['verified'] ? 'Confirmed' : 'Not confirmed' ?></span></dd></div>
        </dl>
        <form method="post" action="/account/">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <button type="submit" class="btn btn--outline">Sign out</button>
        </form>
    </section>
<?php endif; ?>
</div>
