<?php

declare(strict_types=1);

/**
 * Shipment page (spec 20): update status, container number and dates, optionally email the customer, and see the
 * history (from the audit log). POST saves (Post/Redirect/Get). Protected: requireAdmin() runs first.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/ShipmentController.php';

$admin = requireAdmin();

$view = (new ShipmentController())->edit($admin, $_GET['id'] ?? null, $_SERVER['REQUEST_METHOD'] ?? 'GET', $_POST, admin_client_ip(), admin_user_agent());
if (isset($view['redirect'])) {
    admin_redirect($view['redirect'], 303);
}
if ($view['problem'] !== null && !($view['problem'] === 'csrf' && isset($view['values']))) {
    require dirname(__DIR__) . '/includes/admin/shipment_problem.php';
}
http_response_code((int) $view['http']);

$shipment         = $view['shipment'];
$shipmentId       = (int) $shipment['id'];
$pageTitle        = 'Shipment';
$activeNav        = 'shipments';
$breadcrumbs      = [['Dashboard', 'dashboard.php'], ['Shipments', 'shipments.php'], [ShipmentEmail::vehicleLabel($shipment), null]];
$formAction       = 'shipment-edit.php?id=' . $shipmentId;
$values           = $view['values'];
$errors           = $view['errors'];
$vehicleChoices   = $view['vehicleChoices'];
$notifyChecked    = $view['notifyChecked'] ?? false;
$needsBackConfirm = $view['needsBackConfirm'] ?? false;
$submitLabel      = 'Save update';
$cancelHref       = 'shipments.php';
$formMessage      = $view['problem'] === 'csrf' ? (string) $view['message'] : ($view['formMessage'] ?? null);
$flash            = $view['flash'] ?? null;
$current          = ShipmentValidator::position((string) $shipment['status']);

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<div class="stack stack--lg">
    <?php if ($flash !== null): ?>
        <?= admin_alert($flash['type'], $flash['text'], '', true) ?>
    <?php endif; ?>

    <section class="card" aria-labelledby="progress-title">
        <h2 class="card__title" id="progress-title">Progress</h2>
        <ol class="ship-steps">
            <?php foreach (array_values(ShipmentValidator::STATUSES) as $i => $label): ?>
                <li class="ship-steps__step<?= $i < $current ? ' is-done' : ($i === $current ? ' is-current' : '') ?>"<?= $i === $current ? ' aria-current="step"' : '' ?>><span class="ship-steps__dot" aria-hidden="true"></span><span><?= e($label) ?></span></li>
            <?php endforeach; ?>
        </ol>
    </section>

    <div class="ship-layout">
        <div><?php require dirname(__DIR__) . '/includes/admin/shipment_form.php'; ?></div>
        <aside class="stack">
            <section class="card" aria-labelledby="history-title">
                <h2 class="card__title" id="history-title">History</h2>
                <?php if ($shipment['history'] === []): ?>
                    <p class="hint">No changes recorded yet.</p>
                <?php else: ?>
                    <ol class="ship-history">
                        <?php foreach ($shipment['history'] as $h): ?>
                            <li><time datetime="<?= e(str_replace(' ', 'T', (string) $h['created_at'])) ?>Z"><?= e(admin_format_datetime((string) $h['created_at'])) ?></time><span><?= e((string) $h['description']) ?></span></li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>
            <?php if (ShipmentService::canDelete($shipment)): ?>
                <a class="btn btn--ghost btn--text-danger" href="shipment-delete.php?id=<?= $shipmentId ?>">Delete this shipment</a>
            <?php endif; ?>
        </aside>
    </div>
</div>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
