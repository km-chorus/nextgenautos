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
 * Spec 27: only parts WITH a price are shown here (owner decision 2026-10-26); the card is includes/part_card.php.
 *
 * With no published part (or when parts cannot be read: the cause is logged) the whole section is left out (owner
 * request 2026-10-22). A filter only counts when it names a type or make with published parts, so an empty list here
 * always means there is nothing to show.
 */
if ($parts === null || $parts['cards'] === []) {
    return;
}

$shown = PublicPartService::SHOWN;
$chipHref = static fn (string $key, ?string $value): string => $value === null ? '/#parts' : '/?' . http_build_query([$key => $value]) . '#parts';
$active = ($parts['type'] !== null ? 'type' : ($parts['make'] !== null ? 'make' : null));
$groups = [
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
<?php $cardHidden = $n >= $shown; require __DIR__ . '/part_card.php'; ?>
<?php endforeach; ?>
        </ul>
        <p class="parts-latest__none" data-parts-none hidden>No parts match this choice yet.</p>

        <p class="latest__all"><a class="btn btn--outline" href="/parts/">View All Parts &amp; Accessories<?= public_icon('arrow-right') ?></a></p>
    </div>
</section>
