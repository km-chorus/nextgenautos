<?php

declare(strict_types=1);

/**
 * Layout for the screens a visitor sees BEFORE being signed in: login, second
 * factor, forgot password and reset password. Same design as the original login
 * screen (brand panel + form card). Presentation only.
 *
 * Expected variables, set by the page before including this file:
 *   string $authTitle    <title> text (also the default heading).
 *   string $authHeading  Optional h1 (defaults to $authTitle).
 *   string $authLead     Optional one-line explanation under the heading.
 *
 * Close with includes/admin/auth_end.php.
 */

require_once dirname(__DIR__, 2) . '/app/helpers/admin_ui.php';

$authTitle   = $authTitle ?? 'Sign in';
$authHeading = $authHeading ?? $authTitle;
$authLead    = $authLead ?? '';

echo admin_head($authTitle);
?>
<body>
<a class="skip-link" href="#main">Skip to main content</a>

<div class="login">
    <header class="login__brand">
        <div class="brand">
            <span class="brand__name"><?= e(ADMIN_BRAND_NAME) ?></span>
            <span class="brand__sub"><?= e(ADMIN_BRAND_SUBTITLE) ?></span>
        </div>
        <div class="login__brand-foot">
            <hr class="login__rule">
            <p class="login__tagline">Vehicle imports and car parts.</p>
        </div>
    </header>

    <main id="main" class="login__panel">
        <div class="login__card">
            <h1 class="login__title"><?= e($authHeading) ?></h1>
            <?php if ($authLead !== ''): ?>
                <p class="login__lead"><?= e($authLead) ?></p>
            <?php endif; ?>
