<?php

declare(strict_types=1);

/**
 * Homepage "Plan Your Vehicle Import" (spec 11B; redesigned 2026-10-23 at the owner's request: no images, solid white cards
 * with a subtle gradient and icons). Two cards: Track Your Shipment (the site's own Track Vehicle page) and Calculate
 * Import Duty (the approved external calculator, new tab). Each lists three short facts that are true of the site:
 * nothing is invented, no duty figures, no live tracking on this website. No JavaScript. Presentation only.
 *
 * Expects: $router (PublicRouter) and $dutyUrl (a validated https address, from ExternalLinksService).
 */

$trackRoute = $router->byName('track');
$trackPath = $trackRoute['path'] ?? '/track/';
$cards = [
    [
        'mod' => 'track', 'icon' => 'route', 'step' => '01', 'title' => 'Track Your Shipment',
        'text' => 'Follow your vehicle from Canada to Ghana with the container number we send you.',
        'points' => [['mail', 'Status updates by email at every step'], ['search', 'Look up your container number on our Track page'], ['route', 'Live position on Hapag-Lloyd\'s tracking']],
        'link' => ['href' => $trackPath, 'label' => 'Track Shipment', 'external' => false],
    ],
    [
        'mod' => 'duty', 'icon' => 'calculator', 'step' => '02', 'title' => 'Calculate Import Duty',
        'text' => 'Estimate the Ghana import duty for your vehicle with the external duty calculator.',
        'points' => [['car', 'Have the make, model and year of the vehicle ready'], ['document', 'The calculator gives an estimate; customs decide the final amount'], ['info', 'This website does not calculate duty itself']],
        'link' => ['href' => $dutyUrl, 'label' => 'Calculate Duty', 'external' => true],
    ],
];
?>
<section class="utilities" aria-labelledby="utilities-title">
    <div class="container">
        <header class="utilities__head">
            <p class="utilities__eyebrow">Plan your vehicle import</p>
            <h2 class="utilities__title" id="utilities-title">Everything you need to stay informed about your vehicle.</h2>
            <p class="utilities__intro">Track your shipment and estimate your import duty using the tools provided for your vehicle journey.</p>
        </header>

        <div class="utilities__grid">
<?php foreach ($cards as $c): ?>
            <article class="utility utility--<?= $c['mod'] ?>">
                <span class="utility__watermark" aria-hidden="true"><?= public_icon($c['icon']) ?></span>
                <div class="utility__top">
                    <span class="utility__icon" aria-hidden="true"><?= public_icon($c['icon']) ?></span>
                    <span class="utility__step" aria-hidden="true"><?= $c['step'] ?></span>
                </div>
                <h3 class="utility__title"><?= e($c['title']) ?></h3>
                <p class="utility__text"><?= e($c['text']) ?></p>
                <ul class="utility__points">
<?php foreach ($c['points'] as [$icon, $point]): ?>
                    <li><span class="utility__point-icon" aria-hidden="true"><?= public_icon($icon) ?></span><span><?= e($point) ?></span></li>
<?php endforeach; ?>
                </ul>
                <a class="utility__link" href="<?= e($c['link']['href']) ?>"<?= $c['link']['external'] ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= e($c['link']['label']) ?><?= public_icon('arrow-right') ?><?= $c['link']['external'] ? '<span class="sr-only"> (opens the external calculator in a new tab)</span>' : '' ?></a>
            </article>
<?php endforeach; ?>
        </div>
    </div>
</section>
