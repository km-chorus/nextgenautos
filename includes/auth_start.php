<?php

declare(strict_types=1);

/**
 * Opening of the customer sign-in / registration / account page frame (spec 13): a dark brand band carrying the
 * page title, and a white card that overlaps the band's lower edge. Closed by includes/auth_end.php.
 * Presentation only. The business name already appears in the site header, so it is not repeated here.
 *
 * Expected variables: string $authTitle (the page's one h1). Optional $authIntro: an instruction shown at the top of the card
 * (the band shows the title only, owner request 2026-10-23).
 */
?>
<div class="auth-page">
    <header class="auth-hero">
        <div class="container auth-hero__inner">
            <h1 class="auth-hero__title" id="auth-heading"><?= e($authTitle) ?></h1>
        </div>
    </header>

    <div class="container auth-body">
        <section class="auth-card" aria-labelledby="auth-heading">
<?php if (!empty($authIntro)): ?>
            <p class="auth-card__intro"><?= e((string) $authIntro) ?></p>
<?php endif; ?>
