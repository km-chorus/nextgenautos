<?php

declare(strict_types=1);

/**
 * Latest Vehicles (spec 10B, laid out after the 2026-10-14 reference): the newest public vehicles directly below the
 * hero, as the same bordered cards the Vehicles page uses, then a "View All Vehicles" link. Presentation only.
 * Only real vehicles are shown: fewer than four are shown as they are. With no published vehicle (or when they cannot
 * be read: the cause is logged) the whole section is left out (owner request 2026-10-22).
 *
 * Expects: $latest = list of cards from PublicVehicleService::latest()|null (null = could not be read),
 *          $vehiclesPath = the Vehicles page path.
 */
if ($latest === null || $latest === []) {
    return;
}
?>
<section class="latest" aria-labelledby="latest-title">
    <div class="container">
        <header class="section-head">
            <h2 class="section-head__title" id="latest-title">Latest Vehicles</h2>
            <p class="section-head__intro">Explore some of the latest vehicles available through Next Gen Autos and Parts.</p>
        </header>

        <ul class="vehicle-grid latest__grid">
<?php foreach ($latest as $v): $i = 99; /* below the hero: the card lazy-loads its photograph */ require __DIR__ . '/vehicle_card.php'; endforeach; ?>
        </ul>

        <p class="latest__all"><a class="btn btn--outline" href="<?= e($vehiclesPath) ?>">View All Vehicles<?= public_icon('arrow-right') ?></a></p>
    </div>
</section>
