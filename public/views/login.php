<?php

declare(strict_types=1);

/**
 * Customer sign-in (spec 13_spec_customer_auth.md). Presentation only: PublicController supplies $data from
 * CustomerAuthController (values, errors, message, notice, csrf, google, unavailable). This is NOT the administrator sign-in.
 *
 * Two ways in: "Sign in with Google" (shown only when Google is configured) and email + password.
 */
$values = $data['values'] ?? [];
$errors = $data['errors'] ?? [];
$authTitle = 'Sign in';
$authTrust = 'Your password is stored securely and never in plain text. If you use Google, we only receive your name and email address.';
require dirname(__DIR__, 2) . '/includes/auth_start.php';
?>
<?php
if (!empty($data['notice'])) { public_toast($page, (string) $data['notice']['type'], (string) $data['notice']['text']); }
if (!empty($data['message'])) { public_toast($page, 'error', (string) $data['message']); }
?>

        <?php if (empty($data['unavailable'])): ?>
            <?php if (!empty($data['google'])): ?>
                <?= public_google_button('Sign in with Google') ?>
                <p class="auth-divider"><span>OR</span></p>
            <?php endif; ?>

            <form class="auth-form" method="post" action="/login/" novalidate data-auth-form>
                <input type="hidden" name="csrf_token" value="<?= e((string) $data['csrf']) ?>">

                <div class="auth-field<?= isset($errors['email']) ? ' auth-field--error' : '' ?>">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" value="<?= e((string) ($values['email'] ?? '')) ?>" placeholder="you@example.com"
                           autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" maxlength="255" required<?= isset($errors['email']) ? ' aria-invalid="true" aria-describedby="email-error"' : '' ?>>
                    <?php if (isset($errors['email'])): ?><p class="auth-field__error" id="email-error"><?= e($errors['email']) ?></p><?php endif; ?>
                </div>

                <div class="auth-field<?= isset($errors['password']) ? ' auth-field--error' : '' ?>">
                    <label for="password">Password</label>
                    <div class="auth-password">
                        <input type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" maxlength="1024" required<?= isset($errors['password']) ? ' aria-invalid="true" aria-describedby="password-error"' : '' ?>>
                        <button type="button" class="auth-password__toggle" data-password-toggle aria-controls="password" aria-pressed="false" hidden>Show</button>
                    </div>
                    <?php if (isset($errors['password'])): ?><p class="auth-field__error" id="password-error"><?= e($errors['password']) ?></p><?php endif; ?>
                </div>

                <p class="auth-forgot"><a href="/forgot-password/">Forgot your password?</a></p>

                <button type="submit" class="auth-submit" data-auth-submit data-busy-label="Signing in">Sign in</button>
            </form>

            <p class="auth-switch">Don't have an account? <a href="/register/">Create one</a></p>
        <?php endif; ?>
<?php require dirname(__DIR__, 2) . '/includes/auth_end.php'; ?>
