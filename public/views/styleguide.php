<?php

declare(strict_types=1);

/** Development-only component library (route exists only when APP_ENV=development). */
?>
<?= public_page_header('Component library', 'Development only', 'Every shared public component in one place. This page does not exist in production.') ?>
<div class="container section">

    <section class="section-block">
        <h2>Typography</h2>
        <h1 class="demo-h">Heading one</h1>
        <h2 class="demo-h">Heading two</h2>
        <h3>Heading three</h3>
        <p>Body text in Nunito. <a href="/styleguide/">A text link</a> is underlined and keeps its colour contrast.</p>
        <p class="muted">Secondary text uses the muted colour.</p>
    </section>

    <section class="section-block">
        <h2>Buttons</h2>
        <div class="actions">
            <a class="btn btn--primary" href="/styleguide/">Primary</a>
            <a class="btn btn--secondary" href="/styleguide/">Secondary</a>
            <a class="btn btn--outline" href="/styleguide/">Outline</a>
            <button class="btn btn--primary" type="button" disabled>Disabled</button>
        </div>
    </section>

    <section class="section-block">
        <h2>Form fields</h2>
        <form class="form" action="/styleguide/" method="get" novalidate>
            <div class="field">
                <label for="sg-name">Name</label>
                <input id="sg-name" name="name" type="text" autocomplete="off">
                <p class="field__hint">A short hint under the field.</p>
            </div>
            <div class="field field--error">
                <label for="sg-email">Email</label>
                <input id="sg-email" name="email" type="email" aria-invalid="true" aria-describedby="sg-email-error">
                <p class="field__error" id="sg-email-error">Enter a valid email address.</p>
            </div>
            <div class="field">
                <label for="sg-note">Message</label>
                <textarea id="sg-note" name="note" rows="3"></textarea>
            </div>
            <button class="btn btn--primary" type="submit">Submit</button>
        </form>
    </section>

    <section class="section-block">
        <h2>Alerts</h2>
        <?= public_alert('success', 'Your request was saved.') ?>
        <?= public_alert('info', 'Helpful information.') ?>
        <?= public_alert('warning', 'Something needs attention.') ?>
        <?= public_alert('error', 'Something went wrong.') ?>
    </section>

    <section class="section-block">
        <h2>Empty state</h2>
        <?= public_empty_state('Nothing here yet', 'Content will appear here when it is available.', ['label' => 'Back to home', 'href' => '/']) ?>
    </section>

    <section class="section-block">
        <h2>Icons</h2>
        <p class="icons-row">
            <?php foreach (['menu', 'close', 'arrow-right', 'alert-circle', 'check-circle', 'info', 'inbox'] as $icon): ?>
                <?= public_icon($icon) ?>
            <?php endforeach; ?>
        </p>
    </section>

    <section class="section-block">
        <h2>Error pages</h2>
        <p><a href="/styleguide/error/">Preview the 500 page</a> &middot; <a href="/styleguide/not-a-real-page/">Preview the 404 page</a></p>
    </section>
</div>
