<?php

declare(strict_types=1);

/**
 * Admin login: step one (email + password). If the account uses two-factor
 * authentication the visitor continues to two-factor.php; otherwise they are
 * signed in. Request handling lives in AuthController; this file only wires the
 * request to it and renders the result.
 */

require_once dirname(__DIR__) . '/app/controllers/AuthController.php';
require_once dirname(__DIR__) . '/app/helpers/admin_ui.php';

admin_send_headers();

$controller = new AuthController();
$ip = admin_client_ip();
$userAgent = admin_user_agent();

$notice = null;
$error = null;
$email = '';
$fieldErrors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $result = $controller->submit($_POST, $ip, $userAgent);

    if ($result['status'] === 'success') {
        admin_redirect('dashboard.php', 303); // Post/Redirect/Get
    }
    if ($result['status'] === 'two_factor') {
        admin_redirect('two-factor.php', 303);
    }

    http_response_code($result['http']);
    if ($result['http'] === 429) {
        header('Retry-After: ' . (AppConfig::LOGIN_WINDOW_MINUTES * 60));
    }
    $error = $result['message'];
    $email = $result['email'];
    $fieldErrors = $result['fieldErrors'];
} else {
    $view = $controller->show($_GET, $ip, $userAgent);
    if ($view['redirect']) {
        admin_redirect('dashboard.php');
    }
    $notice = $view['notice'];
}

csrf_token(); // session + token exist before any output (the cookie header must precede the body)

$authTitle = 'Sign in';
$authLead  = 'Enter your administrator email and password.';
require dirname(__DIR__) . '/includes/admin/auth_start.php';
?>
            <?php if ($error !== null || $notice !== null): ?>
                <div class="login__notice" data-login-notice tabindex="-1">
                    <?php if ($error !== null): ?>
                        <?= admin_alert('error', $error) ?>
                    <?php else: ?>
                        <?= admin_alert($notice['type'], $notice['title']) ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form class="login__form" method="post" action="login.php" novalidate data-login-form>
                <?= csrf_field() ?>

                <div class="field">
                    <label class="label" for="email">Email address</label>
                    <input class="input" type="email" id="email" name="email" value="<?= e($email) ?>"
                           autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false"
                           maxlength="255" required aria-describedby="email-error"<?= isset($fieldErrors['email']) ? ' aria-invalid="true"' : '' ?>>
                    <p class="field-error" id="email-error" role="alert"<?= isset($fieldErrors['email']) ? '' : ' hidden' ?>>
                        <?= admin_icon('alert-circle', 'icon--sm') ?><span data-error-text><?= e($fieldErrors['email'] ?? '') ?></span>
                    </p>
                </div>

                <div class="field">
                    <label class="label" for="password">Password</label>
                    <div class="input-group">
                        <input class="input" type="password" id="password" name="password"
                               autocomplete="current-password" maxlength="1024" required aria-describedby="password-error"<?= isset($fieldErrors['password']) ? ' aria-invalid="true"' : '' ?>>
                        <button type="button" class="btn btn--ghost input-group__button" data-password-toggle
                                aria-controls="password" aria-label="Show password" aria-pressed="false" hidden>
                            <?= admin_icon('eye') ?>
                        </button>
                    </div>
                    <p class="field-error" id="password-error" role="alert"<?= isset($fieldErrors['password']) ? '' : ' hidden' ?>>
                        <?= admin_icon('alert-circle', 'icon--sm') ?><span data-error-text><?= e($fieldErrors['password'] ?? '') ?></span>
                    </p>
                </div>

                <div class="login__actions">
                    <button type="submit" class="btn btn--primary btn--block" data-submit-button>Sign in</button>
                    <p class="login__forgot"><a href="forgot-password.php">Forgot password?</a></p>
                </div>
            </form>
<?php require dirname(__DIR__) . '/includes/admin/auth_end.php'; ?>
