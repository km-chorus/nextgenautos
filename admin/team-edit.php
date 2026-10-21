<?php

declare(strict_types=1);

/**
 * Edit Team Member (spec 08_spec_team.md). The id comes from the URL (untrusted): TeamController
 * validates its format and that the member exists. GET shows the form; POST saves (and, when a
 * new photo is chosen, replaces the photo: new file first, old file removed only after the
 * database change succeeded), then redirects (Post/Redirect/Get). Without a new photo the existing
 * photo is kept. Protected: requireAdmin() runs first.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/TeamController.php';

$admin = requireAdmin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    ignore_user_abort(true);
    @set_time_limit(120);
}

$view = (new TeamController())->edit($admin, $_GET['id'] ?? null, $_SERVER['REQUEST_METHOD'] ?? 'GET', $_POST, $_FILES, $_SERVER, admin_client_ip(), admin_user_agent());

if (isset($view['redirect'])) {
    admin_redirect($view['redirect'], 303);
}
if ($view['problem'] !== null && !($view['problem'] === 'csrf' && isset($view['values']))) {
    require dirname(__DIR__) . '/includes/admin/team_problem.php';
}
http_response_code((int) $view['http']);

$member   = $view['member'];
$memberId = (int) $member['id'];
$name     = (string) $member['name'];

$pageTitle   = 'Edit Team Member';
$activeNav   = 'team';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Team', 'team.php'], [$name, 'team-view.php?id=' . $memberId], ['Edit', null]];

$flash        = null;
$limits       = TeamController::photoLimits();
$photoLimits  = $limits;
$photoNotice  = $limits['notice'];
$formAction   = 'team-edit.php?id=' . $memberId;
$values       = $view['values'];
$errors       = $view['errors'];
$submitLabel  = 'Save changes';
$cancelHref   = 'team-view.php?id=' . $memberId;
$formMessage  = $view['problem'] === 'csrf' ? (string) $view['message'] : ($view['formMessage'] ?? null);
$notice       = flash_take();

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<div class="stack stack--lg">
    <?php if ($notice !== null): ?>
        <?= admin_alert($notice['type'], $notice['text'], '', true) ?>
    <?php endif; ?>
    <div class="page-header">
        <p class="page-header__text">Editing <strong><?= e($name) ?></strong>. Fields you do not change stay as they are, and the photo is kept unless you choose a new one.</p>
    </div>
    <?php require dirname(__DIR__) . '/includes/admin/team_form.php'; ?>
</div>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
