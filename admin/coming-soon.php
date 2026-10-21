<?php

declare(strict_types=1);

/**
 * Controlled placeholder for modules that are built by later specifications.
 *
 * The module name comes from the URL (untrusted): it is only ever compared
 * against the navigation allow-list and never printed or used as a path.
 * Unknown values get a 404. No module functionality lives here.
 * Protected: requireAdmin() runs first, so signed-out visitors learn nothing.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';

$admin = requireAdmin();

$requested = $_GET['module'] ?? null;
$module = null;
if (is_string($requested)) {
    foreach (admin_nav_items() as $item) {
        if ($item['placeholder'] && $item['key'] === $requested) {
            $module = $item;
            break;
        }
    }
}

if ($module === null) {
    http_response_code(404);
}

$pageTitle   = $module['label'] ?? 'Page not found';
$activeNav   = $module['key'] ?? '';
$breadcrumbs = [['Dashboard', 'dashboard.php'], [$pageTitle, null]];

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<section class="card card--flush" aria-labelledby="coming-soon-title">
    <h2 id="coming-soon-title" class="sr-only"><?= e($pageTitle) ?></h2>
    <?php if ($module !== null): ?>
        <?= admin_empty_state(
            $module['label'] . ' is not available yet',
            'This section will be built in a later step. Nothing is lost: it will appear here when it is ready.',
            ['label' => 'Back to dashboard', 'href' => 'dashboard.php'],
            $module['icon']
        ) ?>
    <?php else: ?>
        <?= admin_empty_state(
            'Page not found',
            'This page does not exist. Use the navigation to return to a section that does.',
            ['label' => 'Back to dashboard', 'href' => 'dashboard.php'],
            'alert-circle'
        ) ?>
    <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
