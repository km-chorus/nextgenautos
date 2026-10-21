<?php

declare(strict_types=1);

/** @var array<string,mixed> $data */
?>
<?= public_page_header('Something went wrong', 'Error 500', 'We could not complete your request. Please try again in a moment.') ?>
<div class="container section">
    <?php if (!empty($data['detail'])): ?>
        <?= public_alert('warning', 'Development detail: ' . (string) $data['detail']) ?>
    <?php endif; ?>
    <div class="actions">
        <a class="btn btn--primary" href="/">Go to home</a>
    </div>
</div>
