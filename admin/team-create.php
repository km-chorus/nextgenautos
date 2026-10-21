<?php

declare(strict_types=1);

/**
 * Add Team Member (spec 08_spec_team.md). GET shows the form; POST creates the member (and
 * stores the optional photo) through TeamController -> TeamService, then redirects
 * (Post/Redirect/Get). Protected: requireAdmin() runs first.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/TeamController.php';

$admin = requireAdmin();

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
if ($isPost) {
    ignore_user_abort(true);   // do not stop between writing the photo and saving the record
    @set_time_limit(120);
}

$view = (new TeamController())->create($admin, $_SERVER['REQUEST_METHOD'] ?? 'GET', $_POST, $_FILES, $_SERVER, admin_client_ip(), admin_user_agent());

if (isset($view['redirect'])) {
    admin_redirect($view['redirect'], 303);
}
// A failed CSRF check keeps the typed values on screen; any other problem has its own page.
if ($view['problem'] !== null && !($view['problem'] === 'csrf' && isset($view['values']))) {
    require dirname(__DIR__) . '/includes/admin/team_problem.php';
}
http_response_code((int) $view['http']);

$pageTitle   = 'Add Team Member';
$activeNav   = 'team';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Team', 'team.php'], ['Add Team Member', null]];

$limits       = TeamController::photoLimits();
$photoLimits  = $limits;
$photoNotice  = $limits['notice'];
$formAction   = 'team-create.php';
$values       = $view['values'];
$errors       = $view['errors'];
$member       = null;
$submitLabel  = 'Create team member';
$cancelHref   = 'team.php';
$formMessage  = $view['problem'] === 'csrf' ? (string) $view['message'] : ($view['formMessage'] ?? null);
$notice       = flash_take();   // for example "The upload was too large" after a request PHP discarded

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<div class="stack stack--lg">
    <?php if ($notice !== null): ?>
        <?= admin_alert($notice['type'], $notice['text'], '', true) ?>
    <?php endif; ?>
    <div class="page-header">
        <p class="page-header__text">Add a person to the company team. Everything shown here comes from you: nothing is filled in automatically.</p>
    </div>
    <?php require dirname(__DIR__) . '/includes/admin/team_form.php'; ?>
</div>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
