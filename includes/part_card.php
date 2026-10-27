<?php

declare(strict_types=1);

/**
 * One part card (homepage Latest Parts and the /parts/ page; spec 16, reworked for spec 27). Presentation only; every
 * value is escaped here. Shows only what the administrator entered:
 *   - condition and stock only when set; no price = "Price on request";
 *   - the main part number when there is one;
 *   - "Compatible with" up to 3 vehicles (+ "and N more"), only the vehicles the administrator entered; no block at all
 *     when none are entered (owner 2026-10-27: no "Confirm fit" line; never "fits all", nothing invented).
 * Actions: Request (no price) / Ask about this part (Contact form pre-filled with the part, and the customer's vehicle
 * when they used "fit my car"), and WhatsApp when a number is set.
 *
 * Expects: $p (PublicPartService card), $whatsappDigits (?string), and optionally $cardHidden (bool),
 * $vehicleQuery (array{make?,model?,year?} already validated) and $vehicleLabel (?string, e.g. "Toyota Corolla 2015").
 */
$cardHidden ??= false;
$vehicleQuery ??= [];
$vehicleLabel ??= null;
$askHref = '/contact/?' . http_build_query(['part' => $p['slug']] + $vehicleQuery) . '#message';
$askLabel = $p['price'] === null ? 'Request' : 'Ask about this part';
$waMessage = "Hello, I'm interested in this part: " . $p['name'] . ($p['part_number'] !== null ? ' (part number ' . $p['part_number'] . ')' : '') . ".\n"
    . 'My vehicle (make, model, year): ' . ($vehicleLabel ?? '');
$meta = implode(' · ', array_filter([$p['type'], $p['condition']]));
?>
            <li class="part-card" data-type="<?= e($p['type_slug']) ?>" data-makes="<?= e(implode(' ', $p['makes'])) ?>"<?= $cardHidden ? ' hidden' : '' ?>>
                <div class="part-card__media">
<?php if ($p['image'] !== null): ?>
                    <img src="<?= e($p['image']['url']) ?>" width="<?= (int) $p['image']['width'] ?>" height="<?= (int) $p['image']['height'] ?>" alt="<?= e($p['name']) ?>" loading="lazy" decoding="async">
<?php else: ?>
                    <span class="part-card__placeholder"><?= public_icon('engine') ?><span>Photo coming soon</span></span>
<?php endif; ?>
<?php if ($p['in_stock'] !== null): ?>
                    <p class="part-card__stock<?= $p['in_stock'] ? '' : ' part-card__stock--out' ?>"><span class="sr-only">Stock: </span><?= $p['in_stock'] ? 'In stock' : 'Out of stock' ?></p>
<?php endif; ?>
                </div>
                <div class="part-card__body">
                    <p class="part-card__meta"><?= e($meta) ?></p>
                    <h3 class="part-card__title"><?= e($p['name']) ?></h3>
<?php if ($p['part_number'] !== null): ?>
                    <p class="part-card__number">Part no. <?= e($p['part_number']) ?></p>
<?php endif; ?>
<?php if ($p['fits'] !== []): ?>
                    <div class="part-card__fits">
                        <p class="part-card__fits-label"><?= public_icon('car') ?><span>Compatible with</span></p>
                        <ul class="part-card__fits-list">
<?php foreach ($p['fits'] as $fit): ?>
                            <li><?= e($fit) ?></li>
<?php endforeach; ?>
<?php if ($p['fits_more'] > 0): ?>
                            <li class="part-card__fits-more">and <?= (int) $p['fits_more'] ?> more</li>
<?php endif; ?>
                        </ul>
                    </div>
<?php endif; ?>
                    <p class="part-card__price<?= $p['price'] === null ? ' part-card__price--request' : '' ?>"><?= $p['price'] === null ? 'Price on request' : e($p['price']) ?></p>
                    <div class="part-card__actions">
                        <a class="btn <?= $p['price'] === null ? 'btn--primary' : 'btn--outline' ?> part-card__ask" href="<?= e($askHref) ?>"><?= e($askLabel) ?><span class="sr-only">: <?= e($p['name']) ?></span></a>
<?php if ($whatsappDigits !== null && ($wa = CompanyContactService::whatsappLink($whatsappDigits, $waMessage)) !== null): ?>
                        <a class="btn part-card__whatsapp" href="<?= e($wa) ?>" target="_blank" rel="noopener noreferrer"><?= public_icon('message') ?><span class="sr-only">Ask about <?= e($p['name']) ?> on WhatsApp (opens in a new tab)</span></a>
<?php endif; ?>
                    </div>
                </div>
            </li>
