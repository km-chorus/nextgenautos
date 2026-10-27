<?php

declare(strict_types=1);

/**
 * Homepage "About Next Gen Autos and Parts" (spec 11C; redesigned 2026-10-26 at the owner's request). A two-column
 * composition: the photograph with an offset frame and two small information cards (route Canada to Ghana; shipping line)
 * beside the introduction: label, headline, one lead paragraph, four features with icons, and two actions.
 *
 * Only approved, true content: what the company does (vehicle imports from Canada to Ghana; car parts for popular makes,
 * owner statement of 2026-10-14), that it ships with Hapag-Lloyd (owner, 2026-10-22) and what the website really does
 * (status emails, tracking). No history, figures, awards or superlatives. No JavaScript.
 *
 * Expects: $router (PublicRouter).
 */
$vehiclesPath = $router->byName('vehicles')['path'] ?? '/vehicles/';
$aboutPath = $router->byName('about')['path'] ?? '/about/';   // the About page exists since 2026-10-26
$features = [
    ['car', 'Quality vehicles', 'Sourced in Canada and shipped to Ghana.'],
    ['engine', 'Car parts', 'For popular makes such as Toyota, Honda and Hyundai.'],
    ['route', 'Clear import steps', 'From choosing a vehicle to shipping and delivery.'],
    ['mail', 'Shipment updates', 'An email at every status change, and live tracking.'],
];
?>
<section class="about" aria-labelledby="about-title">
    <div class="container about__grid">
        <div class="about__media">
            <div class="about__frame" aria-hidden="true"></div>
            <figure class="about__photo">
                <img src="<?= public_asset('images/about/about.webp') ?>" width="800" height="1000" alt="Two vehicles loaded on a car carrier truck" loading="lazy" decoding="async">
            </figure>
            <div class="about__chip about__chip--route">
                <span class="about__chip-icon" aria-hidden="true"><?= public_icon('route') ?></span>
                <span><strong>Canada <span aria-hidden="true">&rarr;</span><span class="sr-only">to</span> Ghana</strong><small>Vehicle import route</small></span>
            </div>
            <div class="about__chip about__chip--line">
                <small>Shipping line</small>
                <strong>Hapag-Lloyd</strong>
            </div>
        </div>

        <div class="about__content">
            <p class="about__eyebrow">About <?= e(PUBLIC_SITE_NAME) ?></p>
            <h2 class="about__title" id="about-title">Quality vehicles from Canada, and the parts to keep them running.</h2>
            <p class="about__lead"><?= e(PUBLIC_SITE_NAME) ?> operates between Canada and Ghana, helping customers source quality vehicles and handle the import journey with clear steps, so you always know what happens next.</p>

            <ul class="about__features">
<?php foreach ($features as [$icon, $title, $text]): ?>
                <li class="about__feature">
                    <span class="about__feature-icon" aria-hidden="true"><?= public_icon($icon) ?></span>
                    <span class="about__feature-body"><strong><?= e($title) ?></strong><span><?= e($text) ?></span></span>
                </li>
<?php endforeach; ?>
            </ul>

            <div class="about__actions">
                <a class="btn btn--primary about__cta" href="<?= e($vehiclesPath) ?>">Browse Vehicles<?= public_icon('arrow-right') ?></a>
                <a class="about__link" href="<?= e($aboutPath) ?>">More about us<?= public_icon('arrow-right') ?></a>
            </div>
        </div>
    </div>
</section>
