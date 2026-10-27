<?php

declare(strict_types=1);

/**
 * Result of the "Confirm your email" link (spec 22). Presentation only. Never says whether an account exists for an
 * invalid link.
 *
 * Expects $data['result']: verified | already | expired | invalid | error.
 */
$result = (string) ($data['result'] ?? 'invalid');
[$authTitle, , $type, $text] = match ($result) {   // the band shows the title only; the message is in the card
    'verified' => ['Email confirmed', 'Thank you.', 'success', 'Your email address is confirmed. The shipments our team records for you now appear in your account.'],
    'already'  => ['Email already confirmed', 'Nothing more to do.', 'success', 'This email address was already confirmed.'],
    'expired'  => ['This link has expired', 'Links work for 48 hours.', 'warning', 'Sign in to your account and press "Send the link again" to get a new one.'],
    'error'    => ['Something went wrong', 'Please try again shortly.', 'error', 'Your email could not be confirmed right now. Please open the link again in a moment.'],
    default    => ['This link is not valid', 'Please use the latest link we sent.', 'error', 'The link may be incomplete. Sign in to your account to send a new one.'],
};
require dirname(__DIR__, 2) . '/includes/auth_start.php';
?>
        <?= public_alert($type, $text) ?>
        <p class="auth-switch"><a class="btn btn--primary" href="/account/">Go to my account</a></p>
<?php require dirname(__DIR__, 2) . '/includes/auth_end.php'; ?>
