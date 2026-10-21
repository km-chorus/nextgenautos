<?php

declare(strict_types=1);

/**
 * Public layout, second half (spec 09_spec_webpage_ui.md): closes <main>, then the compact
 * footer, then the document. Presentation only. The company phone numbers come from the admin Settings ($page[phones]); other company details (e-mail, address, social
 * links, opening hours) are NOT shown because none has been approved or stored yet: they are
 * added from real data in a later specification, never invented here.
 */

require_once dirname(__DIR__) . '/app/helpers/public_ui.php';

$footerImage = public_footer_image();
?>
</main>

<footer class="site-footer" data-site-background>
<?php if ($footerImage !== null): ?>
    <div class="site-footer__media">
        <picture>
            <source media="(max-width: 767.98px)" srcset="<?= e($footerImage['mobile']['url']) ?>">
            <img src="<?= e($footerImage['desktop']['url']) ?>" width="<?= (int) $footerImage['desktop']['width'] ?>" height="<?= (int) $footerImage['desktop']['height'] ?>" alt="" loading="lazy" decoding="async">
        </picture>
    </div>
<?php endif; ?>
    <div class="container site-footer__inner">
        <div class="site-footer__about">
            <p class="brand__name"><?= e(PUBLIC_SITE_NAME) ?></p>
            <p class="site-footer__text">Vehicle imports from Canada to Ghana, and car parts for popular makes.</p>
<?php if (!empty($page['phones'])): ?>
            <ul class="site-footer__phones" aria-label="Phone numbers">
<?php foreach ($page['phones'] as $footerPhone): ?>
                <li><a href="tel:<?= e($footerPhone['tel']) ?>"><?= public_icon('phone') ?><span><?= e($footerPhone['display']) ?></span></a></li>
<?php endforeach; ?>
            </ul>
<?php endif; ?>
        </div>
        <nav class="site-footer__nav" aria-label="Footer">
            <?php foreach (public_footer_groups() as $group): ?>
                <div class="footer-group">
                    <h2 class="footer-group__title"><?= e($group['title']) ?></h2>
                    <ul>
                        <?php foreach ($group['links'] as $link): ?>
                            <li><a href="<?= e($link['path']) ?>"><?= e($link['label']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </nav>
    </div>
    <div class="container site-footer__legal">
        <p>&copy; <?= e(gmdate('Y')) ?> <?= e(PUBLIC_SITE_NAME) ?></p>
        <a class="site-footer__cookies" href="/cookies/" data-cookie-settings>Cookie settings</a>
    </div>
</footer>
<?php require __DIR__ . '/cookie_banner.php'; ?>
<?php if (!in_array($page['nav'] ?? '', ['cookies'], true)) { require __DIR__ . '/assistant.php'; } ?>
</body>
</html>
