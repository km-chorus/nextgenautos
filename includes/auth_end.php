<?php

declare(strict_types=1);

/**
 * Closing of the customer page frame opened by includes/auth_start.php.
 * Optional $authTrust (string): a short, TRUE statement about how the customer's data is handled, shown under the card's content.
 */
?>
            <?php if (!empty($authTrust)): ?>
                <p class="auth-trust"><?= public_icon('lock') ?><span><?= e($authTrust) ?></span></p>
            <?php endif; ?>
        </section>
    </div>
</div>
