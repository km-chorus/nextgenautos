<?php

declare(strict_types=1);

/**
 * Full-page state for a shipment request that cannot continue: access denied, shipment
 * not found, request not verified (CSRF), or an unexpected error. Presentation only; the
 * messages are fixed sentences from ShipmentController, never browser input. In development only,
 * an extra one-line detail (paths removed) is shown. Renders the page and stops the script.
 *
 * Expected variables: array $view (ShipmentController view model with 'problem', 'http', 'message'), array $admin.
 */

require_once dirname(__DIR__, 2) . '/app/helpers/admin_ui.php';

$problemTitles = [
    'forbidden' => ['Access denied', 'alert-circle'],
    'not_found' => ['Shipment not found', 'ship'],
    'csrf'      => ['Request could not be verified', 'alert-circle'],
    'error'     => ['Something went wrong', 'alert-circle'],
];
[$problemTitle, $problemIcon] = $problemTitles[$view['problem']] ?? $problemTitles['error'];

http_response_code((int) $view['http']);
$pageTitle   = 'Shipments';
$activeNav   = 'shipments';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Shipments', 'shipments.php'], [$problemTitle, null]];

require dirname(__DIR__, 2) . '/includes/admin/shell_start.php';
?>
<section class="card card--flush" aria-labelledby="problem-title">
    <h2 class="sr-only" id="problem-title"><?= e($problemTitle) ?></h2>
    <?= admin_empty_state($problemTitle, (string) $view['message'], ['label' => 'Back to Shipments', 'href' => 'shipments.php'], $problemIcon) ?>
    <?php if (isset($view['detail']) && is_string($view['detail']) && $view['detail'] !== ''): ?>
        <p class="hint dev-detail"><strong>Development detail (not shown in production):</strong> <?= e($view['detail']) ?></p>
    <?php endif; ?>
</section>
<?php
require dirname(__DIR__, 2) . '/includes/admin/shell_end.php';
exit;
