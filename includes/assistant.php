<?php

declare(strict_types=1);

/**
 * Site assistant window (spec 19_spec_site_assistant.md): a round button at the bottom right that opens a small chat
 * window. Presentation only; assets/js/assistant.js fetches every answer from /assistant/ and inserts it as text.
 * Hidden until that script runs: without JavaScript the Contact page and WhatsApp remain the way to ask.
 * Not shown on the cookie page (its form needs the space) and not on the assistant endpoint itself.
 */
?>
<div class="assistant" data-assistant hidden>
    <section class="assistant__panel" id="assistant-panel" role="dialog" aria-labelledby="assistant-title" aria-describedby="assistant-sub" data-assistant-panel hidden>
        <header class="assistant__head">
            <span class="assistant__head-icon" aria-hidden="true"><?= public_icon('bot') ?></span>
            <div class="assistant__head-text">
                <h2 class="assistant__title" id="assistant-title"><?= e(PUBLIC_SITE_NAME) ?> Assistant</h2>
                <p class="assistant__sub" id="assistant-sub">Ask about vehicles, prices &amp; more</p>
            </div>
            <button class="assistant__close" type="button" data-assistant-close aria-label="Close the assistant"><?= public_icon('close') ?></button>
        </header>
        <div class="assistant__log" role="log" aria-live="polite" aria-relevant="additions" data-assistant-log>
            <div class="assistant__welcome">
                <span class="assistant__welcome-icon" aria-hidden="true"><?= public_icon('bot') ?></span>
                <p>Hi! How can I help you today?</p>
            </div>
        </div>
        <form class="assistant__form" data-assistant-form>
            <label class="sr-only" for="assistant-input">Type your question</label>
            <input class="assistant__input" type="text" id="assistant-input" name="q" maxlength="200" autocomplete="off" placeholder="Type a message..." data-assistant-input>
            <button class="assistant__send" type="submit" aria-label="Send" data-assistant-send><?= public_icon('send') ?></button>
        </form>
    </section>
    <button class="assistant__launcher" type="button" data-assistant-toggle aria-expanded="false" aria-controls="assistant-panel" aria-label="Open the site assistant">
        <span class="assistant__launcher-open" aria-hidden="true"><?= public_icon('message') ?></span>
        <span class="assistant__launcher-close" aria-hidden="true"><?= public_icon('close') ?></span>
    </button>
</div>
