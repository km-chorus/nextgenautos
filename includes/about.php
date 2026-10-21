<?php

declare(strict_types=1);

/**
 * Homepage "About Amponsah Autos" + "Our Team" (spec 11C, editorial grid): ONE composition drawn with thin structural rules.
 * Top band: the company introduction (large heading, two short paragraphs) beside a restrained picture. Below: four vertical
 * columns hold the people (compact profile modules at staggered heights, small avatars, never portraits); under them sit the
 * "Our team" heading and one company statement. The team comes from Team Management through PublicTeamService. No JavaScript.
 *
 * Only approved content: what the company does (vehicle imports from Canada to Ghana, and car parts for popular makes: owner
 * statement of 2026-10-14) and between which countries; no history, figures, awards or superlatives.
 * There is no About page yet (the route is a placeholder), so no "Learn more" link; the team has no profile pages, so the
 * modules are not links and carry no arrow.
 *
 * Expects: $team = list of members from PublicTeamService, or null when they could not be read.
 */
$team = $team ?? null;
$members = is_array($team) ? $team : [];
?>
<section class="about" aria-labelledby="about-title">
    <div class="container">
        <div class="eg">
            <div class="eg__band">
                <div class="eg__intro">
                    <p class="eg__eyebrow">About Amponsah Autos</p>
                    <h2 class="eg__title" id="about-title">Quality vehicles from Canada, and the parts to keep them running.</h2>
                    <div class="eg__copy">
                        <p>Amponsah Autos operates between Canada and Ghana, helping customers source quality vehicles and navigate the vehicle import journey.</p>
                        <p>We also deal in car parts for popular makes such as Toyota, Honda and Hyundai.</p>
                        <p>From choosing a vehicle to shipping and tracking it, we keep each step clear so customers know what happens next.</p>
                    </div>
                    <ul class="eg__points" aria-label="What we do">
                        <li>Quality vehicles</li>
                        <li>Car parts</li>
                        <li>Canada sourcing</li>
                        <li>Ghana delivery</li>
                    </ul>
                </div>
                <figure class="eg__visual">
                    <img src="<?= public_asset('images/about/about.webp') ?>" width="800" height="1000" alt="Two vehicles loaded on a car carrier truck" loading="lazy" decoding="async">
                </figure>
            </div>

<?php if ($members !== []): ?>
            <section class="eg__team" aria-labelledby="team-title">
                <header class="eg__team-head">
                    <p class="eg__eyebrow">Our team</p>
                    <h3 class="eg__team-title" id="team-title">The people behind Amponsah Autos.</h3>
                    <p class="eg__team-text">Meet the people responsible for helping our customers source, manage and move their vehicles from Canada to Ghana, and find the parts they need.</p>
                </header>

                <ul class="eg__members">
<?php foreach ($members as $i => $member): ?>
                    <li class="member member--<?= $i + 1 ?>">
                        <div class="member__top">
<?php if ($member['photo'] !== null): ?>
                            <img class="member__avatar" src="<?= e($member['photo']['url']) ?>" width="<?= (int) $member['photo']['width'] ?>" height="<?= (int) $member['photo']['height'] ?>" alt="<?= e($member['alt']) ?>" loading="lazy" decoding="async">
<?php else: ?>
                            <span class="member__avatar member__avatar--empty" role="img" aria-label="<?= e($member['alt']) ?>"></span>
<?php endif; ?>
                            <div class="member__id">
                                <p class="member__name"><?= e($member['name']) ?></p>
<?php if ($member['position'] !== ''): ?>
                                <p class="member__position"><?= e($member['position']) ?></p>
<?php endif; ?>
                            </div>
                        </div>
<?php if ($member['category'] !== null): ?>
                        <p class="member__category"><?= e($member['category']) ?></p>
<?php endif; ?>
<?php if ($member['bio'] !== null): ?>
                        <p class="member__bio"><?= e($member['bio']) ?></p>
<?php endif; ?>
                    </li>
<?php endforeach; ?>
                </ul>

                <p class="eg__statement">Quality vehicles and parts. A clear process. A smoother journey from Canada to Ghana.</p>
            </section>
<?php endif; ?>
        </div>
    </div>
</section>
