<?php

declare(strict_types=1);

/**
 * Admin shell, opening half: <head>, sidebar, top header, <main>.
 * Presentation only. Pages are protected by requireAdmin() before including this file.
 *
 * Expected variables, set by the page before including this file:
 *   string $pageTitle    Page heading (h1) and <title>.
 *   string $activeNav    Key from admin_nav_items(), e.g. 'dashboard'.
 *   array  $breadcrumbs  Optional list of [label, href|null]; last item is current.
 *   array  $admin        The signed-in administrator returned by requireAdmin()
 *                        (['id','name','role']); shown in the header/sidebar.
 *   string $adminRoot    Optional. Path to project root from the page ('..').
 *
 * Close with includes/admin/shell_end.php.
 */

require_once dirname(__DIR__, 2) . '/app/helpers/admin_ui.php';
require_once dirname(__DIR__, 2) . '/app/helpers/csrf.php';
require_once dirname(__DIR__, 2) . '/app/services/AuthorizationService.php';

admin_send_headers();

$pageTitle   = $pageTitle ?? 'Admin';
$activeNav   = $activeNav ?? '';
$breadcrumbs = $breadcrumbs ?? [];
$admin        = $admin ?? null;
echo admin_head($pageTitle);
?>
<body>
<a class="skip-link" href="#main">Skip to main content</a>

<div class="admin-shell" data-admin-shell>
    <div class="nav-backdrop" data-nav-close aria-hidden="true"></div>

    <div class="sidebar" id="admin-sidebar">
        <div class="sidebar__header">
            <a class="brand" href="dashboard.php">
                <span class="brand__name"><?= e(ADMIN_BRAND_NAME) ?></span>
                <span class="brand__sub"><?= e(ADMIN_BRAND_SUBTITLE) ?></span>
            </a>
            <button type="button" class="btn btn--ghost btn--on-dark btn--icon sidebar-close" data-nav-close aria-label="Close navigation menu">
                <?= admin_icon('x') ?>
            </button>
        </div>

        <nav class="sidebar__nav" aria-label="Main navigation">
            <ul class="nav-list">
                <?php foreach (admin_nav_items() as $item): ?>
                    <li>
                        <a class="nav-link" href="<?= e($item['href']) ?>"<?= $item['key'] === $activeNav ? ' aria-current="page"' : '' ?>>
                            <?= admin_icon($item['icon']) ?>
                            <span><?= e($item['label']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="sidebar__footer">
            <div class="profile">
                <?= admin_icon('user', 'icon--lg') ?>
                <div class="profile__text">
                    <span class="profile__name"><?= e($admin['name'] ?? 'Not signed in') ?></span>
                    <?php if ($admin !== null): ?>
                        <span class="profile__meta"><?= e(AuthorizationService::roleLabel($admin['role'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <a class="nav-link"<?= $activeNav === 'security' ? ' aria-current="page"' : '' ?> href="security.php">
                <?= admin_icon('shield') ?>
                <span>Account security</span>
            </a>
            <form class="logout-form" method="post" action="logout.php">
                <?= csrf_field() ?>
                <button type="submit" class="nav-link btn-reset">
                    <?= admin_icon('log-out') ?>
                    <span>Log out</span>
                </button>
            </form>
        </div>
    </div>

    <div class="admin-content" data-nav-background>
        <header class="topbar">
            <button type="button" class="btn btn--ghost btn--icon menu-button" data-nav-toggle
                    aria-label="Open navigation menu" aria-expanded="false" aria-controls="admin-sidebar">
                <?= admin_icon('menu') ?>
            </button>

            <div class="topbar__titles">
                <?php if (count($breadcrumbs) > 0): ?>
                    <nav class="breadcrumbs" aria-label="Breadcrumb">
                        <ol>
                            <?php foreach ($breadcrumbs as $index => [$crumbLabel, $crumbHref]): ?>
                                <li>
                                    <?php if ($index > 0): ?><?= admin_icon('chevron-right', 'icon--sm') ?><?php endif; ?>
                                    <?php if ($crumbHref !== null && $index < count($breadcrumbs) - 1): ?>
                                        <a href="<?= e($crumbHref) ?>"><?= e($crumbLabel) ?></a>
                                    <?php else: ?>
                                        <span aria-current="page"><?= e($crumbLabel) ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </nav>
                <?php endif; ?>
                <h1 class="topbar__title"><?= e($pageTitle) ?></h1>
            </div>

            <div class="user-chip">
                <?= admin_icon('user') ?>
                <span class="user-chip__label"><?= e($admin['name'] ?? 'Not signed in') ?></span>
            </div>
        </header>

        <main id="main" class="main" tabindex="-1" data-focus-after-dismiss>
            <div class="page">
