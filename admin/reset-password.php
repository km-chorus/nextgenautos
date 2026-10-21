<?php

declare(strict_types=1);

/**
 * Reset password: choose a new password after following the emailed link.
 *
 * Opening the link (GET ?token=...) validates it, remembers it in the server-side
 * session and immediately redirects to this page WITHOUT the token. The form then
 * works only for that session. After a successful reset the link is dead, every
 * older session of the administrator is ended, and two-factor authentication stays
 * exactly as it was.
 */

require_once dirname(__DIR__) . '/app/controllers/PasswordResetController.php';
require_once dirname(__DIR__) . '/app/helpers/admin_ui.php';

admin_send_headers();

$controller = new PasswordResetController();
$ip = admin_client_ip();
$userAgent = admin_user_agent();

$error = null;
$fieldErrors = [];
$linkOk = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $result = $controller->submitReset($_POST, $ip, $userAgent);

    if ($result['status'] === 'done') {
        admin_redirect('login.php?reason=reset', 303);
    }

    http_response_code($result['http']);
    $error = $result['message'];
    $fieldErrors = $result['fieldErrors'];
    $linkOk = in_array($result['status'], ['invalid_input', 'csrf'], true) && $controller->hasBoundLink();
} elseif (isset($_GET['token'])) {
    $token = is_string($_GET['token']) ? $_GET['token'] : '';
    $result = $controller->openLink($token, $ip, $userAgent);

    if ($result['status'] === 'bound') {
        admin_redirect('reset-password.php', 303); // token leaves the URL
    }
    http_response_code($result['http']);
    $error = $result['message'];
} else {
    $linkOk = $controller->hasBoundLink();
    if (!$linkOk) {
        $error = PasswordResetController::MESSAGE_LINK_INVALID;
    }
}

csrf_token();

$authTitle = 'Reset password';
$authHeading = 'Choose a new password';
$authLead = $linkOk ? 'Use at least ' . PasswordPolicy::MIN_LENGTH . ' characters. A passphrase works well.' : '';
require dirname(__DIR__) . '/includes/admin/auth_start.php';
?>
            <?php if ($error !== null): ?>
                <div class="login__notice" data-login-notice tabindex="-1">
                    <?= admin_alert('error', $error) ?>
                </div>
            <?php endif; ?>

            <?php if ($linkOk): ?>
                <form class="login__form" method="post" action="reset-password.php" novalidate data-single-submit data-password-form>
                    <?= csrf_field() ?>

                    <div class="field">
                        <label class="label" for="new_password">New password</label>
                        <div class="input-group">
                            <input class="input" type="password" id="new_password" name="new_password" autocomplete="new-password"
                                   maxlength="<?= PasswordPolicy::MAX_BYTES ?>" required aria-describedby="new_password-error password-strength"
                                   data-password-strength<?= isset($fieldErrors['new_password']) ? ' aria-invalid="true"' : '' ?>>
                            <button type="button" class="btn btn--ghost input-group__button" data-password-toggle
                                    aria-controls="new_password" aria-label="Show password" aria-pressed="false" hidden>
                                <?= admin_icon('eye') ?>
                            </button>
                        </div>
                        <p class="hint" id="password-strength" data-strength-label aria-live="polite">At least <?= PasswordPolicy::MIN_LENGTH ?> characters.</p>
                        <p class="field-error" id="new_password-error" role="alert"<?= isset($fieldErrors['new_password']) ? '' : ' hidden' ?>>
                            <?= admin_icon('alert-circle', 'icon--sm') ?><span data-error-text><?= e($fieldErrors['new_password'] ?? '') ?></span>
                        </p>
                    </div>

                    <div class="field">
                        <label class="label" for="confirm_password">Confirm new password</label>
                        <input class="input" type="password" id="confirm_password" name="confirm_password" autocomplete="new-password"
                               maxlength="<?= PasswordPolicy::MAX_BYTES ?>" required aria-describedby="confirm_password-error"<?= isset($fieldErrors['confirm_password']) ? ' aria-invalid="true"' : '' ?>>
                        <p class="field-error" id="confirm_password-error" role="alert"<?= isset($fieldErrors['confirm_password']) ? '' : ' hidden' ?>>
                            <?= admin_icon('alert-circle', 'icon--sm') ?><span data-error-text><?= e($fieldErrors['confirm_password'] ?? '') ?></span>
                        </p>
                    </div>

                    <div class="login__actions">
                        <button type="submit" class="btn btn--primary btn--block" data-submit-button>Change password</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="login__actions">
                    <a class="btn btn--primary btn--block" href="forgot-password.php">Request a new link</a>
                    <p class="login__forgot"><a href="login.php">Back to sign in</a></p>
                </div>
            <?php endif; ?>
<?php require dirname(__DIR__) . '/includes/admin/auth_end.php'; ?>
