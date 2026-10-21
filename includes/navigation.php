<?php

declare(strict_types=1);

/**
 * Public main navigation (spec 09_spec_webpage_ui.md, revised by spec 10A), included by includes/header.php.
 * Desktop (owner decision of 2026-10-16): the page links are centred in the header; Duty Calculator and Login sit on
 * the right (this element is display: contents, so both lists are direct parts of the header row). Parts and
 * Accessories and the phone are in the utility bar above on desktop, and in this menu on smaller screens.
 * Small screens: one full-screen menu opened by the menu icon (assets/js/public.js). Without
 * JavaScript the lists are simply shown (assets/css/public-nojs.css), so every page stays reachable.
 *
 * Expected variables: array $page (nav = route name of the current section, navExact = true when the
 * current page IS that route; false/absent marks only the section).
 */

$currentNav = (string) ($page['nav'] ?? '');
$exact = !empty($page['navExact']);
$mark = static function (string $route) use ($currentNav, $exact): string {
    if ($currentNav !== $route) {
        return '';
    }

    return $exact ? ' aria-current="page"' : ' aria-current="true"';
};
$navItems = public_nav_items(!empty($page['signed_in']));
$main = array_values(array_filter($navItems, static fn (array $i): bool => !isset($i['utility'])));
$utility = array_values(array_filter($navItems, static fn (array $i): bool => isset($i['utility'])));
$phone = $page['phone'] ?? null;   // array{display,tel} from the settings table, or null
$dutyUrl = (string) ($page['dutyUrl'] ?? '');   // validated external duty calculator address
$renderItem = static function (array $item) use ($mark): string {
    return '<li><a href="' . e($item['path']) . '"' . $mark($item['route']) . '>'
        . (isset($item['icon']) ? public_icon($item['icon']) : '') . '<span' . (!empty($item['icon_only']) ? ' class="site-nav__label"' : '') . '>' . e($item['label']) . '</span></a></li>';
};
?>
<nav id="site-menu" class="site-nav" aria-label="Main" data-site-nav>
    <div class="site-nav__top">
        <a class="site-logo" href="/" aria-label="<?= e(PUBLIC_SITE_NAME) ?>, home"><?= public_logo_markup() ?></a>
        <button class="menu-icon" type="button" data-nav-close aria-label="Close menu"><?= public_icon('close') ?></button>
    </div>
    <ul class="site-nav__list">
        <?php foreach ($main as $item): ?>
            <?= $renderItem($item) . "\n" ?>
        <?php endforeach; ?>
    </ul>
    <ul class="site-nav__utility">
        <li class="site-nav__mobile-only"><a href="/parts/"<?= $mark('parts') ?>><?= public_icon('engine') ?><span>Parts and Accessories</span></a></li>
        <?php if (is_array($phone)): ?>
            <li class="site-nav__phone site-nav__mobile-only"><a href="tel:<?= e($phone['tel']) ?>"><?= public_icon('phone') ?><span><?= e($phone['display']) ?></span></a></li>
        <?php endif; ?>
        <?php if ($dutyUrl !== ''): ?>
            <li class="site-nav__duty"><a href="<?= e($dutyUrl) ?>" target="_blank" rel="noopener noreferrer"><?= public_icon('calculator') ?><span>Duty Calculator</span><span class="sr-only"> (opens the external duty calculator in a new tab)</span></a></li>
        <?php endif; ?>
        <?php foreach ($utility as $item): ?>
            <?= $renderItem($item) . "\n" ?>
        <?php endforeach; ?>
    </ul>
</nav>
