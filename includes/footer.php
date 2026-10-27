<?php

declare(strict_types=1);

/**
 * Public layout, second half (spec 09_spec_webpage_ui.md): closes <main>, then the compact
 * footer, then the document. Presentation only. The company phone numbers come from the admin Settings ($page[phones]); other company details (e-mail, address, social
 * links, opening hours) are NOT shown because none has been approved or stored yet: they are
 * added from real data in a later specification, never invented here.
 */

require_once dirname(__DIR__) . '/app/helpers/public_ui.php';

?>
</main>

<footer class="site-footer" data-site-background>
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
            <?php foreach (public_footer_groups(!empty($page['accident']), !empty($page['team'])) as $group): ?>
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
        <nav class="site-footer__legal-links" aria-label="Legal">
            <a class="site-footer__cookies" href="/privacy/">Privacy Policy</a>
            <a class="site-footer__cookies" href="/cookies/" data-cookie-settings>Cookie settings</a>
        </nav>
    </div>
</footer>
<?php require __DIR__ . '/cookie_banner.php'; ?>
<?php if (!in_array($page['nav'] ?? '', ['cookies'], true)) { require __DIR__ . '/assistant.php'; } ?>
<?php if (!empty($page['toast'])):
    /* 2026-10-22: one message at a time (welcome after sign-in, "too many attempts", confirmations), with a progress bar
       that shows when it closes (public.js; paused while hovered or focused). Errors stay longer and are announced at once. */
    $toast = is_array($page['toast']) ? $page['toast'] : ['type' => 'success', 'text' => (string) $page['toast']];
    $toastType = in_array($toast['type'] ?? '', ['success', 'error', 'info'], true) ? $toast['type'] : 'info';
    $toastMs = $toastType === 'error' ? 8000 : 6000; ?>
<div class="toast toast--<?= $toastType ?>" role="<?= $toastType === 'error' ? 'alert' : 'status' ?>" aria-live="<?= $toastType === 'error' ? 'assertive' : 'polite' ?>" data-toast data-toast-ms="<?= $toastMs ?>">
    <span class="toast__icon" aria-hidden="true"><?= public_icon($toastType === 'error' ? 'alert-circle' : ($toastType === 'info' ? 'info' : 'check-circle')) ?></span>
    <p class="toast__text"><?= e((string) ($toast['text'] ?? '')) ?></p>
    <button class="toast__close" type="button" data-toast-close aria-label="Close message"><?= public_icon('close') ?></button>
    <span class="toast__progress toast__progress--<?= $toastMs ?>" aria-hidden="true" data-toast-progress></span>
</div>
<?php endif; ?>
</body>
</html>
