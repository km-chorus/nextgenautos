<?php

declare(strict_types=1);

/**
 * Customer account page (spec 13_spec_customer_auth.md). Deliberately minimal: it confirms who is signed in and
 * offers sign out. What a signed-in customer can DO (enquiries, saved vehicles, tracking) is not decided yet and
 * needs its own approved specification.
 */
$authTitle = 'My account';
$authLead = 'You are signed in.';
require dirname(__DIR__, 2) . '/includes/auth_start.php';
?>
        <?php if (!empty($data['message'])): ?>
            <?= public_alert('error', (string) $data['message']) ?>
        <?php endif; ?>

        <?php if (is_array($data['customer'] ?? null)): ?>
            <dl class="auth-details">
                <div><dt>Name</dt><dd><?= e((string) $data['customer']['name']) ?></dd></div>
                <div><dt>Email</dt><dd><?= e((string) $data['customer']['email']) ?></dd></div>
            </dl>

            <form method="post" action="/account/">
                <input type="hidden" name="csrf_token" value="<?= e((string) $data['csrf']) ?>">
                <button type="submit" class="auth-submit auth-submit--secondary">Sign out</button>
            </form>
        <?php endif; ?>
<?php require dirname(__DIR__, 2) . '/includes/auth_end.php'; ?>
