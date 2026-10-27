<?php

declare(strict_types=1);

/**
 * About Us (owner request 2026-10-26): the business, how buying works, and the team. Presentation only.
 *
 * Team cards (owner 2026-10-26): the photo only, with department, name and role on a gradient overlay at the bottom; the
 * photo zooms in on hover. Members without a photo are not shown, and no members with photos = no team section.
 * Only approved, true content: vehicle imports from Canada to Ghana and car parts for popular makes (owner statement of
 * 2026-10-14), shipping with Hapag-Lloyd (owner, 2026-10-22), the office address from Admin > Settings, the buying steps
 * from app/config/how_it_works.php, and team members exactly as entered in Admin > Team (active ones, admin's order).
 * No history, figures, awards or superlatives. Parts that are empty are left out (no address card, no team section).
 *
 * @var array<string,mixed> $data steps (list of {title,text}), team (PublicTeamService::forAboutPage), address (?string)
 */
$team = $data['team'] ?? [];
$address = $data['address'] ?? null;
$facts = array_values(array_filter([
    ['car', 'Vehicles from Canada', 'Quality vehicles sourced in Canada and shipped to Ghana.'],
    ['engine', 'Car parts', 'Parts for popular makes such as Toyota, Honda and Hyundai.'],
    ['route', 'Shipping with Hapag-Lloyd', 'We send you the container number so you can follow your vehicle to Ghana.'],
    $address !== null ? ['map-pin', 'Our office', $address] : null,
]));
?>
<?= public_page_header('About Us') ?>

<section class="ab-intro" aria-labelledby="ab-intro-title">
    <div class="container ab-intro__grid">
        <div class="ab-intro__text">
            <p class="ab-eyebrow">Who we are</p>
            <h2 class="ab-title" id="ab-intro-title">Quality vehicles from Canada, and the parts to keep them running.</h2>
            <p class="ab-lead"><?= e(PUBLIC_SITE_NAME) ?> operates between Canada and Ghana. We help customers source quality vehicles in Canada and bring them to Ghana, and we deal in car parts for popular makes.</p>
            <p class="ab-text">From choosing a vehicle to shipping and tracking it, we keep every step clear, so you always know what happens next.</p>
        </div>
        <ul class="ab-facts">
<?php foreach ($facts as [$icon, $title, $text]): ?>
            <li class="ab-fact">
                <span class="ab-fact__icon" aria-hidden="true"><?= public_icon($icon) ?></span>
                <h3 class="ab-fact__title"><?= e($title) ?></h3>
                <p class="ab-fact__text"><?= nl2br(e($text)) ?></p>
            </li>
<?php endforeach; ?>
        </ul>
    </div>
</section>

<?php if (!empty($data['steps'])): ?>
<section class="ab-steps" aria-labelledby="ab-steps-title">
    <div class="container">
        <header class="ab-head">
            <p class="ab-eyebrow">How we work</p>
            <h2 class="ab-title" id="ab-steps-title">From Canada to your driveway in Ghana.</h2>
        </header>
        <ol class="ab-timeline">
<?php foreach ($data['steps'] as $i => $step): ?>
            <li class="ab-step">
                <span class="ab-step__num" aria-hidden="true"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                <h3 class="ab-step__title"><?= e((string) $step['title']) ?></h3>
                <p class="ab-step__text"><?= e((string) $step['text']) ?></p>
            </li>
<?php endforeach; ?>
        </ol>
    </div>
</section>
<?php endif; ?>

<?php if ($team !== []): ?>
<section class="ab-team" id="team" aria-labelledby="ab-team-title">
    <div class="container">
        <header class="ab-head">
            <p class="ab-eyebrow">Our team</p>
            <h2 class="ab-title" id="ab-team-title">Meet the team</h2>
            <p class="ab-head__text">The people behind <?= e(PUBLIC_SITE_NAME) ?>.</p>
        </header>
        <ul class="ab-team__grid">
<?php foreach ($team as $m): ?>
            <li class="ab-member">
                <img class="ab-member__img" src="<?= e($m['large'] ?? $m['photo']['url']) ?>" srcset="<?= e($m['photo']['url']) ?> 400w<?= $m['large'] !== null ? ', ' . e($m['large']) . ' 1200w' : '' ?>" sizes="(min-width: 64em) 300px, (min-width: 40em) 45vw, 48vw" width="<?= (int) $m['photo']['width'] ?>" height="<?= (int) $m['photo']['height'] ?>" alt="<?= e($m['alt']) ?>" loading="lazy" decoding="async">
                <div class="ab-member__overlay">
<?php if ($m['category'] !== null): ?>
                    <p class="ab-member__dept"><?= e($m['category']) ?></p>
<?php endif; ?>
                    <h3 class="ab-member__name"><?= e($m['name']) ?></h3>
<?php if ($m['position'] !== ''): ?>
                    <p class="ab-member__role"><?= e($m['position']) ?></p>
<?php endif; ?>
                </div>
            </li>
<?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<section class="ab-cta" aria-labelledby="ab-cta-title">
    <div class="container">
        <div class="ab-cta__box">
            <div>
                <h2 class="ab-cta__title" id="ab-cta-title">Ready to find your next vehicle?</h2>
                <p class="ab-cta__text">Browse the vehicles on offer, or talk to our team about the one you are looking for.</p>
            </div>
            <div class="ab-cta__actions">
                <a class="btn btn--primary ab-cta__btn" href="/vehicles/">Browse Vehicles<?= public_icon('arrow-right') ?></a>
                <a class="btn ab-cta__btn ab-cta__btn--ghost" href="/contact/">Contact us</a>
            </div>
        </div>
    </div>
</section>
