<?php

declare(strict_types=1);

/**
 * Admin dashboard (spec 03_spec_dashboard.md).
 *
 * Presentation only: data comes from DashboardController -> DashboardService
 * -> DashboardRepository -> PDO. No SQL and no business logic here.
 * Protected: requireAdmin() runs before anything is read or rendered.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/DashboardController.php';
require_once dirname(__DIR__) . '/app/helpers/admin_ui.php';

$admin = requireAdmin();

$view = (new DashboardController())->index();

$pageTitle   = 'Dashboard';
$activeNav   = 'dashboard';
$breadcrumbs = []; // top-level page: a breadcrumb would only repeat the heading

/** Shown inside a section when its data could not be loaded. */
$sectionError = admin_alert('error', "We couldn't load this information right now.", 'Please try again.');

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<div class="dashboard">

    <!-- Overview statistics -->
    <section aria-labelledby="overview-heading">
        <h2 id="overview-heading" class="sr-only">Overview</h2>
        <?php if ($view['stats']['error']): ?>
            <?= $sectionError ?>
        <?php else: ?>
            <?php
            $stats = $view['stats']['data'];
            $cards = [
                ['Total vehicles',     $stats['total_vehicles'],     'car',          'Archived vehicles are not counted.'],
                ['Available vehicles', $stats['available_vehicles'], 'check-circle', ''],
                ['Total shipments',    $stats['total_shipments'],    'ship',         ''],
                ['New enquiries',      $stats['new_enquiries'],      'message',      ''],
            ];
            ?>
            <div class="stat-grid">
                <?php foreach ($cards as [$label, $value, $icon, $note]): ?>
                    <div class="stat">
                        <div class="stat__head">
                            <p class="stat__label"><?= e($label) ?></p>
                            <?= admin_icon($icon) ?>
                        </div>
                        <p class="stat__value"><?= e(admin_format_count($value)) ?></p>
                        <?php if ($note !== ''): ?><p class="stat__note"><?= e($note) ?></p><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="dashboard-layout">
        <div class="dashboard-main">

            <!-- Recent vehicles -->
            <section class="card card--flush" aria-labelledby="recent-vehicles-heading">
                <div class="card__header">
                    <h2 class="card__heading" id="recent-vehicles-heading">Recent vehicles</h2>
                    <a class="card__link" href="<?= e(admin_module_href('vehicles')) ?>">View all vehicles</a>
                </div>
                <?php if ($view['vehicles']['error']): ?>
                    <div class="card__body"><?= $sectionError ?></div>
                <?php elseif ($view['vehicles']['data'] === []): ?>
                    <?= admin_empty_state(
                        'No vehicles yet',
                        'Add your first vehicle to start building the inventory.',
                        ['label' => 'Add Vehicle', 'icon' => 'plus', 'href' => admin_module_href('vehicles')],
                        'car'
                    ) ?>
                <?php else: ?>
                    <div class="table-wrap" role="region" aria-labelledby="recent-vehicles-heading" tabindex="0">
                        <table class="table table--stack">
                            <thead>
                                <tr>
                                    <th scope="col">Vehicle</th>
                                    <th scope="col">Price</th>
                                    <th scope="col">Availability</th>
                                    <th scope="col" class="col-actions"><span class="sr-only">Action</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($view['vehicles']['data'] as $vehicle): ?>
                                    <?php
                                    $title    = admin_vehicle_title($vehicle['year'], $vehicle['brand_name'], $vehicle['model']);
                                    $imageUrl = admin_vehicle_image_url($vehicle['image_path']);
                                    $imageAlt = trim((string) $vehicle['image_alt']) !== '' ? (string) $vehicle['image_alt'] : $title;
                                    ?>
                                    <tr>
                                        <td class="cell-primary">
                                            <div class="vehicle-cell">
                                                <?php if ($imageUrl !== null): ?>
                                                    <img class="thumb" src="<?= $imageUrl ?>" alt="<?= e($imageAlt) ?>" width="64" height="44" loading="lazy">
                                                <?php else: ?>
                                                    <span class="thumb thumb--empty"><?= admin_icon('car') ?><span class="sr-only">No photo available</span></span>
                                                <?php endif; ?>
                                                <div class="cell-text">
                                                    <p class="cell-title"><?= e($title) ?></p>
                                                    <?php if (trim((string) $vehicle['trim']) !== ''): ?>
                                                        <p class="cell-sub"><?= e((string) $vehicle['trim']) ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td data-label="Price" class="nowrap"><?= e(admin_format_price((string) $vehicle['price'], (string) $vehicle['currency'])) ?></td>
                                        <td data-label="Availability"><?= admin_status_badge((string) $vehicle['availability_status']) ?></td>
                                        <td class="col-actions">
                                            <a class="btn btn--secondary btn--sm" href="<?= e(admin_module_href('vehicles')) ?>">View<span class="sr-only"> <?= e($title) ?></span></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Recent enquiries -->
            <section class="card card--flush" aria-labelledby="recent-enquiries-heading">
                <div class="card__header">
                    <h2 class="card__heading" id="recent-enquiries-heading">Recent enquiries</h2>
                    <a class="card__link" href="<?= e(admin_module_href('enquiries')) ?>">View all enquiries</a>
                </div>
                <?php if ($view['enquiries']['error']): ?>
                    <div class="card__body"><?= $sectionError ?></div>
                <?php elseif ($view['enquiries']['data'] === []): ?>
                    <?= admin_empty_state(
                        'No enquiries yet',
                        'No enquiries have been received yet. Customer enquiries will appear here.',
                        null,
                        'message'
                    ) ?>
                <?php else: ?>
                    <div class="table-wrap" role="region" aria-labelledby="recent-enquiries-heading" tabindex="0">
                        <table class="table table--stack">
                            <thead>
                                <tr>
                                    <th scope="col">Customer</th>
                                    <th scope="col">Vehicle / subject</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="col-actions"><span class="sr-only">Action</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($view['enquiries']['data'] as $enquiry): ?>
                                    <?php
                                    $subject = $enquiry['vehicle_model'] !== null
                                        ? admin_vehicle_title($enquiry['vehicle_year'], $enquiry['brand_name'], $enquiry['vehicle_model'])
                                        : admin_enquiry_type_label((string) $enquiry['enquiry_type']);
                                    $date = admin_format_date((string) $enquiry['created_at']);
                                    ?>
                                    <tr>
                                        <td class="cell-primary"><div class="cell-text"><p class="cell-title"><?= e((string) $enquiry['customer_name']) ?></p></div></td>
                                        <td data-label="Vehicle / subject"><div class="cell-text"><?= e($subject) ?></div></td>
                                        <td data-label="Date" class="nowrap"><time datetime="<?= e(str_replace(' ', 'T', (string) $enquiry['created_at'])) ?>Z"><?= e($date) ?></time></td>
                                        <td data-label="Status"><?= admin_status_badge((string) $enquiry['status']) ?></td>
                                        <td class="col-actions">
                                            <a class="btn btn--secondary btn--sm" href="<?= e(admin_module_href('enquiries')) ?>">View<span class="sr-only"> enquiry from <?= e((string) $enquiry['customer_name']) ?></span></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <div class="dashboard-side">

            <!-- Shipment overview -->
            <section class="card card--flush" aria-labelledby="shipments-heading">
                <div class="card__header">
                    <h2 class="card__heading" id="shipments-heading">Shipment overview</h2>
                    <a class="card__link" href="<?= e(admin_module_href('shipments')) ?>">View all shipments</a>
                </div>
                <?php if ($view['shipments']['error']): ?>
                    <div class="card__body"><?= $sectionError ?></div>
                <?php elseif ($view['shipments']['data']['total'] === 0): ?>
                    <?= admin_empty_state(
                        'No shipments yet',
                        'No shipments have been created yet.',
                        ['label' => 'Add Shipment', 'icon' => 'plus', 'href' => admin_module_href('shipments')],
                        'ship'
                    ) ?>
                <?php else: ?>
                    <dl class="summary-list">
                        <?php foreach ($view['shipments']['data']['by_status'] as $status => $count): ?>
                            <div class="summary-row">
                                <dt><?= e(admin_status_label($status)) ?></dt>
                                <dd><?= e(admin_format_count($count)) ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                <?php endif; ?>
            </section>

            <!-- Quick actions -->
            <section class="card" aria-labelledby="quick-actions-heading">
                <h2 class="card__title" id="quick-actions-heading">Quick actions</h2>
                <ul class="quick-actions">
                    <li><a class="btn btn--secondary" href="<?= e(admin_module_href('vehicles')) ?>"><?= admin_icon('plus') ?>Add Vehicle</a></li>
                    <li><a class="btn btn--secondary" href="<?= e(admin_module_href('shipments')) ?>"><?= admin_icon('plus') ?>Add Shipment</a></li>
                    <li><a class="btn btn--secondary" href="<?= e(admin_module_href('enquiries')) ?>"><?= admin_icon('message') ?>View Enquiries</a></li>
                    <li><a class="btn btn--secondary" href="<?= e(admin_module_href('team')) ?>"><?= admin_icon('users') ?>Manage Team</a></li>
                </ul>
            </section>
        </div>
    </div>
</div>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
