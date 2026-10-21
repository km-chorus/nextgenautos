<?php

declare(strict_types=1);

/**
 * Account security (/admin/security.php): two-factor authentication, recovery
 * codes and the administrator's own password.
 *
 * Protected: requireAdmin() runs first. Presentation only; the work is done by
 * SecurityController -> AuthService. Recovery codes appear only in the response
 * to the request that created them and are never stored in readable form.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/SecurityController.php';

$admin = requireAdmin();

$view = (new SecurityController())->handle(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_POST,
    $admin,
    admin_client_ip(),
    admin_user_agent()
);
http_response_code($view['http']);

$pageTitle   = 'Account security';
$activeNav   = 'security';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Account security', null]];
$errors = $view['errors'];
$pwErrors = $view['passwordErrors'];

/** Small helper for repeated "field with error" markup. */
$fieldError = static function (string $id, ?string $message): string {
    return '<p class="field-error" id="' . e($id) . '-error" role="alert"' . ($message === null ? ' hidden' : '') . '>'
        . admin_icon('alert-circle', 'icon--sm') . '<span data-error-text>' . e($message ?? '') . '</span></p>';
};

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<div class="stack--lg stack">

    <?php if ($view['notice'] !== null): ?>
        <?= admin_alert($view['notice']['type'], $view['notice']['text']) ?>
    <?php endif; ?>
    <?php if (isset($errors['page'])): ?>
        <?= admin_alert('error', $errors['page']) ?>
    <?php endif; ?>

    <?php if ($view['codes'] !== null): ?>
        <section class="card recovery" aria-labelledby="codes-heading">
            <h2 class="card__title" id="codes-heading">Save your recovery codes</h2>
            <p class="text-muted">Each code works once and can replace an authenticator code if you lose your phone. Store them somewhere safe, such as a password manager. <strong>They will not be shown again.</strong></p>
            <ol class="recovery-codes">
                <?php foreach ($view['codes'] as $code): ?>
                    <li><code><?= e($code) ?></code></li>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endif; ?>

    <!-- Two-factor authentication -->
    <section class="card" aria-labelledby="tf-heading">
        <div class="card__row">
            <h2 class="card__title" id="tf-heading">Two-factor authentication</h2>
            <?= admin_status_badge($view['enabled'] ? 'ENABLED' : 'NOT_ENABLED') ?>
        </div>
        <p class="text-muted">Adds a second step at sign-in: a 6-digit code from an authenticator app such as Google Authenticator, Microsoft Authenticator or Authy.</p>

        <?php if (isset($errors['twofactor'])): ?>
            <div class="section-alert"><?= admin_alert('error', $errors['twofactor']) ?></div>
        <?php endif; ?>

        <?php if (!$view['available']): ?>
            <div class="section-alert"><?= admin_alert('warning', 'Two-factor authentication is unavailable', 'The application encryption key (APP_KEY) is not configured on this server.') ?></div>

        <?php elseif ($view['enabled']): ?>
            <dl class="facts">
                <div><dt>Enabled since</dt><dd><?= e(admin_format_date((string) $view['confirmedAt'])) ?></dd></div>
                <div><dt>Recovery codes remaining</dt><dd><?= e((string) $view['remaining']) ?> of <?= AppConfig::RECOVERY_CODE_COUNT ?></dd></div>
            </dl>

            <div class="settings-grid">
                <form method="post" action="security.php" class="settings-form" novalidate data-single-submit>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="regenerate_codes">
                    <h3 class="settings-form__title">Create new recovery codes</h3>
                    <p class="hint">The current codes stop working immediately.</p>
                    <?php if (isset($errors['regenerate'])): ?><?= admin_alert('error', $errors['regenerate']) ?><?php endif; ?>
                    <div class="field">
                        <label class="label" for="rg_password">Current password</label>
                        <input class="input" type="password" id="rg_password" name="current_password" autocomplete="current-password" maxlength="1024" required>
                    </div>
                    <div class="field">
                        <label class="label" for="rg_code">Authenticator code</label>
                        <input class="input input--code" type="text" id="rg_code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]*" maxlength="10" required>
                    </div>
                    <button type="submit" class="btn btn--secondary" data-submit-button>Create new codes</button>
                </form>

                <form method="post" action="security.php" class="settings-form" novalidate data-single-submit>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="disable_2fa">
                    <h3 class="settings-form__title">Turn off two-factor authentication</h3>
                    <p class="hint">Needs your password and a current code. Your account will be protected by the password alone.</p>
                    <?php if (isset($errors['disable'])): ?><?= admin_alert('error', $errors['disable']) ?><?php endif; ?>
                    <div class="field">
                        <label class="label" for="dis_password">Current password</label>
                        <input class="input" type="password" id="dis_password" name="current_password" autocomplete="current-password" maxlength="1024" required>
                    </div>
                    <div class="field">
                        <label class="label" for="dis_code">Authenticator code</label>
                        <input class="input input--code" type="text" id="dis_code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]*" maxlength="10">
                    </div>
                    <div class="field">
                        <label class="label" for="dis_recovery">Or a recovery code <span class="label__note">(only if you cannot use the app)</span></label>
                        <input class="input input--code" type="text" id="dis_recovery" name="recovery_code" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="32">
                    </div>
                    <button type="submit" class="btn btn--danger" data-submit-button data-disable-form>Turn off</button>
                </form>
            </div>

        <?php elseif ($view['setup'] !== null): ?>
            <div class="setup">
                <ol class="setup__steps">
                    <li>Open your authenticator app and add a new account.</li>
                    <li>Scan this QR code, or choose "enter a setup key" and type the key below.</li>
                    <li>Enter the 6-digit code the app shows to finish.</li>
                </ol>
                <div class="setup__body">
                    <div class="qr" role="img" aria-label="QR code for your authenticator app"><?= $view['setup']['svg'] ?></div>
                    <div>
                        <p class="label">Setup key</p>
                        <p class="setup-key"><code><?= e($view['setup']['key']) ?></code></p>
                        <p class="hint">Keep this key private. It is shown only while you set up.</p>
                    </div>
                </div>
                <form method="post" action="security.php" class="settings-form settings-form--narrow" novalidate data-single-submit>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="confirm_2fa">
                    <div class="field">
                        <label class="label" for="confirm_code">Authenticator code</label>
                        <input class="input input--code" type="text" id="confirm_code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]*" maxlength="10" required autofocus>
                    </div>
                    <div class="cluster">
                        <button type="submit" class="btn btn--primary" data-submit-button>Confirm and enable</button>
                    </div>
                </form>
                <form method="post" action="security.php" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cancel_2fa_setup">
                    <button type="submit" class="btn btn--ghost btn--sm">Cancel setup</button>
                </form>
            </div>

        <?php else: ?>
            <form method="post" action="security.php" novalidate data-single-submit>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="start_2fa">
                <button type="submit" class="btn btn--primary" data-submit-button>Set up two-factor authentication</button>
            </form>
        <?php endif; ?>
    </section>

    <!-- Password -->
    <section class="card" aria-labelledby="pw-heading">
        <h2 class="card__title" id="pw-heading">Password</h2>
        <p class="text-muted">Changing your password signs you out everywhere else.</p>
        <?php if (isset($errors['password'])): ?>
            <div class="section-alert"><?= admin_alert('error', $errors['password']) ?></div>
        <?php endif; ?>

        <form method="post" action="security.php" class="settings-form settings-form--narrow" novalidate data-single-submit data-password-form>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_password">
            <div class="field">
                <label class="label" for="cp_current">Current password</label>
                <input class="input" type="password" id="cp_current" name="current_password" autocomplete="current-password" maxlength="1024" required>
            </div>
            <div class="field">
                <label class="label" for="cp_new">New password</label>
                <input class="input" type="password" id="cp_new" name="new_password" autocomplete="new-password" maxlength="<?= PasswordPolicy::MAX_BYTES ?>" required
                       aria-describedby="cp_new-error password-strength" data-password-strength<?= isset($pwErrors['new_password']) ? ' aria-invalid="true"' : '' ?>>
                <p class="hint" id="password-strength" data-strength-label aria-live="polite">At least <?= PasswordPolicy::MIN_LENGTH ?> characters.</p>
                <?= $fieldError('cp_new', $pwErrors['new_password'] ?? null) ?>
            </div>
            <div class="field">
                <label class="label" for="cp_confirm">Confirm new password</label>
                <input class="input" type="password" id="cp_confirm" name="confirm_password" autocomplete="new-password" maxlength="<?= PasswordPolicy::MAX_BYTES ?>" required
                       aria-describedby="cp_confirm-error"<?= isset($pwErrors['confirm_password']) ? ' aria-invalid="true"' : '' ?>>
                <?= $fieldError('cp_confirm', $pwErrors['confirm_password'] ?? null) ?>
            </div>
            <?php if ($view['enabled']): ?>
                <div class="field">
                    <label class="label" for="cp_code">Authenticator code</label>
                    <input class="input input--code" type="text" id="cp_code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]*" maxlength="10" required>
                </div>
            <?php endif; ?>
            <button type="submit" class="btn btn--primary" data-submit-button>Change password</button>
        </form>
    </section>
</div>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
