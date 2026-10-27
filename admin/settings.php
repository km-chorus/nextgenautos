<?php

declare(strict_types=1);

/**
 * Settings (spec 17): the company contact details and social media links shown on the public Contact page, header and
 * vehicle pages. GET shows the form; POST saves through SettingsController / SettingsService, then redirects
 * (Post/Redirect/Get). Protected: requireAdmin() runs first.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/SettingsController.php';

$admin = requireAdmin();

$view = (new SettingsController())->edit($admin, $_SERVER['REQUEST_METHOD'] ?? 'GET', $_POST, admin_client_ip(), admin_user_agent());

if (isset($view['redirect'])) {
    admin_redirect($view['redirect'], 303);
}
http_response_code((int) $view['http']);

$pageTitle   = 'Settings';
$activeNav   = 'settings';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Settings', null]];

require dirname(__DIR__) . '/includes/admin/shell_start.php';

if (in_array($view['problem'], ['forbidden', 'error'], true)): ?>
<section class="card card--flush" aria-labelledby="problem-title">
    <h2 class="sr-only" id="problem-title"><?= $view['problem'] === 'forbidden' ? 'Access denied' : 'Something went wrong' ?></h2>
    <?= admin_empty_state($view['problem'] === 'forbidden' ? 'Access denied' : 'Something went wrong', (string) $view['message'], ['label' => 'Back to Dashboard', 'href' => 'dashboard.php'], 'alert-circle') ?>
</section>
<?php else:
    $values = $view['values'];
    $errors = $view['errors'];
    $formMessage = $view['formMessage'];
    $flash = $view['flash'] ?? null;
    $accidentEnabled = !empty($view['accidentEnabled']);
?>
<div class="stack stack--lg">
    <div class="page-header">
        <p class="page-header__text">These details appear on the public website. Leave a field empty to hide it.</p>
    </div>
<?php if ($flash !== null): ?>
    <?= admin_alert($flash['type'], $flash['text'], '', true) ?>
<?php endif; ?>
    <?php require dirname(__DIR__) . '/includes/admin/settings_form.php'; ?>
</div>
<?php endif;
require dirname(__DIR__) . '/includes/admin/shell_end.php';
