<?php

declare(strict_types=1);

/**
 * Track Vehicle (spec 13_spec_customer_auth.md; business rules 06 sections 7 and 29 to 31). Shown only to a signed-in
 * customer: PublicController checks the session before this view is reached.
 *
 * The website does NOT track anything itself. It explains the process, says plainly that a container number is
 * provided by the company once the shipping company assigns one, and links to the official tracking provider.
 * No container number, location or status is generated, guessed or shown here.
 *
 * Expects $data: customer (name), trackingUrl (a validated https address from ExternalLinksService).
 */
$steps = [
    ['title' => 'Your vehicle is purchased', 'text' => 'After purchase, Mr Amponsah Autos arranges the shipment of your vehicle to Ghana.'],
    ['title' => 'The shipping company assigns a container', 'text' => 'The shipping company, not this website, assigns the container and provides its number to the company.'],
    ['title' => 'We send you the container number', 'text' => 'Once the company has the real container number, it is communicated to you directly.'],
    ['title' => 'Track with the shipping provider', 'text' => 'Enter your container number on the official tracking page. The shipping provider is the source of truth for where your container is.'],
];
?>
<?= public_page_header('Track Vehicle', 'Shipment tracking', 'Follow your vehicle\'s journey from Canada to Ghana using the official shipping provider.') ?>

<div class="container section track">
    <p class="track__signed-in">Signed in as <strong><?= e((string) ($data['customer']['name'] ?? '')) ?></strong>.</p>

    <div class="track__grid">
        <section class="track-panel" aria-labelledby="track-status">
            <h2 class="track-panel__title" id="track-status">Tracking information</h2>
            <p class="track-panel__status"><?= public_icon('info') ?><span>Tracking information is not yet available until your container number has been provided.</span></p>
            <p>Your container number will be provided by Mr Amponsah Autos once your vehicle has been assigned to a shipment. You do not need one before then.</p>

            <h3 class="track-panel__subtitle">Already have your container number?</h3>
            <p>Open the official tracking page and enter the number the company gave you.</p>
            <p class="track-panel__action">
                <a class="btn btn--primary" href="<?= e((string) $data['trackingUrl']) ?>" target="_blank" rel="noopener noreferrer">Open official tracking<?= public_icon('arrow-right') ?><span class="sr-only"> (opens the shipping provider's website in a new tab)</span></a>
            </p>
            <p class="track-panel__note">Live shipment information is shown by the shipping provider, not by this website.</p>
        </section>

        <section class="track-how" aria-labelledby="track-how-title">
            <h2 class="track-how__title" id="track-how-title">How tracking works</h2>
            <ol class="process__steps" role="list">
<?php foreach ($steps as $i => $step): ?>
                <li class="process__step">
                    <span class="process__number" aria-hidden="true"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                    <h3 class="process__step-title"><?= e($step['title']) ?></h3>
                    <p class="process__step-text"><?= e($step['text']) ?></p>
                </li>
<?php endforeach; ?>
            </ol>
        </section>
    </div>

    <p class="track__help">Questions about your shipment? <a href="/contact/">Contact us</a>.</p>
</div>
