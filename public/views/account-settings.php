<?php

declare(strict_types=1);

/**
 * Account settings (owner request 2026-10-22): the signed-in customer changes their name and password. Presentation only;
 * CustomerAuthController::settings and CustomerAccountService do the work, every value is escaped here.
 *
 * Expects $data: customer (id, name, email, verified), values (name), errors (field => message), notice (?array type,
 * text from a fixed list), message (?string), hasPassword (bool: false = Google-only account), csrf, unavailable (bool).
 * The email address is shown but not editable: shipments are linked to the confirmed email (contact the team).
 */
$c = $data['customer'] ?? null;
$csrf = (string) ($data['csrf'] ?? '');
$errors = $data['errors'] ?? [];
$hasPassword = !empty($data['hasPassword']);
$fieldError = static function (string $name) use ($errors): string {
    return isset($errors[$name]) ? '<p class="auth-field__error" id="' . e($name) . '-error">' . e($errors[$name]) . '</p>' : '';
};
$invalid = static fn (string $name): string => isset($errors[$name]) ? ' aria-invalid="true" aria-describedby="' . e($name) . '-error"' : '';
?>
<?= public_page_header('Account Settings') ?>

<div class="container section dash settings">
<?php
if (!empty($data['notice'])) { public_toast($page, (string) $data['notice']['type'], (string) $data['notice']['text']); }
if (!empty($data['message'])) { public_toast($page, 'error', (string) $data['message']); }
?>

<?php if ($c !== null): ?>
    <p class="settings__links"><a href="/account/"><?= public_icon('arrow-left') ?><span>My dashboard (favourites, reservations)</span></a> <a href="/track/"><?= public_icon('route') ?><span>Track my vehicle</span></a></p>

    <div class="dash-grid">
        <section class="dash-panel" aria-labelledby="settings-name-title">
            <header class="dash-panel__head"><h2 class="dash-panel__title" id="settings-name-title"><?= public_icon('user') ?><span>Your name</span></h2></header>
            <form class="auth-form" method="post" action="/account/settings/" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="name">
                <div class="auth-field<?= isset($errors['name']) ? ' auth-field--error' : '' ?>">
                    <label for="name">Full name</label>
                    <input type="text" id="name" name="name" value="<?= e((string) ($data['values']['name'] ?? '')) ?>" autocomplete="name" maxlength="<?= CustomerValidator::MAX_NAME ?>" required<?= $invalid('name') ?>>
                    <?= $fieldError('name') ?>
                </div>
                <button type="submit" class="auth-submit settings__submit">Save name</button>
            </form>
        </section>

        <section class="dash-panel" aria-labelledby="settings-email-title">
            <header class="dash-panel__head"><h2 class="dash-panel__title" id="settings-email-title"><?= public_icon('mail') ?><span>Email address</span></h2></header>
            <p class="settings__email"><strong><?= e((string) $c['email']) ?></strong> <span class="dash-badge <?= $c['verified'] ? 'dash-badge--ok' : 'dash-badge--light' ?>"><?= $c['verified'] ? 'Confirmed' : 'Not confirmed' ?></span></p>
            <p class="dash-note">Your shipments are linked to this email address, so it cannot be changed here. To change it, please <a href="/contact/">contact us</a>.</p>
        </section>

        <section class="dash-panel dash-panel--wide" aria-labelledby="settings-password-title">
            <header class="dash-panel__head"><h2 class="dash-panel__title" id="settings-password-title"><?= public_icon('lock') ?><span><?= $hasPassword ? 'Change password' : 'Set a password' ?></span></h2></header>
<?php if (!$hasPassword): ?>
            <p class="dash-note settings__intro">You sign in with Google. You can also set a password to sign in with your email address.</p>
<?php endif; ?>
            <form class="auth-form settings__password" method="post" action="/account/settings/" novalidate data-auth-form>
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="password">
                <input type="email" name="username" value="<?= e((string) $c['email']) ?>" autocomplete="username" hidden>
<?php if ($hasPassword): ?>
                <div class="auth-field<?= isset($errors['current_password']) ? ' auth-field--error' : '' ?>">
                    <label for="current_password">Current password</label>
                    <input type="password" id="current_password" name="current_password" autocomplete="current-password" maxlength="1024" required<?= $invalid('current_password') ?>>
                    <?= $fieldError('current_password') ?>
                </div>
<?php endif; ?>
                <div class="auth-field<?= isset($errors['new_password']) ? ' auth-field--error' : '' ?>">
                    <label for="new_password">New password</label>
                    <input type="password" id="new_password" name="new_password" autocomplete="new-password" maxlength="1024" required<?= $invalid('new_password') ?>>
                    <?= $fieldError('new_password') ?>
                </div>
                <div class="auth-field<?= isset($errors['new_password_confirm']) ? ' auth-field--error' : '' ?>">
                    <label for="new_password_confirm">Type the new password again</label>
                    <input type="password" id="new_password_confirm" name="new_password_confirm" autocomplete="new-password" maxlength="1024" required<?= $invalid('new_password_confirm') ?>>
                    <?= $fieldError('new_password_confirm') ?>
                </div>
                <button type="submit" class="auth-submit settings__submit"><?= $hasPassword ? 'Change password' : 'Set password' ?></button>
            </form>
        </section>
    </div>

    <form class="settings__signout" method="post" action="/account/">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <button type="submit" class="btn btn--outline"><?= public_icon('logout') ?><span>Sign out</span></button>
    </form>
<?php endif; ?>
</div>
