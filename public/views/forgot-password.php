<?php

declare(strict_types=1);

/**
 * "Forgot your password?" (2026-10-22). Presentation only: CustomerAuthController::forgot supplies values, errors, message,
 * csrf, unavailable. The answer never reveals whether an address has an account. Rate limits answer with a toast.
 */
$values = $data['values'] ?? [];
$errors = $data['errors'] ?? [];
$authTitle = 'Forgot your password?';
$authIntro = 'Enter the email address of your account. We will email you a link and a 6-digit code to set a new password.';
$authTrust = 'The link and the code work once and expire after 30 minutes. We never send or ask for your password.';
require dirname(__DIR__, 2) . '/includes/auth_start.php';
?>
<?php if (!empty($data['message'])) { public_toast($page, 'error', (string) $data['message']); } ?>

        <?php if (empty($data['unavailable'])): ?>
            <form class="auth-form" method="post" action="/forgot-password/" novalidate data-auth-form>
                <input type="hidden" name="csrf_token" value="<?= e((string) $data['csrf']) ?>">
                <div class="auth-field<?= isset($errors['email']) ? ' auth-field--error' : '' ?>">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" value="<?= e((string) ($values['email'] ?? '')) ?>" placeholder="you@example.com"
                           autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" maxlength="255" required<?= isset($errors['email']) ? ' aria-invalid="true" aria-describedby="email-error"' : '' ?>>
                    <?php if (isset($errors['email'])): ?><p class="auth-field__error" id="email-error"><?= e($errors['email']) ?></p><?php endif; ?>
                </div>
                <button type="submit" class="auth-submit" data-auth-submit data-busy-label="Sending">Send reset link and code</button>
            </form>
            <p class="auth-switch">Already have a code? <a href="/reset-password/">Enter it here</a></p>
            <p class="auth-switch">Remembered it? <a href="/login/">Back to sign in</a></p>
        <?php endif; ?>
<?php require dirname(__DIR__, 2) . '/includes/auth_end.php'; ?>
