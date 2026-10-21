<?php

declare(strict_types=1);

/**
 * Homepage "Plan Your Vehicle Import" (spec 11B): two utilities, one composition. LEFT (charcoal): Track Your Shipment,
 * which links to the site's own Track Vehicle page. RIGHT (white): Calculate Import Duty, which links to the approved
 * external calculator in a new tab. No JavaScript, no embedded or proxied service, no duty figures, no live tracking.
 * Presentation only.
 *
 * Expects: $router (PublicRouter) and $dutyUrl (a validated https address, from ExternalLinksService).
 */

$trackRoute = $router->byName('track');
$trackPath = $trackRoute['path'] ?? '/track/';
?>
<section class="utilities" aria-labelledby="utilities-title">
    <div class="container">
        <header class="utilities__head">
            <p class="utilities__eyebrow">Plan your vehicle import</p>
            <h2 class="utilities__title" id="utilities-title">Everything you need to stay informed about your vehicle.</h2>
            <p class="utilities__intro">Track your shipment and estimate your import duty using the tools provided for your vehicle journey.</p>
        </header>

        <div class="utilities__grid">
            <article class="utility utility--track">
                <div class="utility__media">
                    <img src="<?= public_asset("images/utilities/track-shipment.webp") ?>" width="960" height="720" alt="Aerial view of a container port terminal" loading="lazy" decoding="async">
                    <span class="utility__badge" aria-hidden="true"><?= public_icon('route') ?></span>
                </div>
                <div class="utility__body">
                    <h3 class="utility__title">Track Your Shipment</h3>
                    <p class="utility__text">Follow your vehicle's shipment using the tracking information provided by Amponsah Autos.</p>
                    <a class="utility__link" href="<?= e($trackPath) ?>">Track Shipment<?= public_icon('arrow-right') ?></a>
                </div>
            </article>

            <article class="utility utility--duty">
                <div class="utility__media utility__media--document">
                    <svg class="utility__sheet" viewBox="0 0 320 240" role="img" aria-hidden="true" focusable="false">
                        <rect x="92" y="22" width="136" height="196" rx="4" class="sheet__page"/>
                        <rect x="108" y="42" width="56" height="8" rx="2" class="sheet__bar sheet__bar--strong"/>
                        <rect x="108" y="64" width="104" height="5" rx="2" class="sheet__bar"/>
                        <rect x="108" y="80" width="88" height="5" rx="2" class="sheet__bar"/>
                        <rect x="108" y="96" width="104" height="5" rx="2" class="sheet__bar"/>
                        <rect x="108" y="124" width="104" height="5" rx="2" class="sheet__bar"/>
                        <rect x="108" y="140" width="72" height="5" rx="2" class="sheet__bar"/>
                        <rect x="108" y="156" width="96" height="5" rx="2" class="sheet__bar"/>
                        <circle cx="196" cy="190" r="14" class="sheet__stamp"/>
                    </svg>
                    <span class="utility__badge" aria-hidden="true"><?= public_icon('document') ?></span>
                </div>
                <div class="utility__body">
                    <h3 class="utility__title">Calculate Import Duty</h3>
                    <p class="utility__text">Estimate the import duty for your vehicle using the external duty calculator. Have your vehicle details available when using the calculator.</p>
                    <a class="utility__link" href="<?= e($dutyUrl) ?>" target="_blank" rel="noopener noreferrer">Calculate Duty<?= public_icon('arrow-right') ?><span class="sr-only"> (opens the external calculator in a new tab)</span></a>
                </div>
            </article>
        </div>
    </div>
</section>
