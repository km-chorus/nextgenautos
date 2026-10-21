<?php

declare(strict_types=1);

/**
 * Customer registration (spec 13_spec_customer_auth.md). Presentation only: PublicController supplies $data from
 * CustomerAuthController. The server validates everything again; the attributes here are a convenience.
 */
$values = $data['values'] ?? [];
$errors = $data['errors'] ?? [];
$authTitle = 'Create an account';
$authLead = 'Create your Mr Amponsah Autos account.';
$authTrust = 'Your password is stored securely and never in plain text. If you use Google, we only receive your name and email address.';
require dirname(__DIR__, 2) . '/includes/auth_start.php';
?>
        <?php if (!empty($data['message'])): ?>
            <?= public_alert('error', (string) $data['message']) ?>
        <?php endif; ?>

        <?php if (empty($data['unavailable'])): ?>
            <?php if (!empty($data['google'])): ?>
                <?= public_google_button('Sign up with Google') ?>
                <p class="auth-divider"><span>OR</span></p>
            <?php endif; ?>

            <form class="auth-form" method="post" action="/register/" novalidate data-auth-form>
                <input type="hidden" name="csrf_token" value="<?= e((string) $data['csrf']) ?>">

                <div class="auth-field<?= isset($errors['name']) ? ' auth-field--error' : '' ?>">
                    <label for="name">Full name</label>
                    <input type="text" id="name" name="name" value="<?= e((string) ($values['name'] ?? '')) ?>" placeholder="Your full name"
                           autocomplete="name" maxlength="100" required<?= isset($errors['name']) ? ' aria-invalid="true" aria-describedby="name-error"' : '' ?>>
                    <?php if (isset($errors['name'])): ?><p class="auth-field__error" id="name-error"><?= e($errors['name']) ?></p><?php endif; ?>
                </div>

                <div class="auth-field<?= isset($errors['email']) ? ' auth-field--error' : '' ?>">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" value="<?= e((string) ($values['email'] ?? '')) ?>" placeholder="you@example.com"
                           autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" maxlength="255" required<?= isset($errors['email']) ? ' aria-invalid="true" aria-describedby="email-error"' : '' ?>>
                    <?php if (isset($errors['email'])): ?><p class="auth-field__error" id="email-error"><?= e($errors['email']) ?></p><?php endif; ?>
                </div>

                <div class="auth-field<?= isset($errors['password']) ? ' auth-field--error' : '' ?>">
                    <label for="password">Password</label>
                    <div class="auth-password">
                        <input type="password" id="password" name="password" placeholder="Create a password" autocomplete="new-password" minlength="12" maxlength="72" required
                               aria-describedby="password-hint<?= isset($errors['password']) ? ' password-error' : '' ?>"<?= isset($errors['password']) ? ' aria-invalid="true"' : '' ?>>
                        <button type="button" class="auth-password__toggle" data-password-toggle aria-controls="password" aria-pressed="false" hidden>Show</button>
                    </div>
                    <p class="auth-field__hint" id="password-hint" data-password-hint>Use at least 12 characters. A short sentence works well.</p>
                    <?php if (isset($errors['password'])): ?><p class="auth-field__error" id="password-error"><?= e($errors['password']) ?></p><?php endif; ?>
                </div>

                <div class="auth-field<?= isset($errors['password_confirm']) ? ' auth-field--error' : '' ?>">
                    <label for="password_confirm">Confirm password</label>
                    <input type="password" id="password_confirm" name="password_confirm" placeholder="Repeat your password" autocomplete="new-password" maxlength="72" required<?= isset($errors['password_confirm']) ? ' aria-invalid="true" aria-describedby="password-confirm-error"' : '' ?>>
                    <?php if (isset($errors['password_confirm'])): ?><p class="auth-field__error" id="password-confirm-error"><?= e($errors['password_confirm']) ?></p><?php endif; ?>
                </div>

                <button type="submit" class="auth-submit" data-auth-submit data-busy-label="Creating account">Create account</button>
            </form>

            <p class="auth-switch">Already have an account? <a href="/login/">Sign in</a></p>
        <?php endif; ?>
<?php require dirname(__DIR__, 2) . '/includes/auth_end.php'; ?>
