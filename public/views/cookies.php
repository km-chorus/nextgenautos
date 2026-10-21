<?php

declare(strict_types=1);

/**
 * Cookie policy and settings (spec 18_spec_cookie_consent.md). Presentation only; the table lists exactly the cookies
 * the code sets for VISITORS (keep it in step with CustomerSessionService, PublicFormToken and CookieConsentService).
 *
 * @var array<string,mixed> $data consent (chosen, activity), saved (bool), error (?string)
 */
$consent = $data['consent'];
$choicesId = 'page';
$cookies = [
    ['vip_customer_session', 'Keeps you signed in to your customer account.', 'Until you close your browser or are inactive for a while'],
    ['vip_form', 'Protects our contact and reservation forms against forgery and spam.', 'Until you close your browser'],
    ['vip_cookie_consent', 'Remembers the cookie choice you make on this page.', CookieConsentService::LIFETIME_DAYS . ' days'],
];   // the staff-only admin session cookie is deliberately not listed: visitors never receive it
?>
<?= public_page_header('Cookie Settings', 'Your privacy', 'The cookies this website uses, and your choice about what your browser keeps.') ?>

<div class="container section cookie-page">
<?php if ($data['saved']): ?>
    <?= public_alert('success', 'Your cookie settings have been saved.') ?>
<?php elseif ($data['error'] !== null): ?>
    <?= public_alert('error', $data['error']) ?>
<?php elseif (!$consent['chosen']): ?>
    <?= public_alert('info', 'You have not made a choice yet. Until you do, only essential cookies are used and your browser keeps no copies of the pages you visit.') ?>
<?php endif; ?>

    <section class="cookie-page__section" aria-labelledby="choice-title" id="settings">
        <h2 id="choice-title">Your choice</h2>
        <form class="cookie-page__form" method="post" action="/cookies/">
            <input type="hidden" name="return" value="/cookies/">
            <input type="hidden" name="consent" value="custom">
<?php require dirname(__DIR__, 2) . '/includes/cookie_choices.php'; ?>
            <div class="actions">
                <button class="btn btn--primary" type="submit">Save settings</button>
            </div>
        </form>
    </section>

    <section class="cookie-page__section" aria-labelledby="list-title">
        <h2 id="list-title">Cookies we use</h2>
        <p>All of these are essential and set by this website only. We do not use advertising, analytics, tracking or third-party cookies, and nothing is stored in your browser's local storage.</p>
        <div class="cookie-table__wrap">
            <table class="cookie-table">
                <thead>
                    <tr><th scope="col">Name</th><th scope="col">Purpose</th><th scope="col">Kept for</th></tr>
                </thead>
                <tbody>
<?php foreach ($cookies as [$name, $purpose, $kept]): ?>
                    <tr><th scope="row"><code><?= e($name) ?></code></th><td data-label="Purpose"><?= e($purpose) ?></td><td data-label="Kept for"><?= e($kept) ?></td></tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="cookie-page__section" aria-labelledby="activity-title">
        <h2 id="activity-title">About browsing activity</h2>
        <p>Browsers normally keep copies of the pages you open so that pressing Back is instant. On a shared or public computer, those copies can show the next person what you looked at.</p>
        <p>When browsing activity is off, we ask your browser not to keep any copy of our pages, and to remove the copies it already kept. Sign-in and account pages are never kept, whatever you choose.</p>
        <p>You can change your choice at any time with the <strong>Cookie settings</strong> link at the bottom of every page.</p>
    </section>
</div>
