<?php

declare(strict_types=1);

/**
 * Presentation helpers for the PUBLIC website (spec 09_spec_webpage_ui.md): markup and
 * metadata only, no business logic, no database. The admin has its own helpers
 * (admin_ui.php); the two never share a layout.
 *
 * All public URLs are root-relative ("/vehicles/") because pages live at different depths
 * ("/brands/toyota/"); the site is served from the web root, like the admin.
 */

require_once __DIR__ . '/html.php';
require_once dirname(__DIR__) . '/config/app.php';

const PUBLIC_SITE_NAME = 'Next Gen Autos and Parts';   // business name changed 2026-10-23 (was "Mr Amponsah Autos")

/**
 * Security headers for every public page. Same-origin scripts, styles, fonts and images only
 * (no inline script or style anywhere), so a stored value can never run as code.
 */
function public_send_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; font-src 'self'; "
        . "img-src 'self' data:; connect-src 'self'; frame-src https://www.google.com; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");   // frame-src: the Contact page Google Map (2026-10-23)
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Content-Type: text/html; charset=UTF-8');
    AppConfig::sendCommonSecurityHeaders();   // Permissions-Policy; HSTS in production over HTTPS
}

/** Root-relative URL of a file under /assets with a modification-time cache-buster (fonts are referenced from the CSS without one). */
function public_asset(string $path): string
{
    $url = '/assets/' . ltrim($path, '/');
    $file = dirname(__DIR__, 2) . '/assets/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '';

    return e($version !== '' ? $url . '?v=' . $version : $url);
}

/**
 * Main navigation: label, route name and path. 'icon' (optional) names a public_icon(); 'utility' items sit apart, on the right of the desktop header.
 * The Login item is shown as the user icon alone on desktop (its name stays available to screen readers and is
 * written out in the mobile menu). It opens the CUSTOMER sign-in page (spec 13); once a customer is signed in it
 * becomes "Account". The administrator sign-in (/admin/login.php) is deliberately not linked from the public site.
 */
function public_nav_items(bool $signedIn = false, bool $accident = false): array
{
    return array_values(array_filter([
        ['label' => 'Home',         'route' => 'home',         'path' => '/'],
        // Owner request 2026-10-26: Accident Vehicles and Track Vehicle sit under Vehicles, as a dropdown.
        ['label' => 'Vehicles',     'route' => 'vehicles',     'path' => '/vehicles/', 'children' => array_values(array_filter([
            ['label' => 'All Vehicles', 'route' => 'vehicles', 'path' => '/vehicles/'],
            // Spec 23: only while the administrator has switched the Accident Vehicles page on.
            $accident ? ['label' => 'Accident Vehicles', 'route' => 'accident-vehicles', 'path' => '/accident-vehicles/'] : null,
            ['label' => 'Track Vehicle', 'route' => 'track', 'path' => '/track/'],
        ]))],
        ['label' => 'About',        'route' => 'about',        'path' => '/about/'],
        ['label' => 'Contact',      'route' => 'contact',      'path' => '/contact/'],
        // Signed in (owner request 2026-10-22): a settings icon (account details) and a sign-out icon replace the user icon.
        $signedIn ? ['label' => 'Account settings', 'route' => 'account-settings', 'path' => '/account/settings/', 'icon' => 'settings', 'utility' => true, 'icon_only' => true] : null,
        $signedIn
            ? ['label' => 'Sign out', 'route' => 'logout', 'path' => '/account/', 'icon' => 'logout', 'utility' => true, 'icon_only' => true, 'logout' => true]
            : ['label' => 'Login',   'route' => 'login',   'path' => '/login/',   'icon' => 'user', 'utility' => true, 'icon_only' => true],
    ]));
}

/**
 * Footer link groups. Only pages that are public routes; nothing here points at the admin area.
 *
 * @return list<array{title:string,links:list<array{label:string,path:string}>}>
 */
function public_footer_groups(bool $accident = false, bool $team = false): array
{
    return [
        ['title' => 'Company', 'links' => [
            ['label' => 'About Us',     'path' => '/about/'],
            // 2026-10-27: only while the About page shows team members (an active member with a real photo)
            ...($team ? [['label' => 'Our Team', 'path' => '/about/#team']] : []),
        ]],
        ['title' => 'Vehicles', 'links' => [
            ['label' => 'Browse Vehicles', 'path' => '/vehicles/'],
            ...($accident ? [['label' => 'Accident Vehicles', 'path' => '/accident-vehicles/']] : []),
            ['label' => 'Parts and Accessories', 'path' => '/parts/'],   // spec 26: the page is live
            ['label' => 'Brands',          'path' => '/brands/'],
        ]],
        ['title' => 'Support', 'links' => [
            ['label' => 'Track Vehicle', 'path' => '/track/'],
            ['label' => 'Contact Us',    'path' => '/contact/'],
        ]],
    ];
}

/**
 * Metadata for the <head>: title, description, canonical URL, robots and Open Graph.
 *
 * $page keys: title (string), description (?string), path (string, the page's own path),
 *             robots (string, default "index,follow"), image (?string, root-relative path), type (default "website").
 * Nothing is invented: no description means no description tag; no APP_URL configured means no
 * canonical or og:url. The canonical URL is built from the CONFIGURED site address and the
 * validated route path, never from the request's Host header (a forged Host cannot change it).
 */
function public_meta(array $page): string
{
    $name = PUBLIC_SITE_NAME;
    $title = (string) ($page['title'] ?? $name);
    // 'fullTitle' (homepage): the whole <title> as given, brand first; every other page is "Page | Brand".
    $fullTitle = isset($page['fullTitle']) && is_string($page['fullTitle']) && $page['fullTitle'] !== ''
        ? $page['fullTitle']
        : (($title === '' || $title === $name) ? $name : $title . ' | ' . $name);
    $description = trim((string) ($page['description'] ?? ''));
    $robots = (string) ($page['robots'] ?? 'index,follow');
    // SEO 2026-10-27: canonical, og:url and og:image always use the official address (AppConfig::SITE_URL), never the
    // address of the running copy, so a missing or wrong APP_URL can never produce a wrong or missing canonical link.
    // Only indexable pages get a canonical link: a noindex page should not name a canonical URL.
    $base = AppConfig::SITE_URL;
    $path = (string) ($page['path'] ?? '');
    $indexable = !str_contains($robots, 'noindex');
    $canonical = ($indexable && preg_match('#^/[a-z0-9/-]*$#', $path) === 1) ? $base . $path : null;
    $image = (isset($page['image']) && is_string($page['image']) && preg_match('#^/[A-Za-z0-9/_.-]+$#', $page['image']) === 1 && !str_contains($page['image'], '..'))
        ? $base . $page['image'] : null;

    $tags = [
        '<title>' . e($fullTitle) . '</title>',
        '<meta name="robots" content="' . e($robots) . '">',
    ];
    if ($description !== '') {
        $tags[] = '<meta name="description" content="' . e($description) . '">';
    }
    if ($canonical !== null) {
        $tags[] = '<link rel="canonical" href="' . e($canonical) . '">';
    }
    $tags[] = '<meta property="og:site_name" content="' . e($name) . '">';
    $tags[] = '<meta property="og:type" content="' . e((string) ($page['type'] ?? 'website')) . '">';
    $tags[] = '<meta property="og:title" content="' . e($fullTitle) . '">';
    if ($description !== '') {
        $tags[] = '<meta property="og:description" content="' . e($description) . '">';
    }
    if ($canonical !== null) {
        $tags[] = '<meta property="og:url" content="' . e($canonical) . '">';
    }
    if ($image !== null) {
        $tags[] = '<meta property="og:image" content="' . e($image) . '">';
        if (isset($page['imageAlt']) && is_string($page['imageAlt']) && $page['imageAlt'] !== '') {
            $tags[] = '<meta property="og:image:alt" content="' . e($page['imageAlt']) . '">';
        }
    }
    // X (Twitter) reads the Open Graph title, description and image; only the card type is its own.
    $tags[] = '<meta name="twitter:card" content="' . ($image !== null ? 'summary_large_image' : 'summary') . '">';
    foreach ($page['jsonld'] ?? [] as $data) {
        if (is_array($data) && $data !== []) {
            $tags[] = public_json_ld($data);
        }
    }

    return implode("\n    ", $tags);
}

/**
 * One schema.org structured-data block (SEO 2026-10-27). A data block, not a script: browsers never run it, so the
 * Content-Security-Policy is unaffected. The JSON is encoded so that "<", ">" and "&" can never end the element early.
 */
function public_json_ld(array $data): string
{
    $json = json_encode(['@context' => 'https://schema.org'] + $data,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);

    return '<script type="application/ld+json">' . $json . '</script>';
}

/**
 * Small inline SVG icons (no icon library, no extra request). Only the icons the foundation needs.
 * The name is limited to the keys below; the markup is fixed text.
 */
function public_icon(string $name, string $class = ''): string
{
    static $paths = [
        'menu'         => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close'        => '<path d="M6 6l12 12M18 6L6 18"/>',
        'arrow-right'  => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'alert-circle' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>',
        'map-pin'      => '<path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.5"/>',
        'alert-triangle' => '<path d="M10.3 4.3 2.6 17.6A2 2 0 0 0 4.3 20.6h15.4a2 2 0 0 0 1.7-3L13.7 4.3a2 2 0 0 0-3.4 0z"/><path d="M12 9.5v4M12 17h.01"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/>',
        'info'         => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'search'       => '<circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/>',
        'settings'     => '<circle cx="12" cy="12" r="3"/><path d="M12 3v2.5M12 18.5V21M3 12h2.5M18.5 12H21M5.6 5.6l1.8 1.8M16.6 16.6l1.8 1.8M5.6 18.4l1.8-1.8M16.6 7.4l1.8-1.8"/><circle cx="12" cy="12" r="6.5"/>',
        'logout'       => '<path d="M10 4H5.5A1.5 1.5 0 0 0 4 5.5v13A1.5 1.5 0 0 0 5.5 20H10"/><path d="M15 8l4 4-4 4M19 12H9"/>',
        'pause'        => '<path d="M9 6v12M15 6v12"/>',
        'play'         => '<path d="M8 5.5v13l10-6.5z"/>',
        'user'         => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c.6-3.6 3.4-5.5 7-5.5s6.4 1.9 7 5.5"/>',
        'image'        => '<rect x="4" y="5" width="16" height="14" rx="1.5"/><circle cx="9" cy="10" r="1.5"/><path d="M4 16l5-4 4 3 3-2 4 3"/>',
        'phone'        => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
        'chevron-left'  => '<path d="M15 5l-7 7 7 7"/>',
        'chevron-right' => '<path d="M9 5l7 7-7 7"/>',
        'chevron-down' => '<path d="M6 9l6 6 6-6"/>',
        'route'        => '<circle cx="6" cy="18" r="2.5"/><circle cx="18" cy="6" r="2.5"/><path d="M8.5 18H15a3 3 0 0 0 0-6H9a3 3 0 0 1 0-6h6.5"/>',
        'document'     => '<path d="M7 3h7l4 4v14H7z"/><path d="M14 3v4h4M10 12h5M10 16h5"/>',
        'mail'         => '<rect x="3.5" y="5.5" width="17" height="13" rx="1.5"/><path d="M4 7l8 6 8-6"/>',
        'clock'        => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'facebook'     => '<path d="M14.5 8H16V4.5h-2A3.5 3.5 0 0 0 10.5 8v2.5H8V14h2.5v6.5H14V14h2l.5-3.5H14V8.6a.6.6 0 0 1 .5-.6z"/>',
        'instagram'    => '<rect x="4" y="4" width="16" height="16" rx="4.5"/><circle cx="12" cy="12" r="3.6"/><path d="M16.8 7.2h.01"/>',
        'tiktok'       => '<path d="M14 4v10.5a3.5 3.5 0 1 1-3.5-3.5"/><path d="M14 4c.4 2.4 2.1 4 4.5 4.3"/>',
        'x'            => '<path d="M5 4.5l14 15M19 4.5L5 19.5"/>',
        'youtube'      => '<rect x="3" y="6" width="18" height="12" rx="3.5"/><path d="M10.5 9.5v5l4.3-2.5z"/>',
        'linkedin'     => '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 10.5v5.5M8 7.6h.01M11.5 16v-5.5M11.5 13a2.5 2.5 0 0 1 5 0v3"/>',
        'message'      => '<path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v9a1.5 1.5 0 0 1-1.5 1.5H9l-4.5 4V5.5z"/><path d="M8 9h8M8 12.5h5"/>',
        'calculator'   => '<rect x="5" y="3" width="14" height="18" rx="1.5"/><path d="M8 7.5h8M8.5 12h.01M12 12h.01M15.5 12h.01M8.5 16h.01M12 16h.01M15.5 16h.01"/>',
        'car'          => '<path d="M5 16v-4l1.6-4.2A2 2 0 0 1 8.5 6.5h7a2 2 0 0 1 1.9 1.3L19 12v4M3.5 16h17M6.5 16v2.2M17.5 16v2.2"/><circle cx="8" cy="13" r="1"/><circle cx="16" cy="13" r="1"/><path d="M6 12h12"/>',
        'engine'       => '<path d="M5 9h3l1-2h5l1 2h2v3h2v5h-2v2H10l-2-2H5z"/><path d="M3 11.5v3M12 9V7"/>',
        'gearbox'      => '<circle cx="6" cy="5" r="1.7"/><circle cx="12" cy="5" r="1.7"/><circle cx="18" cy="5" r="1.7"/><circle cx="12" cy="19" r="1.7"/><path d="M6 6.7V12h12V6.7M12 6.7v10.6"/>',
        'steering'     => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="2.2"/><path d="M3.2 11h6.6M14.2 11h6.6M12 14.2V21"/>',
        'fuel'         => '<path d="M4 20V5.5A1.5 1.5 0 0 1 5.5 4h6A1.5 1.5 0 0 1 13 5.5V20M3 20h11"/><path d="M6.5 8h4M13 9.5h2.2l1.8 1.8V17a1.5 1.5 0 0 0 3 0V9.5L17.5 7"/>',
        'gauge'        => '<path d="M4 17a8 8 0 1 1 16 0"/><path d="M12 17l3.6-4.6M4 17h2M18 17h2M12 9V8"/>',
        'lock'         => '<rect x="5" y="11" width="14" height="9" rx="1.5"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
        'inbox'        => '<path d="M4 13l2-7h12l2 7v5H4zM4 13h5l1 2h4l1-2h5"/>',
        'calendar'     => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/>',
        'arrow-left'   => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'camera'       => '<path d="M4 8h3l2-2.5h6L17 8h3v11H4z"/><circle cx="12" cy="13" r="3.5"/>',
        'bot'          => '<rect x="4" y="8" width="16" height="11" rx="2.5"/><path d="M12 8V4.5M10.5 4h3M2 12.5v3M22 12.5v3"/><circle cx="9" cy="13.5" r="1.2"/><circle cx="15" cy="13.5" r="1.2"/>',
        'send'         => '<path d="M21 3L10 14M21 3l-7 18-4-7-7-4z"/>',
        'heart'        => '<path d="M12 20s-7.5-4.6-7.5-10.2A4.3 4.3 0 0 1 12 7.1a4.3 4.3 0 0 1 7.5 2.7C19.5 15.4 12 20 12 20z"/>',
    ];
    if (!isset($paths[$name])) {
        return '';
    }

    return '<svg class="icon' . ($class !== '' ? ' ' . e($class) : '') . '" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
}

/**
 * Alert (success | error | warning | info). The type is also written in words for screen readers,
 * so meaning never depends on colour. Errors use role="alert", the others role="status".
 */
/**
 * Form feedback as a toast (owner request 2026-10-22: every form's feedback message is a toast with a progress bar).
 * Called by a view before the footer prints $page['toast']; the last message wins (a form message replaces the welcome).
 * Field errors stay next to their fields (they must remain visible while the customer corrects them).
 *
 * @param array<string,mixed> $page
 */
function public_toast(array &$page, string $type, string $message): void
{
    if (trim($message) === '') {
        return;
    }
    $page['toast'] = ['type' => in_array($type, ['success', 'error', 'info'], true) ? $type : ($type === 'warning' ? 'error' : 'info'), 'text' => $message];
}

function public_alert(string $type, string $message): string
{
    $icons = ['success' => 'check-circle', 'error' => 'alert-circle', 'warning' => 'alert-circle', 'info' => 'info'];
    $labels = ['success' => 'Success', 'error' => 'Error', 'warning' => 'Warning', 'info' => 'Information'];
    if (!isset($icons[$type])) {
        $type = 'info';
    }

    return '<div class="alert alert--' . $type . '" role="' . ($type === 'error' ? 'alert' : 'status') . '">'
        . public_icon($icons[$type]) . '<p><span class="sr-only">' . $labels[$type] . ': </span>' . e($message) . '</p></div>';
}

/**
 * Empty state. $action: ['label' => string, 'href' => string] (a link) or null.
 */
function public_empty_state(string $title, string $text, ?array $action = null, string $icon = 'inbox'): string
{
    $html = '<div class="empty-state">' . '<span class="empty-state__icon">' . public_icon($icon) . '</span>'
        . '<h2 class="empty-state__title">' . e($title) . '</h2><p class="empty-state__text">' . e($text) . '</p>';
    if ($action !== null && preg_match('#^/[a-z0-9/-]*$#', (string) ($action['href'] ?? '')) === 1) {
        $html .= '<a class="btn btn--secondary" href="' . e((string) $action['href']) . '">' . e((string) $action['label']) . '</a>';
    }

    return $html . '</div>';
}

/**
 * A responsive <img> for images the site stores (vehicle and team photos). Pass every available
 * size explicitly; nothing is guessed:
 *   $variants = [['path' => 'uploads/vehicles/<hex>-t.webp', 'width' => 640], ['path' => '.../<hex>.webp', 'width' => 1600]]
 * Paths must be exactly the shape the image service stores (uploads/vehicles|team/<32 hex>[-t].webp), so a
 * database value can never point the browser at another file or an outside address.
 *
 * Options: sizes (the CSS width the image occupies, e.g. "(min-width: 960px) 33vw, 100vw"),
 *          width + height (the intrinsic size of the DEFAULT variant: reserves space, no layout jump),
 *          priority (true for the main image above the fold: no lazy loading, high fetch priority),
 *          class, decorative (true = empty alt for purely decorative pictures).
 * Everything below the fold is lazy-loaded and decoded asynchronously.
 *
 * @param list<array{path:string,width:int}> $variants smallest first
 */
function public_image(array $variants, string $alt, int $width, int $height, array $options = []): string
{
    $clean = [];
    foreach ($variants as $variant) {
        $path = (string) ($variant['path'] ?? '');
        $w = (int) ($variant['width'] ?? 0);
        if (preg_match('#^uploads/(?:vehicles|team)/[a-f0-9]{32}(?:-t)?\.webp$#', $path) === 1 && $w > 0) {
            $clean[] = ['url' => '/' . $path, 'width' => $w];
        }
    }
    if ($clean === [] || $width < 1 || $height < 1) {
        return '';
    }
    $default = $clean[array_key_last($clean)];
    $srcset = implode(', ', array_map(static fn (array $v): string => $v['url'] . ' ' . $v['width'] . 'w', $clean));
    $priority = !empty($options['priority']);
    $sizes = isset($options['sizes']) ? trim((string) $options['sizes']) : '100vw';
    $class = isset($options['class']) ? ' class="' . e((string) $options['class']) . '"' : '';
    $altText = !empty($options['decorative']) ? '' : $alt;

    return '<img' . $class . ' src="' . e($default['url']) . '"'
        . (count($clean) > 1 ? ' srcset="' . e($srcset) . '" sizes="' . e($sizes) . '"' : '')
        . ' width="' . $width . '" height="' . $height . '" alt="' . e($altText) . '"'
        . ($priority ? ' fetchpriority="high" decoding="async"' : ' loading="lazy" decoding="async"') . '>';
}

/**
 * Page header pattern for ordinary pages: a small label, the title (the page's one h1) and an
 * optional short intro. Deliberately modest: the homepage has its own hero treatment later.
 */
function public_page_header(string $title): string
{
    // Full-width banner: a local photograph under a tinted gradient overlay (all in public.css). Place it OUTSIDE the page's .container.
    // The page title only (owner request 2026-10-23): no label above it and no description under it.
    return '<header class="page-banner"><div class="container page-banner__inner">'
        . '<h1 class="page-banner__title">' . e($title) . '</h1>'
        . '</div></header>';
}

/**
 * Preload links for the first hero slide only (the largest above-the-fold image), one per
 * layout so a phone never downloads the desktop crop. The values come from HomepageHeroService.
 *
 * @param array<string,mixed> $slide a slide from HomepageHeroService::slides()
 * @return list<array{media:string,href:string,srcset:?string}>
 */
function public_hero_preloads(array $slide): array
{
    $desktop = $slide['desktop'];
    $srcset = isset($slide['wide']) ? $desktop['url'] . ' ' . $desktop['width'] . 'w, ' . $slide['wide']['url'] . ' ' . $slide['wide']['width'] . 'w' : null;

    return [
        ['media' => '(max-width: 767.98px)', 'href' => $slide['mobile']['url'], 'srcset' => null],
        ['media' => '(min-width: 768px)', 'href' => $desktop['url'], 'srcset' => $srcset],
    ];
}

/**
 * The logo image for the header and the mobile menu. Save the real logo as assets/images/logo.svg (preferred:
 * sharp at every size) or logo.webp / logo.png and nothing else needs to change; the first file found in
 * that order is used. The image is drawn at a fixed height with its own natural width (never stretched).
 * A neutral, EMPTY placeholder logo.svg ships with the project so the slot has its place and the header has no
 * broken image; it contains no drawing and no text and must be replaced by the real logo.
 *
 * @return array{url:string,width:int,height:int}|null null when no logo file exists at all
 */
function public_logo(int $height = 40): ?array
{
    $dir = dirname(__DIR__, 2) . '/assets/images/';
    foreach (['svg' => null, 'webp' => IMAGETYPE_WEBP, 'png' => IMAGETYPE_PNG] as $extension => $type) {
        $file = $dir . 'logo.' . $extension;
        if (!is_file($file)) {
            continue;
        }
        $width = $ratioHeight = 0;
        if ($extension === 'svg') {
            // an SVG has no pixel size: read width/height (or the viewBox) from its opening tag
            $head = (string) file_get_contents($file, false, null, 0, 4096);
            if (preg_match('/<svg\b[^>]*>/is', $head, $tag) !== 1) {
                continue;
            }
            if (preg_match('/\bwidth="([0-9.]+)(?:px)?"/i', $tag[0], $w) === 1 && preg_match('/\bheight="([0-9.]+)(?:px)?"/i', $tag[0], $h) === 1) {
                $width = (float) $w[1];
                $ratioHeight = (float) $h[1];
            } elseif (preg_match('/viewBox="[0-9.\-]+[ ,]+[0-9.\-]+[ ,]+([0-9.]+)[ ,]+([0-9.]+)"/i', $tag[0], $vb) === 1) {
                $width = (float) $vb[1];
                $ratioHeight = (float) $vb[2];
            }
        } else {
            $info = @getimagesize($file);
            if ($info === false || $info[2] !== $type) {
                continue;
            }
            [$width, $ratioHeight] = [(float) $info[0], (float) $info[1]];
        }
        if ($width <= 0 || $ratioHeight <= 0) {
            continue;
        }

        return ['url' => '/assets/images/logo.' . $extension . '?v=' . (string) filemtime($file), 'width' => (int) round($width * $height / $ratioHeight), 'height' => $height];
    }

    return null;
}

/** False while the only logo file is the project's EMPTY placeholder logo.svg (it contains the word PLACEHOLDER and draws nothing). */
function public_has_real_logo(): bool
{
    $dir = dirname(__DIR__, 2) . '/assets/images/';
    if (is_file($dir . 'logo.webp') || is_file($dir . 'logo.png')) {
        return true;
    }

    return is_file($dir . 'logo.svg') && !str_contains((string) file_get_contents($dir . 'logo.svg', false, null, 0, 600), 'PLACEHOLDER');
}

/** The logo as an <img> (or the business name as text when there is no real logo yet). Used in the header and the mobile menu. */
function public_logo_markup(int $height = 40): string
{
    $logo = public_logo($height);
    if ($logo === null || !public_has_real_logo()) {
        // No real logo yet: the business name as text, never an empty slot. It is replaced by the logo as soon as a real file exists.
        return '<span class="site-logo__text">' . e(PUBLIC_SITE_NAME) . '</span>';
    }

    return '<img src="' . e($logo['url']) . '" width="' . $logo['width'] . '" height="' . $logo['height'] . '" alt="' . e(PUBLIC_SITE_NAME) . '" decoding="async">';
}

/**
 * "Continue with Google" button (spec 13). A plain link to /auth/google/ (the server starts the OAuth flow), so it
 * works without JavaScript and needs no inline script. The Google "G" keeps its four official colours, as Google's
 * branding rules require; it is the only coloured mark on the page.
 */
function public_google_button(string $label): string
{
    $mark = '<svg class="auth-google__mark" viewBox="0 0 48 48" width="20" height="20" aria-hidden="true" focusable="false">'
        . '<path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>'
        . '<path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>'
        . '<path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>'
        . '<path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>'
        . '</svg>';

    return '<a class="auth-google" href="/auth/google/">' . $mark . '<span>' . e($label) . '</span></a>';
}

/**
 * One labelled field of a public enquiry form (vehicle page and Contact page, specs 14 and 17), with its hint and error
 * linked by aria-describedby. $type: text | email | tel | textarea | select ($options = value => label, with a "" prompt).
 * Every value is escaped here.
 *
 * @param array<string,string> $options
 */
function public_enquiry_field(string $form, string $name, string $label, string $type, bool $required, string $autocomplete,
    string $value, ?string $error, string $hint = '', array $options = []): string
{
    $id = $form . '-' . $name;
    $described = trim(($hint !== '' ? $id . '-hint ' : '') . ($error !== null ? $id . '-error' : ''));
    $max = ['email' => EnquiryValidator::MAX_EMAIL, 'phone' => EnquiryValidator::MAX_PHONE, 'name' => EnquiryValidator::MAX_NAME][$name] ?? null;
    $attrs = ' id="' . $id . '" name="' . e($name) . '"' . ($required ? ' required' : '') . ($autocomplete !== '' ? ' autocomplete="' . e($autocomplete) . '"' : '')
        . ($described !== '' ? ' aria-describedby="' . $described . '"' : '') . ($error !== null ? ' aria-invalid="true"' : '');

    if ($type === 'textarea') {
        $control = '<textarea' . $attrs . ' rows="5" maxlength="' . EnquiryValidator::MAX_MESSAGE . '">' . e($value) . '</textarea>';
    } elseif ($type === 'select') {
        $control = '<select' . $attrs . '>';
        foreach ($options as $key => $text) {
            $control .= '<option value="' . e((string) $key) . '"' . ((string) $key === $value ? ' selected' : '') . '>' . e($text) . '</option>';
        }
        $control .= '</select>';
    } else {
        $control = '<input type="' . e($type) . '"' . $attrs . ' value="' . e($value) . '"' . ($max !== null ? ' maxlength="' . $max . '"' : '') . '>';
    }

    return '<div class="field' . ($error !== null ? ' field--error' : '') . '">'
        . '<label for="' . $id . '">' . e($label) . ($required ? '' : ' <span class="field__optional">(optional)</span>') . '</label>'
        . $control
        . ($hint !== '' ? '<p class="field__hint" id="' . $id . '-hint">' . e($hint) . '</p>' : '')
        . ($error !== null ? '<p class="field__error" id="' . $id . '-error">' . e($error) . '</p>' : '')
        . '</div>';
}

/** The hidden parts of a public enquiry form: which form, the session-free CSRF token and the honeypot (hidden from people). */
function public_enquiry_hidden(string $form, string $kind, string $token): string
{
    return '<input type="hidden" name="kind" value="' . e($kind) . '">'
        . '<input type="hidden" name="' . PublicFormToken::FIELD . '" value="' . e($token) . '">'
        . '<div class="form-hp" aria-hidden="true"><label for="' . e($form) . '-website">Leave this field empty</label>'
        . '<input type="text" id="' . e($form) . '-website" name="' . PublicEnquiryService::HONEYPOT . '" tabindex="-1" autocomplete="off"></div>';
}
