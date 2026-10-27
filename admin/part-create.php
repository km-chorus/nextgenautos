<?php

declare(strict_types=1);

/**
 * Add Part (spec 16, phase 1). GET shows the form; POST creates the part through PartController -> PartService, then
 * redirects to its Edit page (Post/Redirect/Get). Protected: requireAdmin() runs first.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/PartController.php';
require_once dirname(__DIR__) . '/app/controllers/TeamController.php';   // TeamController::photoLimits(): the shared upload limits

$admin = requireAdmin();

$view = (new PartController())->create($admin, $_SERVER['REQUEST_METHOD'] ?? 'GET', $_POST, $_FILES, $_SERVER, admin_client_ip(), admin_user_agent());

if (isset($view['redirect'])) {
    admin_redirect($view['redirect'], 303);
}
// A failed CSRF check keeps the typed values on screen; any other problem has its own page.
if ($view['problem'] !== null && !($view['problem'] === 'csrf' && isset($view['values']))) {
    require dirname(__DIR__) . '/includes/admin/part_problem.php';
}
http_response_code((int) $view['http']);

$pageTitle   = 'Add Part';
$activeNav   = 'parts';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Parts', 'parts.php'], ['Add Part', null]];

$formAction      = 'part-create.php';
$values          = $view['values'];
$errors          = $view['errors'];
$fitments        = $view['fitments'];
$numbers         = $view['numbers'] ?? [];
$copySources     = $view['copySources'] ?? [];
$copyNotice      = $view['copyNotice'] ?? null;
$copySelected    = $view['copySelected'] ?? '';
$categoryChoices = $view['categoryChoices'];
$brandChoices    = $view['brandChoices'];
$part            = null;
$photoLimits     = TeamController::photoLimits();
$submitLabel     = 'Create part';
$cancelHref      = 'parts.php';
$formMessage     = $view['problem'] === 'csrf' ? (string) $view['message'] : ($view['formMessage'] ?? null);

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<div class="stack stack--lg">
    <div class="page-header">
        <p class="page-header__text">Add a car part or accessory. It stays off the website until you tick "Show this part on the website".</p>
    </div>
    <?php require dirname(__DIR__) . '/includes/admin/part_form.php'; ?>
</div>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
