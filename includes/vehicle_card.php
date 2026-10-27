<?php

declare(strict_types=1);

/**
 * One vehicle card (spec 10B/11, shared by the Vehicles page). Presentation only.
 * Expects $v: a card from PublicVehicleService (all values are escaped here). The whole card opens the vehicle:
 * the title link is stretched over it by CSS, so there is a single link per card for keyboard and screen-reader users.
 * Extra photographs are not downloaded until the visitor asks for them (vehicle-gallery.js); without JavaScript the card
 * simply shows its first photograph.
 * The "Calculate Ghana import duty" button (owner request 2026-10-14) is a second, separate link that sits above the
 * card-wide link and opens the approved external calculator ($page['dutyUrl'], set for every page by PublicController):
 * the website itself never calculates duty.
 */
$hasGallery = $v['image'] !== null && $v['gallery'] !== [];
?>
            <li class="vehicle-card">
                <div class="vehicle-card__figure"<?= $hasGallery ? ' data-gallery' : '' ?>>
                    <a class="vehicle-card__media" href="<?= e($v['url']) ?>" tabindex="-1" aria-hidden="true">
<?php if ($v['image'] !== null): ?>
                        <img src="<?= e($v['image']['url']) ?>" width="<?= (int) $v['image']['width'] ?>" height="<?= (int) $v['image']['height'] ?>" alt=""<?= ($i ?? 99) < 3 ? ' fetchpriority="high"' : ' loading="lazy"' ?> decoding="async">
<?php foreach ($v['gallery'] as $photo): ?>
                        <img data-src="<?= e($photo['url']) ?>" width="<?= (int) $photo['width'] ?>" height="<?= (int) $photo['height'] ?>" alt="" decoding="async" hidden>
<?php endforeach; ?>
<?php else: ?>
                        <span class="vehicle-card__placeholder"><?= public_icon('car') ?><span>Photo coming soon</span></span>
<?php endif; ?>
                    </a>
<?php if ($hasGallery): ?>
                    <button class="vehicle-card__nav vehicle-card__nav--prev" type="button" data-gallery-prev aria-label="Previous photo of <?= e($v['title']) ?>" hidden><?= public_icon('chevron-left') ?></button>
                    <button class="vehicle-card__nav vehicle-card__nav--next" type="button" data-gallery-next aria-label="Next photo of <?= e($v['title']) ?>" hidden><?= public_icon('chevron-right') ?></button>
                    <p class="vehicle-card__count" data-gallery-count aria-hidden="true" hidden>1 / <?= count($v['gallery']) + 1 ?></p>
<?php endif; ?>
<?php $favSlug = (string) $v['slug']; $favTitle = (string) $v['title']; $favVariant = 'card'; require __DIR__ . '/favourite_button.php'; ?>
<?php if (!empty($v['accident'])): ?>
                    <p class="vehicle-card__damage">Accident<span class="vehicle-card__damage-more">-damaged</span></p>
<?php endif; ?>
                    <p class="vehicle-card__status<?= $v['reserved'] ? ' vehicle-card__status--reserved' : '' ?>"><span class="sr-only">Availability: </span><?= e($v['status']) ?></p>
                </div>
                <div class="vehicle-card__body">
                    <p class="vehicle-card__meta"><?= e($v['year']) ?> · <?= e($v['brand']) ?></p>
                    <div class="vehicle-card__head">
                        <h3 class="vehicle-card__title"><a class="vehicle-card__open" href="<?= e($v['url']) ?>"><?= e($v['name']) ?><span class="sr-only">, <?= e($v['title']) ?><?= !empty($v['accident']) ? ', accident-damaged' : '' ?>: view full details</span></a></h3>
                        <p class="vehicle-card__price"><?= e($v['price']) ?><?php if ($v['duty']): ?><span class="vehicle-card__duty">Duty included</span><?php endif; ?></p>
                    </div>
<?php if ($v['specs'] !== []): ?>
                    <dl class="vehicle-card__specs">
<?php foreach ($v['specs'] as $spec): ?>
                        <div class="vehicle-card__spec">
                            <dt><?= public_icon(['mileage' => 'gauge', 'engine' => 'engine', 'transmission' => 'gearbox', 'drive' => 'steering', 'fuel' => 'fuel'][$spec['key']]) ?><span><?= e($spec['label']) ?></span></dt>
                            <dd><?= e($spec['value']) ?></dd>
                        </div>
<?php endforeach; ?>
                    </dl>
<?php endif; ?>
<?php if (($page['dutyUrl'] ?? '') !== ''): ?>
                    <a class="vehicle-card__duty-link" href="<?= e($page['dutyUrl']) ?>" target="_blank" rel="noopener noreferrer"><?= public_icon('calculator') ?><span>Calculate Ghana import duty</span><span class="sr-only"> for the <?= e($v['title']) ?> (opens the external duty calculator in a new tab)</span></a>
<?php endif; ?>
                </div>
            </li>
