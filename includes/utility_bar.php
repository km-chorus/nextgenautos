<?php

declare(strict_types=1);

/**
 * Desktop utility bar (owner decisions of 2026-10-14 and 2026-10-16): a thin charcoal band above the white header with
 * the company phone on the left and the "Parts and Accessories" button on the right. The duty calculator and Login sit
 * in the header itself (includes/navigation.php). Shown from 62em only; on smaller screens the same links are in the
 * menu. It scrolls away; the white header below stays sticky. Presentation only.
 *
 * Expected variables: array $page (phone = array{display,tel}|null, nav).
 */

$barPhone = $page['phone'] ?? null;
?>
<div class="utility-bar">
    <div class="container utility-bar__inner">
        <ul class="utility-bar__list">
<?php if (is_array($barPhone)): ?>
            <li><a href="tel:<?= e($barPhone['tel']) ?>"><?= public_icon('phone') ?><span><?= e($barPhone['display']) ?></span></a></li>
<?php endif; ?>
        </ul>
        <ul class="utility-bar__list">
            <li><a class="utility-bar__cta" href="/parts/"<?= ($page['nav'] ?? '') === 'parts' ? ' aria-current="page"' : '' ?>>Parts and Accessories</a></li>
        </ul>
    </div>
</div>
