<?php

declare(strict_types=1);

/**
 * Forgot password: request a reset link by email.
 *
 * The page answers identically whether or not the email has an account. If email
 * delivery is not configured here it says so plainly instead of pretending
 * something was sent. The email itself is sent only through EmailService (Brevo).
 */

require_once dirname(__DIR__) . '/app/controllers/PasswordResetController.php';
require_once dirname(__DIR__) . '/app/helpers/admin_ui.php';

admin_send_headers();

$controller = new PasswordResetController();
$ip = admin_client_ip();
$userAgent = admin_user_agent();

$available = PasswordResetService::isAvailable();
$message = null;      // alert shown above the form: ['type' => ..., 'text' => ...]
$email = '';
$fieldError = null;
$requested = false;

if (!$available) {
    // Email cannot be delivered from here: say so honestly. A submitted form gets 503 and nothing is sent.
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        http_response_code(503);
    }
    $message = ['type' => 'warning', 'text' => PasswordResetController::MESSAGE_UNAVAILABLE];
} elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $result = $controller->submitForgot($_POST, $ip, $userAgent);
    http_response_code($result['http']);
    $email = $result['email'];
    $fieldError = $result['fieldError'];

    if ($result['status'] === 'requested') {
        $requested = true;
        $message = ['type' => 'success', 'text' => $result['message']];
    } elseif ($result['message'] !== null) {
        $message = ['type' => 'error', 'text' => $result['message']];
    }
}

csrf_token();

$authTitle = 'Forgot password';
$authHeading = 'Forgot password';
$authLead = 'Enter your administrator email and we will send you a link to choose a new password.';
require dirname(__DIR__) . '/includes/admin/auth_start.php';
?>
            <?php if ($message !== null): ?>
                <div class="login__notice" data-login-notice tabindex="-1">
                    <?= admin_alert($message['type'], $message['text']) ?>
                </div>
            <?php endif; ?>

            <?php if ($available && !$requested): ?>
                <form class="login__form" method="post" action="forgot-password.php" novalidate data-single-submit>
                    <?= csrf_field() ?>
                    <div class="field">
                        <label class="label" for="email">Email address</label>
                        <input class="input" type="email" id="email" name="email" value="<?= e($email) ?>"
                               autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false"
                               maxlength="255" required autofocus aria-describedby="email-error"<?= $fieldError !== null ? ' aria-invalid="true"' : '' ?>>
                        <p class="field-error" id="email-error" role="alert"<?= $fieldError !== null ? '' : ' hidden' ?>>
                            <?= admin_icon('alert-circle', 'icon--sm') ?><span data-error-text><?= e($fieldError ?? '') ?></span>
                        </p>
                    </div>
                    <div class="login__actions">
                        <button type="submit" class="btn btn--primary btn--block" data-submit-button>Send reset link</button>
                    </div>
                </form>
            <?php endif; ?>

            <p class="login__forgot login__forgot--spaced"><a href="login.php">Back to sign in</a></p>
<?php require dirname(__DIR__) . '/includes/admin/auth_end.php'; ?>
