<?php

declare(strict_types=1);

/**
 * Admin UI presentation helpers (markup only, no business logic, no database).
 * Spec: Context/specs/02_spec_admin.md
 *
 * Pages set $adminRoot to the path from the page to the project root
 * (default '..' for pages in /admin) before calling admin_asset().
 */

require_once __DIR__ . '/html.php';

/** Temporary text-based brand. Replace with the approved logo asset when available. */
const ADMIN_BRAND_NAME = 'Mr. AmponsahAutos';
const ADMIN_BRAND_SUBTITLE = 'Administration';

/**
 * Security headers for admin pages. The CSP allows only same-origin scripts,
 * styles, fonts and images, so inline scripts/styles must never be added.
 */
function admin_send_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; "
        . "font-src 'self'; img-src 'self' data:; connect-src 'none'; form-action 'self'; "
        . "base-uri 'none'; frame-ancestors 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY'); // clickjacking (CSP frame-ancestors above covers modern browsers)
    header('Referrer-Policy: same-origin');
    // Admin pages must not be reusable from the browser cache or Back button after sign-out.
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Robots-Tag: noindex, nofollow');
    // Deliberately deferred: Strict-Transport-Security (only valid once the production
    // domain serves HTTPS; set it at deployment) and Permissions-Policy.
}

/** Opening <html> through </head> for every admin page (shell and login). */
function admin_head(string $pageTitle): string
{
    return '<!DOCTYPE html>' . "\n"
        . '<html lang="en">' . "\n"
        . '<head>' . "\n"
        . '    <meta charset="utf-8">' . "\n"
        . '    <meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
        . '    <meta name="robots" content="noindex, nofollow">' . "\n"
        . '    <title>' . e($pageTitle) . ' | ' . e(ADMIN_BRAND_NAME) . ' Admin</title>' . "\n"
        . '    <link rel="icon" href="data:,">' . "\n" // no favicon asset yet; avoids a 404 request
        . '    <link rel="preload" href="' . admin_asset('fonts/manrope-latin-wght.woff2') . '" as="font" type="font/woff2" crossorigin>' . "\n"
        . '    <link rel="stylesheet" href="' . admin_asset('css/admin.css') . '">' . "\n"
        . '    <script src="' . admin_asset('js/admin.js') . '"></script>' . "\n"
        . '</head>' . "\n";
}

/** URL of a file under /assets, with a modification-time cache-buster. */
function admin_asset(string $path): string
{
    $root = $GLOBALS['adminRoot'] ?? '..';
    $url = $root . '/assets/' . ltrim($path, '/');
    $file = dirname(__DIR__, 2) . '/assets/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '';

    return e($version !== '' ? $url . '?v=' . $version : $url);
}

/** Inline reference to a sprite icon. $name is limited to [a-z-]. */
function admin_icon(string $name, string $class = ''): string
{
    if (preg_match('/^[a-z-]+$/', $name) !== 1) {
        return '';
    }
    $classes = trim('icon ' . $class);
    $href = admin_asset('images/icons.svg') ;

    return '<svg class="' . e($classes) . '" aria-hidden="true" focusable="false">'
        . '<use href="' . $href . '#i-' . $name . '"></use></svg>';
}

/**
 * Link to a module's page. Modules that are built by a later specification
 * all resolve to the single controlled placeholder page (coming-soon.php);
 * when a module is built, only its entry here changes.
 */
function admin_module_href(string $module): string
{
    static $built = ['dashboard' => 'dashboard.php', 'vehicles' => 'vehicles.php', 'team' => 'team.php', 'brands' => 'brands.php', 'parts' => 'parts.php', 'settings' => 'settings.php'];

    return $built[$module] ?? 'coming-soon.php?module=' . rawurlencode($module);
}

/**
 * Sidebar navigation. 'placeholder' is true while the module's page is not
 * built yet (its link opens the controlled "not available yet" page).
 *
 * @return list<array{key:string,label:string,icon:string,href:string,placeholder:bool}>
 */
function admin_nav_items(): array
{
    $items = [
        ['dashboard', 'Dashboard', 'dashboard', false],
        ['vehicles',  'Vehicles',  'car',       false],
        ['parts',     'Parts',     'wrench',    false],
        ['shipments', 'Shipments', 'ship',      true],
        ['enquiries', 'Enquiries', 'message',   true],
        ['team',      'Team',      'users',     false],
        ['brands',    'Brands',    'tag',       false],
        ['content',   'Content',   'file-text', true],
        ['settings',  'Settings',  'settings',  false],
    ];

    return array_map(
        static fn (array $i): array => [
            'key' => $i[0], 'label' => $i[1], 'icon' => $i[2],
            'href' => admin_module_href($i[0]), 'placeholder' => $i[3],
        ],
        $items
    );
}

/**
 * Alert. $type: success | error | warning | info.
 * Errors use role="alert"; the others use role="status".
 * The type is also announced in text, so meaning never depends on color.
 */
function admin_alert(string $type, string $title, string $message = '', bool $dismissible = false): string
{
    $icons = [
        'success' => 'check-circle',
        'error'   => 'alert-circle',
        'warning' => 'alert-triangle',
        'info'    => 'info',
    ];
    $labels = ['success' => 'Success', 'error' => 'Error', 'warning' => 'Warning', 'info' => 'Information'];
    if (!isset($icons[$type])) {
        $type = 'info';
    }

    $html = '<div class="alert alert--' . $type . '" role="' . ($type === 'error' ? 'alert' : 'status') . '">';
    $html .= '<span class="alert__icon">' . admin_icon($icons[$type]) . '</span>';
    $html .= '<div><p class="alert__title"><span class="sr-only">' . $labels[$type] . ': </span>' . e($title) . '</p>';
    if ($message !== '') {
        $html .= '<p class="alert__message">' . e($message) . '</p>';
    }
    $html .= '</div>';
    if ($dismissible) {
        $html .= '<button type="button" class="btn btn--ghost btn--icon alert__dismiss" data-dismiss-alert aria-label="Dismiss ' . strtolower($labels[$type]) . ' message">'
            . admin_icon('x') . '</button>';
    }

    return $html . '</div>';
}

/**
 * Status badge. All status-to-tone decisions live here so badges stay consistent.
 * Unknown statuses fall back to neutral and show their own text.
 */
function admin_status_badge(string $status): string
{
    [$label, $tone] = admin_status_definition($status);

    return '<span class="badge badge--' . $tone . '">' . e($label) . '</span>';
}

/** Display label for a status (same wording as its badge). */
function admin_status_label(string $status): string
{
    return admin_status_definition($status)[0];
}

/**
 * Status key => [label, tone]. Single source of truth for status wording and
 * color. Unknown statuses fall back to neutral and show their own text.
 *
 * @return array{0:string,1:string}
 */
function admin_status_definition(string $status): array
{
    static $map = [
        'AVAILABLE'                      => ['Available', 'success'],
        'RESERVED'                       => ['Reserved', 'warning'],
        'SOLD'                           => ['Sold', 'neutral'],
        'UNAVAILABLE'                    => ['Unavailable', 'neutral'],
        'NOT_YET_SHIPPED'                => ['Not Yet Shipped', 'neutral'],
        'SHIPMENT_BOOKED'                => ['Shipment Booked', 'info'],
        'TRACKING_INFORMATION_AVAILABLE' => ['Tracking Information Available', 'info'],
        'IN_TRANSIT'                     => ['In Transit', 'info'],
        'ARRIVED'                        => ['Arrived', 'success'],
        'DELIVERED'                      => ['Delivered', 'success'],
        'ACTIVE'                         => ['Active', 'success'],
        'INACTIVE'                       => ['Inactive', 'neutral'],
        'PENDING'                        => ['Pending', 'warning'],
        'RESOLVED'                       => ['Resolved', 'success'],
        // Enquiry statuses (database enum: new / in_progress / closed)
        'NEW'                            => ['New', 'warning'],
        'IN_PROGRESS'                    => ['In Progress', 'info'],
        'CLOSED'                         => ['Closed', 'neutral'],
        // Vehicle publication (is_published)
        'PUBLISHED'                      => ['Published', 'success'],
        'UNPUBLISHED'                    => ['Unpublished', 'neutral'],
        // Vehicle images
        'PRIMARY'                        => ['Primary', 'success'],
        'GALLERY'                        => ['Gallery', 'neutral'],
        'PRIMARY_SET'                    => ['Primary image set', 'success'],
        'PRIMARY_NOT_SET'                => ['Primary image not set', 'warning'],
        // Security settings
        'ENABLED'                        => ['Enabled', 'success'],
        'NOT_ENABLED'                    => ['Not Enabled', 'neutral'],
    ];

    $key = strtoupper(str_replace([' ', '-'], '_', trim($status)));

    return $map[$key] ?? [$status, 'neutral'];
}

/** Statuses known to the badge system (for the component library page). */
function admin_status_keys(): array
{
    return [
        'AVAILABLE', 'RESERVED', 'SOLD', 'NOT_YET_SHIPPED', 'SHIPMENT_BOOKED',
        'IN_TRANSIT', 'ARRIVED', 'DELIVERED', 'ACTIVE', 'INACTIVE', 'PENDING', 'RESOLVED',
    ];
}

/**
 * Empty state. $action: ['label' => string, 'icon' => ?string, 'href' => ?string, 'disabled' => bool].
 * With 'href' the action is a link; otherwise a button that is disabled
 * unless 'disabled' is false.
 */
function admin_empty_state(string $title, string $text, ?array $action = null, string $icon = 'inbox'): string
{
    $html = '<div class="empty-state">';
    $html .= '<span class="empty-state__icon">' . admin_icon($icon, 'icon--lg') . '</span>';
    $html .= '<h3 class="empty-state__title">' . e($title) . '</h3>';
    $html .= '<p class="empty-state__text">' . e($text) . '</p>';
    if ($action !== null) {
        $inner = (isset($action['icon']) ? admin_icon((string) $action['icon']) : '') . e((string) $action['label']);
        if (isset($action['href'])) {
            $html .= '<a class="btn btn--primary" href="' . e((string) $action['href']) . '">' . $inner . '</a>';
        } else {
            $disabled = ($action['disabled'] ?? true) ? ' disabled' : '';
            $html .= '<button type="button" class="btn btn--primary"' . $disabled . '>' . $inner . '</button>';
        }
    }

    return $html . '</div>';
}

/** Table skeleton for loading states. */
function admin_skeleton_rows(int $rows = 4): string
{
    $html = '<div class="skeleton-rows" aria-busy="true"><span class="sr-only">Loading</span>';
    for ($i = 0; $i < $rows; $i++) {
        $html .= '<div class="skeleton-row" aria-hidden="true">'
            . '<span class="skeleton"></span><span class="skeleton"></span><span class="skeleton skeleton--short"></span></div>';
    }

    return $html . '</div>';
}

/* ---------------------------------------------------------------------------
 * Dashboard display helpers (formatting only; values are escaped by callers
 * with e(), or returned already escaped where noted).
 * ------------------------------------------------------------------------ */

/** 1234 => "1,234". */
function admin_format_count(int $value): string
{
    return number_format($value);
}

/** DECIMAL string + ISO currency code => "GHS 1,234.50" (plain text, not escaped). */
function admin_format_price(string $amount, string $currency): string
{
    return strtoupper(trim($currency)) . ' ' . number_format((float) $amount, 2);
}

/** UTC "Y-m-d H:i:s" => "7 Oct 2026" (plain text); empty string if unparseable. */
function admin_format_date(string $utcDateTime): string
{
    try {
        return (new DateTimeImmutable($utcDateTime, new DateTimeZone('UTC')))->format('j M Y');
    } catch (Exception $e) {
        return '';
    }
}

/** UTC "Y-m-d H:i:s" => "7 Oct 2026, 14:05 UTC" (plain text); empty string if unparseable. */
function admin_format_datetime(string $utcDateTime): string
{
    try {
        return (new DateTimeImmutable($utcDateTime, new DateTimeZone('UTC')))->format('j M Y, H:i') . ' UTC';
    } catch (Exception $e) {
        return '';
    }
}

/** "2020 Toyota Camry" (plain text, not escaped). */
function admin_vehicle_title(mixed $year, ?string $brand, ?string $model): string
{
    return trim(implode(' ', array_filter([(string) $year, (string) $brand, (string) $model], 'strlen')));
}

/** "2024 Toyota Camry XSE": year, brand, model and trim when present (plain text, not escaped). */
function admin_vehicle_label(mixed $year, ?string $brand, ?string $model, ?string $trim): string
{
    return trim(implode(' ', array_filter([(string) $year, (string) $brand, (string) $model, (string) $trim], 'strlen')));
}

/** Customer-facing wording for an enquiry type (plain text). */
function admin_enquiry_type_label(string $type): string
{
    return [
        'vehicle'      => 'Vehicle enquiry',
        'availability' => 'Availability question',
        'purchase'     => 'Reservation request',   // spec 14: the vehicle page's Reserve form
        'shipping'     => 'Shipping question',
        'general'      => 'General enquiry',
        'part'         => 'Part enquiry',
    ][$type] ?? 'Enquiry';
}

/**
 * Safe, escaped URL for a stored vehicle image, or null.
 * The stored path must be a plain filename directly inside uploads/vehicles/;
 * anything else (traversal, other folders, schemes) is rejected so a bad
 * database value can never point the browser at an arbitrary file or URL.
 */
function admin_vehicle_image_url(?string $path): ?string
{
    if ($path === null || preg_match('#^uploads/vehicles/[A-Za-z0-9][A-Za-z0-9._-]*$#', $path) !== 1
        || str_contains($path, '..')) {
        return null;
    }

    return e(($GLOBALS['adminRoot'] ?? '..') . '/' . $path);
}

/**
 * Safe, escaped URL of a team member's stored photo (or its thumbnail), or null.
 * Only the exact shape the image service generates is accepted (uploads/team/<32 hex>.webp), so a
 * bad database value can never point the browser at another file or an outside address.
 */
function admin_team_photo_url(?string $path, bool $thumbnail = false): ?string
{
    if ($path === null || preg_match('#^uploads/team/[a-f0-9]{32}\.webp$#', $path) !== 1) {
        return null;
    }
    if ($thumbnail) {
        $path = preg_replace('/\.webp$/', '-t.webp', $path) ?? $path;
    }

    return e(($GLOBALS['adminRoot'] ?? '..') . '/' . $path);
}
/**
 * Safe, escaped URL of a part photo's THUMBNAIL (spec 16), or null. Only the exact shape the image service generates is
 * accepted (uploads/parts/<32 hex>.webp), so a bad database value can never point the browser elsewhere.
 */
function admin_part_image_url(?string $path): ?string
{
    if ($path === null || preg_match('#^uploads/parts/([a-f0-9]{32})\.webp$#', $path, $m) !== 1) {
        return null;
    }

    return e(($GLOBALS['adminRoot'] ?? '..') . '/uploads/parts/' . $m[1] . '-t.webp');
}
