<?php

declare(strict_types=1);

/**
 * Latest Parts & Accessories (spec 16 phase 1, owner request 2026-10-16), after Browse by Brand on the homepage.
 * Presentation only; every value is escaped here.
 *
 * Two ways to narrow the cards: "Browse by type" (part categories) and "Browse by brand" (the vehicle makes the parts
 * fit). Each choice is a real link (?parts_type= / ?parts_make=) answered by the server, so it works without
 * JavaScript; parts.js turns the two groups into tabs and filters the cards already on the page without a reload.
 * Each card lists the vehicles the part fits. There is no part page yet (phase 2), so a card's actions are
 * "Ask about this part" (the Contact form, pre-filled) and WhatsApp when a number is configured.
 *
 * Expects: $parts = PublicPartService::homepage() result | null (null = could not be read), $whatsappDigits = ?string.
 */

$shown = PublicPartService::SHOWN;
$chipHref = static fn (string $key, ?string $value): string => $value === null ? '/#parts' : '/?' . http_build_query([$key => $value]) . '#parts';
$active = $parts === null ? null : ($parts['type'] !== null ? 'type' : ($parts['make'] !== null ? 'make' : null));
$groups = $parts === null ? [] : [
    'type' => ['title' => 'Browse by type', 'key' => 'parts_type', 'options' => $parts['types'], 'current' => $parts['type'], 'all' => 'All types'],
    'make' => ['title' => 'Browse by brand', 'key' => 'parts_make', 'options' => $parts['makes'], 'current' => $parts['make'], 'all' => 'All brands'],
];
?>
<section class="parts-latest" id="parts" aria-labelledby="parts-title" data-parts data-parts-shown="<?= $shown ?>"<?= $active !== null ? ' data-parts-active="' . $active . '"' : '' ?>>
    <div class="container">
        <header class="section-head">
            <h2 class="section-head__title" id="parts-title">Latest Parts &amp; Accessories</h2>
            <p class="section-head__intro">Car parts and accessories for popular makes. Every part lists the vehicles it fits.</p>
        </header>

<?php if ($parts === null): ?>
        <p class="latest__note">Parts could not be loaded right now. Please try again shortly.</p>
<?php elseif ($parts['cards'] === []): ?>
        <div class="latest__empty">
            <h3 class="latest__empty-title">Parts &amp; Accessories Coming Soon</h3>
            <p>We're adding our parts and accessories. Check back soon, or contact us about the part you need.</p>
        </div>
<?php else: ?>
        <div class="parts-browse" data-parts-browse>
            <div class="parts-tabs" data-parts-tablist hidden>
<?php foreach ($groups as $name => $g): ?>
                <button class="parts-tabs__tab" type="button" id="parts-tab-<?= $name ?>" data-parts-tab="<?= $name ?>"><?= e($g['title']) ?></button>
<?php endforeach; ?>
            </div>
<?php foreach ($groups as $name => $g): ?>
            <div class="parts-panel" id="parts-panel-<?= $name ?>" data-parts-panel="<?= $name ?>">
                <h3 class="parts-panel__title" data-parts-panel-title><?= e($g['title']) ?></h3>
                <ul class="parts-chips">
                    <li><a class="parts-chip" href="<?= e($chipHref($g['key'], null)) ?>" data-parts-filter="<?= $name ?>" data-parts-value=""<?= $active === null || ($active === $name && $g['current'] === null) ? ' aria-current="true"' : '' ?>><?= e($g['all']) ?></a></li>
<?php foreach ($g['options'] as $o): ?>
                    <li><a class="parts-chip" href="<?= e($chipHref($g['key'], $o['slug'])) ?>" data-parts-filter="<?= $name ?>" data-parts-value="<?= e($o['slug']) ?>"<?= $g['current'] === $o['slug'] ? ' aria-current="true"' : '' ?>><?= e($o['name']) ?></a></li>
<?php endforeach; ?>
                </ul>
            </div>
<?php endforeach; ?>
        </div>

        <p class="sr-only" role="status" aria-live="polite" data-parts-status></p>
        <ul class="parts-grid" data-parts-grid>
<?php foreach ($parts['cards'] as $n => $p): ?>
            <li class="part-card" data-type="<?= e($p['type_slug']) ?>" data-makes="<?= e(implode(' ', $p['makes'])) ?>"<?= $n >= $shown ? ' hidden' : '' ?>>
                <div class="part-card__media">
<?php if ($p['image'] !== null): ?>
                    <img src="<?= e($p['image']['url']) ?>" width="<?= (int) $p['image']['width'] ?>" height="<?= (int) $p['image']['height'] ?>" alt="" loading="lazy" decoding="async">
<?php else: ?>
                    <span class="part-card__placeholder"><?= public_icon('engine') ?><span>Photo coming soon</span></span>
<?php endif; ?>
                    <p class="part-card__stock<?= $p['in_stock'] ? '' : ' part-card__stock--out' ?>"><span class="sr-only">Stock: </span><?= $p['in_stock'] ? 'In stock' : 'Out of stock' ?></p>
                </div>
                <div class="part-card__body">
                    <p class="part-card__meta"><?= e($p['type']) ?> · <?= e($p['condition']) ?></p>
                    <h3 class="part-card__title"><?= e($p['name']) ?></h3>
<?php if ($p['part_number'] !== null): ?>
                    <p class="part-card__number">Part no. <?= e($p['part_number']) ?></p>
<?php endif; ?>
                    <div class="part-card__fits">
                        <p class="part-card__fits-label"><?= public_icon('car') ?><span>Compatible with</span></p>
<?php if ($p['fits'] === []): ?>
                        <p class="part-card__fits-none">Contact us to confirm it fits your vehicle.</p>
<?php else: ?>
                        <ul class="part-card__fits-list">
<?php foreach ($p['fits'] as $fit): ?>
                            <li><?= e($fit) ?></li>
<?php endforeach; ?>
<?php if ($p['fits_more'] > 0): ?>
                            <li class="part-card__fits-more">and <?= (int) $p['fits_more'] ?> more</li>
<?php endif; ?>
                        </ul>
<?php endif; ?>
                    </div>
                    <p class="part-card__price"><?= e($p['price']) ?></p>
                    <div class="part-card__actions">
                        <a class="btn btn--outline part-card__ask" href="/contact/?<?= e(http_build_query(['part' => $p['slug']])) ?>#message">Ask about this part<span class="sr-only">: <?= e($p['name']) ?></span></a>
<?php if ($whatsappDigits !== null && ($wa = CompanyContactService::whatsappLink($whatsappDigits, 'Hello, I am interested in this part: ' . $p['name'] . ($p['part_number'] !== null ? ' (part number ' . $p['part_number'] . ')' : '') . '.')) !== null): ?>
                        <a class="btn part-card__whatsapp" href="<?= e($wa) ?>" target="_blank" rel="noopener noreferrer"><?= public_icon('message') ?><span class="sr-only">Ask about <?= e($p['name']) ?> on WhatsApp (opens WhatsApp in a new tab)</span></a>
<?php endif; ?>
                    </div>
                </div>
            </li>
<?php endforeach; ?>
        </ul>
        <p class="parts-latest__none" data-parts-none hidden>No parts match this choice yet.</p>
<?php endif; ?>

        <p class="latest__all"><a class="btn btn--outline" href="/parts/">View All Parts &amp; Accessories<?= public_icon('arrow-right') ?></a></p>
    </div>
</section>
