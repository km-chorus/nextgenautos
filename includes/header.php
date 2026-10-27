<?php

declare(strict_types=1);

/**
 * Public layout, first half (spec 09_spec_webpage_ui.md): the document <head>, the skip link,
 * the site header with the shared navigation, and the opening of <main>. Closed by includes/footer.php.
 * Presentation only. The admin area has its own shell (includes/admin/shell_start.php); the two
 * never share markup, navigation or styles.
 *
 * Expected variables (set by PublicController): array $page (title, path, robots, description?, image?, nav, navExact?).
 */

require_once dirname(__DIR__) . '/app/helpers/public_ui.php';

$pageDescription = $page['description'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= public_meta($page) . "\n" ?>
    <meta name="theme-color" content="#2B2F35">
    <link rel="icon" href="data:,">
    <link rel="preload" href="/assets/fonts/manrope-latin-wght.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= public_asset('css/public.css') ?>">
<?php foreach ($page['css'] ?? [] as $assetFile): if (preg_match('/^[a-z0-9-]+\.css$/', (string) $assetFile) === 1): ?>
    <link rel="stylesheet" href="<?= public_asset('css/' . $assetFile) ?>">
<?php endif; endforeach; ?>
<?php foreach ($page['preloads'] ?? [] as $preload): ?>
    <link rel="preload" as="image" media="<?= e($preload['media']) ?>" href="<?= e($preload['href']) ?>"<?= $preload['srcset'] !== null ? ' imagesrcset="' . e($preload['srcset']) . '" imagesizes="100vw"' : '' ?> fetchpriority="high">
<?php endforeach; ?>
    <noscript><link rel="stylesheet" href="<?= public_asset('css/public-nojs.css') ?>"></noscript>
    <script src="<?= public_asset('js/public.js') ?>" defer></script>
    <script src="<?= public_asset('js/assistant.js') ?>" defer></script>
    <script src="<?= public_asset('js/favourites.js') ?>" defer></script>
<?php foreach ($page['js'] ?? [] as $assetFile): if (preg_match('/^[a-z0-9-]+\.js$/', (string) $assetFile) === 1): ?>
    <script src="<?= public_asset('js/' . $assetFile) ?>" defer></script>
<?php endif; endforeach; ?>
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>

<?php require __DIR__ . '/utility_bar.php'; ?>

<header class="site-header">
    <div class="container site-header__inner">
        <a class="site-logo" href="/" aria-label="<?= e(PUBLIC_SITE_NAME) ?>, home"><?= public_logo_markup() ?></a>

        <button class="menu-icon" type="button" data-nav-toggle aria-expanded="false" aria-controls="site-menu" aria-label="Open menu"><?= public_icon('menu') ?></button>

        <?php require __DIR__ . '/navigation.php'; ?>
    </div>
</header>

<main id="main" tabindex="-1" data-site-background>
