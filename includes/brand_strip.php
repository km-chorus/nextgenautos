<?php

declare(strict_types=1);

/**
 * Browse by Brand (spec 10C): a horizontal, natively scrollable strip of the active brands, each linking to the
 * Vehicles page filtered by that brand. Presentation only. Without JavaScript it still scrolls (swipe, trackpad,
 * keyboard through the links); assets/js/brand-strip.js only adds the arrow buttons.
 *
 * Expects: $brands = list<array{name,slug,url,logo}>|null (from PublicBrandService; null = could not be read).
 */
?>
<section class="brands" aria-labelledby="brands-title">
    <div class="container">
        <header class="brands__head">
            <h2 class="brands__title" id="brands-title">Browse by Brand</h2>
            <p class="brands__intro">Explore vehicles from the brands we source.</p>
        </header>

<?php if ($brands === null || $brands === []): ?>
        <p class="brands__empty">Brand selection is currently unavailable.</p>
<?php else: ?>
        <div class="brand-strip" data-brand-strip>
            <button class="brand-strip__arrow brand-strip__arrow--prev" type="button" data-brand-prev aria-label="Scroll brands left" hidden><?= public_icon('chevron-left') ?></button>
            <div class="brand-strip__viewport" data-brand-viewport>
                <ul class="brand-strip__track">
<?php foreach ($brands as $brand): ?>
                    <li class="brand-strip__item">
                        <a class="brand-item" href="<?= e($brand['url']) ?>">
<?php if ($brand['logo'] !== null): ?>
                            <span class="brand-item__logo"><img src="<?= e($brand['logo']) ?>" alt="<?= e($brand['name']) ?>" width="104" height="56" decoding="async"></span>
                            <span class="brand-item__name" aria-hidden="true"><?= e($brand['name']) ?></span>
<?php else: ?>
                            <span class="brand-item__logo brand-item__logo--missing"><span class="brand-item__text"><?= e($brand['name']) ?></span></span>
<?php endif; ?>
                        </a>
                    </li>
<?php endforeach; ?>
                </ul>
            </div>
            <button class="brand-strip__arrow brand-strip__arrow--next" type="button" data-brand-next aria-label="Scroll brands right" hidden><?= public_icon('chevron-right') ?></button>
        </div>
<?php endif; ?>
    </div>
</section>
