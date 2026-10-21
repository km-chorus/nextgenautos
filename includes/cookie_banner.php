<?php

declare(strict_types=1);

/**
 * Cookie consent banner and settings dialog (spec 18_spec_cookie_consent.md). Presentation only.
 *
 * The banner is shown until the visitor has chosen (never on /cookies/, which has the full form). Every form posts to
 * /cookies/, so it all works without JavaScript; public.js only opens the settings dialog in place of the /cookies/ page.
 * The dialog is always present (closed) so the footer's "Cookie settings" can reopen the choice on any page.
 *
 * Expected variables: array $page (consent{chosen,activity}, return, nav).
 */

$consent = $page['consent'] ?? ['chosen' => false, 'activity' => false];
$returnTo = (string) ($page['return'] ?? '/');
$onCookiesPage = ($page['nav'] ?? '') === 'cookies';
?>
<?php if (!$consent['chosen'] && !$onCookiesPage): ?>
<section class="cookie-banner" aria-labelledby="cookie-banner-title" data-cookie-banner>
    <div class="container cookie-banner__inner">
        <div class="cookie-banner__text">
            <h2 class="cookie-banner__title" id="cookie-banner-title">Cookies on this website</h2>
            <p>We use essential cookies to keep the site secure and keep you signed in. With your permission, your browser can also keep copies of the pages you visit. No advertising, analytics or third-party cookies.</p>
        </div>
        <form class="cookie-banner__actions" method="post" action="/cookies/">
            <input type="hidden" name="return" value="<?= e($returnTo) ?>">
            <button class="btn btn--primary" type="submit" name="consent" value="all">Accept all</button>
            <button class="btn btn--outline" type="submit" name="consent" value="essential">Essential only</button>
            <a class="cookie-banner__link" href="/cookies/" data-cookie-settings>Cookie settings</a>
        </form>
    </div>
</section>
<?php endif; ?>
<?php if (!$onCookiesPage): ?>
<dialog class="cookie-dialog" aria-labelledby="cookie-dialog-title" data-cookie-dialog>
    <form class="cookie-dialog__form" method="post" action="/cookies/">
        <input type="hidden" name="return" value="<?= e($returnTo) ?>">
        <input type="hidden" name="consent" value="custom">
        <div class="cookie-dialog__head">
            <h2 class="cookie-dialog__title" id="cookie-dialog-title">Cookie settings</h2>
            <button class="cookie-dialog__close" type="button" data-cookie-close aria-label="Close cookie settings"><?= public_icon('close') ?></button>
        </div>
<?php require __DIR__ . '/cookie_choices.php'; ?>
        <div class="cookie-dialog__actions">
            <button class="btn btn--primary" type="submit">Save settings</button>
            <a class="cookie-banner__link" href="/cookies/">Full cookie policy</a>
        </div>
    </form>
</dialog>
<?php endif; ?>
