<?php

declare(strict_types=1);

/**
 * Admin login, step two: authenticator code (or a one-time recovery code).
 * Reachable only after a correct password for an account with two-factor
 * authentication. The visitor is NOT signed in until this step succeeds.
 */

require_once dirname(__DIR__) . '/app/controllers/AuthController.php';
require_once dirname(__DIR__) . '/app/helpers/admin_ui.php';

admin_send_headers();

$controller = new AuthController();
$ip = admin_client_ip();
$userAgent = admin_user_agent();

$mode = ($_GET['mode'] ?? '') === 'recovery' ? 'recovery' : 'code';
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $result = $controller->submitTwoFactor($_POST, $ip, $userAgent);
    $mode = $result['mode'];

    if ($result['status'] === 'success') {
        admin_redirect('dashboard.php', 303);
    }
    if ($result['status'] === 'expired') {
        admin_redirect('login.php?reason=twofactor', 303);
    }

    http_response_code($result['http']);
    if ($result['http'] === 429) {
        header('Retry-After: ' . (AppConfig::LOGIN_WINDOW_MINUTES * 60));
    }
    $error = $result['message'];
} elseif (!$controller->twoFactorPending()) {
    admin_redirect('login.php');
}

csrf_token();

$isRecovery = $mode === 'recovery';
$authTitle = 'Verification';
$authHeading = 'Verification code';
$authLead = $isRecovery
    ? 'Enter one of your one-time recovery codes.'
    : 'Enter the 6-digit code from your authenticator app.';
require dirname(__DIR__) . '/includes/admin/auth_start.php';
?>
            <?php if ($error !== null): ?>
                <div class="login__notice" data-login-notice tabindex="-1">
                    <?= admin_alert('error', $error) ?>
                </div>
            <?php endif; ?>

            <form class="login__form" method="post" action="two-factor.php<?= $isRecovery ? '?mode=recovery' : '' ?>" novalidate data-single-submit>
                <?= csrf_field() ?>
                <input type="hidden" name="mode" value="<?= $isRecovery ? 'recovery' : 'code' ?>">

                <div class="field">
                    <?php if ($isRecovery): ?>
                        <label class="label" for="recovery_code">Recovery code</label>
                        <input class="input input--code" type="text" id="recovery_code" name="recovery_code"
                               autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="32" required autofocus
                               aria-describedby="recovery-hint">
                        <p class="hint" id="recovery-hint">Each recovery code works once. Use the format XXXXX-XXXXX.</p>
                    <?php else: ?>
                        <label class="label" for="code">Authentication code</label>
                        <input class="input input--code" type="text" id="code" name="code"
                               inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]*" maxlength="10" required autofocus>
                    <?php endif; ?>
                </div>

                <div class="login__actions">
                    <button type="submit" class="btn btn--primary btn--block" data-submit-button>Verify</button>
                    <p class="login__forgot">
                        <?php if ($isRecovery): ?>
                            <a href="two-factor.php">Use an authenticator code instead</a>
                        <?php else: ?>
                            <a href="two-factor.php?mode=recovery">Use a recovery code instead</a>
                        <?php endif; ?>
                    </p>
                    <p class="login__forgot"><a href="login.php">Back to sign in</a></p>
                </div>
            </form>
<?php require dirname(__DIR__) . '/includes/admin/auth_end.php'; ?>
