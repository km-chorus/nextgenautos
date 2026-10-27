<?php

declare(strict_types=1);

/** Shown for a planned page that a later specification builds. Neutral: no invented content. */
?>
<?= public_page_header((string) $page['title']) ?>
<div class="container section">
    <?= public_empty_state('This page is not available yet', 'We are still preparing this part of the website. Please browse the rest of the site in the meantime.', ['label' => 'Back to home', 'href' => '/']) ?>
</div>
