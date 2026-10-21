<?php

declare(strict_types=1);

/**
 * Full-page state for a Brand Management request that cannot continue:
 * access denied, brand not found, request not verified (CSRF), or an
 * unexpected error. Presentation only; the messages are fixed sentences from
 * BrandController, never browser input. Renders the page and stops the script.
 *
 * Expected variables: array $view (BrandController view model with 'problem',
 * 'http', 'message'), array $admin.
 */

require_once dirname(__DIR__, 2) . '/app/helpers/admin_ui.php';

$problemTitles = [
    'forbidden' => ['Access denied', 'alert-circle'],
    'not_found' => ['Brand not found', 'tag'],
    'csrf'      => ['Request could not be verified', 'alert-circle'],
    'error'     => ['Something went wrong', 'alert-circle'],
];
[$problemTitle, $problemIcon] = $problemTitles[$view['problem']] ?? $problemTitles['error'];

http_response_code((int) $view['http']);
$pageTitle   = 'Brands';
$activeNav   = 'brands';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Brands', 'brands.php'], [$problemTitle, null]];

require dirname(__DIR__, 2) . '/includes/admin/shell_start.php';
?>
<section class="card card--flush" aria-labelledby="problem-title">
    <h2 class="sr-only" id="problem-title"><?= e($problemTitle) ?></h2>
    <?= admin_empty_state($problemTitle, (string) $view['message'], ['label' => 'Back to Brands', 'href' => 'brands.php'], $problemIcon) ?>
</section>
<?php
require dirname(__DIR__, 2) . '/includes/admin/shell_end.php';
exit;
