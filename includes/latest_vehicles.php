<?php

declare(strict_types=1);

/**
 * Latest Vehicles (spec 10B, laid out after the 2026-10-14 reference): the newest public vehicles directly below the
 * hero, as the same bordered cards the Vehicles page uses, then a "View All Vehicles" link. Presentation only.
 * Only real vehicles are shown: fewer than four are shown as they are, none gives the "coming soon" state.
 *
 * Expects: $latest = list of cards from PublicVehicleService::latest()|null (null = could not be read),
 *          $vehiclesPath = the Vehicles page path.
 */
?>
<section class="latest" aria-labelledby="latest-title">
    <div class="container">
        <header class="section-head">
            <h2 class="section-head__title" id="latest-title">Latest Vehicles</h2>
            <p class="section-head__intro">Explore some of the latest vehicles available through Amponsah Autos.</p>
        </header>

<?php if ($latest === null): ?>
        <p class="latest__note">Vehicles could not be loaded right now. Please try again shortly.</p>
<?php elseif ($latest === []): ?>
        <div class="latest__empty">
            <h3 class="latest__empty-title">Vehicles Coming Soon</h3>
            <p>We're preparing our latest available vehicles. Check back soon.</p>
        </div>
<?php else: ?>
        <ul class="vehicle-grid latest__grid">
<?php foreach ($latest as $v): $i = 99; /* below the hero: the card lazy-loads its photograph */ require __DIR__ . '/vehicle_card.php'; endforeach; ?>
        </ul>
<?php endif; ?>

        <p class="latest__all"><a class="btn btn--outline" href="<?= e($vehiclesPath) ?>">View All Vehicles<?= public_icon('arrow-right') ?></a></p>
    </div>
</section>
