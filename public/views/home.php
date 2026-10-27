<?php

declare(strict_types=1);

/**
 * Homepage (spec 10_spec_homepage_hero.md, revised): the hero with the vehicle search form, then Latest Vehicles
 * (spec 10B), Browse by Brand, Latest Parts & Accessories (spec 16), the import utilities and About.
 * "How It Works" and the team section were removed at the owner's request (2026-10-21).
 *
 * @var PublicRouter $router
 * @var array<string,mixed> $data  'hero' = slides from HomepageHeroService (empty until approved photographs exist)
 */

$slides = $data['hero'];
$count = count($slides);
$vehiclesPath = $router->byName('vehicles')['path'];
$searchOptions = $data['searchOptions'] ?? null;   // null = the lists could not be read: the hero shows without the search panel
$blank = 'data:image/gif;base64,R0lGODlhAQABAAAAACw=';
$content = require dirname(__DIR__, 2) . '/app/config/homepage_content.php';   // hero wording and trust cards
$trust = $content['trust'];
?>
<section class="hero<?= $count === 0 ? ' hero--plain' : '' ?>" aria-labelledby="hero-title"<?= $count > 1 ? ' data-hero' : '' ?>>
<?php if ($count > 0): ?>
    <div class="hero__slides" data-hero-slides>
<?php foreach ($slides as $i => $slide):
    $first = $i === 0;
    $wide = $slide['wide'];
    $desktopSet = $slide['desktop']['url'] . ' ' . $slide['desktop']['width'] . 'w' . ($wide !== null ? ', ' . $wide['url'] . ' ' . $wide['width'] . 'w' : '');
    $srcAttr = $first ? 'srcset' : 'data-srcset'; ?>
        <div class="hero__slide hero__slide--<?= e($slide['focus']) ?><?= $first ? ' is-active' : '' ?>" data-hero-slide>
            <picture>
                <source media="(max-width: 767.98px)" <?= $srcAttr ?>="<?= e($slide['mobile']['url']) ?>">
                <source media="(min-width: 768px)" <?= $srcAttr ?>="<?= e($desktopSet) ?>" sizes="100vw">
                <img src="<?= $first ? e($slide['desktop']['url']) : $blank ?>"<?= $first ? '' : ' data-src="' . e($slide['desktop']['url']) . '"' ?> width="<?= (int) $slide['desktop']['width'] ?>" height="<?= (int) $slide['desktop']['height'] ?>" alt="<?= e($slide['alt']) ?>" decoding="async"<?= $first ? ' fetchpriority="high"' : '' ?>>
            </picture>
        </div>
<?php endforeach; ?>
    </div>
    <div class="hero__overlay" aria-hidden="true"></div>
<?php endif; ?>

    <div class="container hero__content">
        <h1 class="hero__title" id="hero-title"><?= e($content['title']) ?></h1>
        <p class="hero__text"><?= e($content['text']) ?></p>
<?php if ($searchOptions !== null): ?>
        <div class="hero__search">
<?php
$filters = ['brand' => null, 'model' => null, 'year' => null, 'max_price' => null];
$searchId = 'hero';
require dirname(__DIR__, 2) . '/includes/vehicle_search.php';
?>
        </div>
<?php endif; ?>
    </div>

</section>

<?php /* Trust cards: outside the hero (which clips its overflow) and pulled up over its bottom edge by a negative offset, so they overlap the hero and the next section. */ ?>
<section class="trust" aria-labelledby="trust-title">
    <div class="container trust__inner">
        <h2 class="sr-only" id="trust-title">Why customers choose <?= e(PUBLIC_SITE_NAME) ?></h2>
        <ul class="trust__list">
<?php foreach ($trust as $item): ?>
            <li class="trust-card">
                <span class="trust-card__icon"><?= public_icon($item['icon']) ?></span>
                <h3 class="trust-card__title"><?= e($item['title']) ?></h3>
                <p class="trust-card__text"><?= e($item['text']) ?></p>
            </li>
<?php endforeach; ?>
        </ul>
    </div>
</section>

<?php $latest = $data['latest'] ?? null; require dirname(__DIR__, 2) . '/includes/latest_vehicles.php'; ?>

<?php $brands = $data['brands'] ?? null; require dirname(__DIR__, 2) . '/includes/brand_strip.php'; ?>

<?php $parts = $data['parts'] ?? null; $whatsappDigits = $data['whatsapp'] ?? null; require dirname(__DIR__, 2) . '/includes/latest_parts.php'; ?>


<?php $dutyUrl = $data['dutyUrl'] ?? ExternalLinksService::DUTY_CALCULATOR_DEFAULT; require dirname(__DIR__, 2) . '/includes/import_utilities.php'; ?>

<?php require dirname(__DIR__, 2) . '/includes/about.php'; ?>
