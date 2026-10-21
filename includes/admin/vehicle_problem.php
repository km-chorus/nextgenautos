<?php

declare(strict_types=1);

/**
 * Full-page state for a Vehicle Management request that cannot continue:
 * access denied, vehicle not found, request not verified (CSRF), or an
 * unexpected error. Presentation only; the messages are fixed sentences from
 * VehicleController, never browser input. Renders the page and stops the script.
 *
 * Expected variables: array $view (VehicleController view model with 'problem',
 * 'http', 'message'), array $admin.
 */

require_once dirname(__DIR__, 2) . '/app/helpers/admin_ui.php';

$problemTitles = [
    'forbidden' => ['Access denied', 'alert-circle'],
    'not_found' => ['Vehicle not found', 'car'],
    'csrf'      => ['Request could not be verified', 'alert-circle'],
    'error'     => ['Something went wrong', 'alert-circle'],
];
[$problemTitle, $problemIcon] = $problemTitles[$view['problem']] ?? $problemTitles['error'];
if (isset($view['title']) && is_string($view['title'])) {
    $problemTitle = $view['title'];      // a fixed title chosen by the controller (for example "Image not found")
}

http_response_code((int) $view['http']);
$pageTitle   = 'Vehicles';
$activeNav   = 'vehicles';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Vehicles', 'vehicles.php'], [$problemTitle, null]];

require dirname(__DIR__, 2) . '/includes/admin/shell_start.php';
?>
<section class="card card--flush" aria-labelledby="problem-title">
    <h2 class="sr-only" id="problem-title"><?= e($problemTitle) ?></h2>
    <?= admin_empty_state($problemTitle, (string) $view['message'], ['label' => 'Back to Vehicles', 'href' => 'vehicles.php'], $problemIcon) ?>
    <?php if (isset($view['detail']) && is_string($view['detail']) && $view['detail'] !== ''): ?>
        <p class="hint dev-detail"><strong>Development detail (not shown in production):</strong> <?= e($view['detail']) ?></p>
    <?php endif; ?>
</section>
<?php
require dirname(__DIR__, 2) . '/includes/admin/shell_end.php';
exit;
