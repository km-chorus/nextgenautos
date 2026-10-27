<?php

declare(strict_types=1);

/**
 * Set a new password (2026-10-22). Two ways, from the same email: the link (mode 'link', hidden token) or the 6-digit code
 * (mode 'code': email + code). Presentation only: CustomerAuthController::resetPassword supplies mode, token, linkState
 * (null | 'invalid' | 'expired'), sent, values, errors, message, csrf, unavailable. Rate limits answer with a toast.
 */
$values = $data['values'] ?? [];
$errors = $data['errors'] ?? [];
$mode = ($data['mode'] ?? 'code') === 'link' ? 'link' : 'code';
$linkState = $data['linkState'] ?? null;
$authTitle = 'Set a new password';
$authIntro = $mode === 'link' ? 'Choose a new password for your account.' : 'Enter the 6-digit code from the email and choose a new password.';
$authTrust = 'After the change, any other device signed in to your account is signed out.';
$field = static function (string $name, string $label, string $type, string $autocomplete, string $extra = '') use ($errors, $values): string {
    $has = isset($errors[$name]);

    return '<div class="auth-field' . ($has ? ' auth-field--error' : '') . '"><label for="' . $name . '">' . e($label) . '</label>'
        . '<input type="' . $type . '" id="' . $name . '" name="' . $name . '" autocomplete="' . $autocomplete . '"'
        . ($type !== 'password' ? ' value="' . e((string) ($values[$name] ?? '')) . '"' : '') . $extra . ' required'
        . ($has ? ' aria-invalid="true" aria-describedby="' . $name . '-error"' : '') . '>'
        . ($has ? '<p class="auth-field__error" id="' . $name . '-error">' . e($errors[$name]) . '</p>' : '') . '</div>';
};
require dirname(__DIR__, 2) . '/includes/auth_start.php';
?>
<?php if (!empty($data['message'])) { public_toast($page, 'error', (string) $data['message']); } ?>

        <?php if (empty($data['unavailable'])): ?>
        <?php if ($mode === 'link' && $linkState !== null): ?>
            <?= public_alert('error', $linkState === 'expired' ? 'This link has expired. Links work for 30 minutes.' : 'This link is not valid any more. It may have been used already, or a newer email was sent.') ?>
            <p class="auth-switch"><a href="/forgot-password/">Send me a new link and code</a></p>
        <?php else: ?>
            <?php if (!empty($data['sent'])) { public_toast($page, 'success', 'If an account exists for this email address, we have sent a link and a 6-digit code. Check your inbox (and spam folder).'); } ?>
            <form class="auth-form" method="post" action="/reset-password/" novalidate data-auth-form>
                <input type="hidden" name="csrf_token" value="<?= e((string) $data['csrf']) ?>">
            <?php if ($mode === 'link'): ?>
                <input type="hidden" name="t" value="<?= e((string) $data['token']) ?>">
            <?php else: ?>
                <?= $field('email', 'Email address', 'email', 'username', ' inputmode="email" autocapitalize="none" spellcheck="false" maxlength="255"') ?>
                <?= $field('code', '6-digit code', 'text', 'one-time-code', ' inputmode="numeric" pattern="[0-9]*" maxlength="7" class="auth-code"') ?>
            <?php endif; ?>
                <?= $field('new_password', 'New password', 'password', 'new-password', ' maxlength="1024"') ?>
                <?= $field('new_password_confirm', 'Type the new password again', 'password', 'new-password', ' maxlength="1024"') ?>
                <button type="submit" class="auth-submit" data-auth-submit data-busy-label="Saving">Set new password</button>
            </form>
            <p class="auth-switch">No email? <a href="/forgot-password/">Send a new link and code</a></p>
        <?php endif; ?>
        <?php endif; ?>
<?php require dirname(__DIR__, 2) . '/includes/auth_end.php'; ?>
