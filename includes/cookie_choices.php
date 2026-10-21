<?php

declare(strict_types=1);

/**
 * The two cookie categories (spec 18), shared by the settings dialog and the /cookies/ page. Presentation only.
 * Expected: array $consent (chosen, activity). The enclosing form posts consent=custom; the checkbox sends activity=1.
 */
?>
        <div class="cookie-choice">
            <div class="cookie-choice__head">
                <h3 class="cookie-choice__title" id="cookie-essential-title">Essential cookies</h3>
                <span class="cookie-choice__always">Always on</span>
            </div>
            <p class="cookie-choice__text">Keep the website secure, protect our forms from spam and keep you signed in. The website cannot work without them, so they cannot be switched off.</p>
        </div>
        <div class="cookie-choice">
            <div class="cookie-choice__head">
                <label class="cookie-choice__title" for="cookie-activity-<?= e($choicesId ?? 'dialog') ?>">Browsing activity</label>
                <span class="cookie-switch">
                    <input class="cookie-switch__input" type="checkbox" role="switch" name="activity" value="1" id="cookie-activity-<?= e($choicesId ?? 'dialog') ?>"<?= $consent['activity'] ? ' checked' : '' ?> aria-describedby="cookie-activity-text-<?= e($choicesId ?? 'dialog') ?>">
                    <span class="cookie-switch__track" aria-hidden="true"></span>
                </span>
            </div>
            <p class="cookie-choice__text" id="cookie-activity-text-<?= e($choicesId ?? 'dialog') ?>">Lets your browser keep copies of the pages you visit, so going back is faster. When off, nothing you browse here is kept in your browser, and copies kept earlier are removed.</p>
        </div>
