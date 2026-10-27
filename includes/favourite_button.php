<?php

declare(strict_types=1);

/**
 * Save-to-favourites button (spec 22), shared by the vehicle cards and the vehicle page. Presentation only.
 *
 * A real form (POST /favourites/), so it works without JavaScript; assets/js/favourites.js switches it in place.
 * Signed in: carries the customer's CSRF token and saves or removes. Guest: the same button sends them to sign in and
 * back to this page. The vehicle is named by its public slug; the server decides everything again.
 *
 * Expects: string $favSlug, string $favTitle, string $favVariant ('card' | 'detail'), array $page (favourites, path).
 */
$favState = $page['favourites'] ?? null;
$favSaved = is_array($favState) && in_array($favSlug, $favState['slugs'], true);
$favReturn = (string) ($page['path'] ?? '/');
?>
<form class="fav fav--<?= e($favVariant) ?>" method="post" action="/favourites/" data-fav>
<?php if (is_array($favState)): ?>
    <input type="hidden" name="csrf_token" value="<?= e($favState['csrf']) ?>">
<?php endif; ?>
    <input type="hidden" name="vehicle" value="<?= e($favSlug) ?>">
    <input type="hidden" name="action" value="<?= $favSaved ? 'remove' : 'add' ?>" data-fav-action>
    <input type="hidden" name="return" value="<?= e($favReturn) ?>">
    <button class="fav__button" type="submit" aria-pressed="<?= $favSaved ? 'true' : 'false' ?>" data-fav-button
            data-label-add="Save <?= e($favTitle) ?> to favourites" data-label-remove="Remove <?= e($favTitle) ?> from favourites"
            aria-label="<?= $favSaved ? 'Remove ' . e($favTitle) . ' from favourites' : 'Save ' . e($favTitle) . ' to favourites' ?>">
        <?= public_icon('heart') ?><?php if ($favVariant === 'detail'): ?><span class="fav__text" data-fav-text><?= $favSaved ? 'Saved' : 'Save' ?></span><?php endif; ?>
    </button>
</form>
