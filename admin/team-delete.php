<?php

declare(strict_types=1);

/**
 * Delete Team Member (spec 08_spec_team.md).
 *   GET  = the confirmation page. It only reads; opening this URL deletes nothing.
 *   POST = the deletion, after explicit confirmation, with a valid CSRF token. The record is
 *          removed first, then the photo files.
 * Protected: requireAdmin() runs first.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/TeamController.php';

$admin = requireAdmin();
$controller = new TeamController();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    ignore_user_abort(true);
    $view = $controller->delete($admin, $_POST, admin_client_ip(), admin_user_agent());
    if (isset($view['redirect'])) {
        admin_redirect($view['redirect'], 303);
    }
    require dirname(__DIR__) . '/includes/admin/team_problem.php';
}

$view = $controller->confirmDelete($admin, $_GET['id'] ?? null);
if ($view['problem'] !== null) {
    require dirname(__DIR__) . '/includes/admin/team_problem.php';
}

$member = $view['member'];
$id     = (int) $member['id'];
$name   = (string) $member['name'];
$photo  = admin_team_photo_url($member['photo_path'], true);

$pageTitle   = 'Delete Team Member';
$activeNav   = 'team';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Team', 'team.php'], [$name, 'team-view.php?id=' . $id], ['Delete', null]];

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<section class="card confirm-card" aria-labelledby="delete-heading">
    <h2 class="card__title" id="delete-heading">Delete <?= e($name) ?>?</h2>

    <div class="member-cell member-cell--confirm">
        <?php if ($photo !== null): ?>
            <img class="avatar avatar--lg" src="<?= $photo ?>" alt="<?= e(TeamService::photoAlt($name)) ?>" width="96" height="128">
        <?php endif; ?>
        <div class="cell-text">
            <p class="cell-title"><?= e($name) ?></p>
            <p class="cell-sub"><?= e((string) $member['position']) ?></p>
        </div>
    </div>

    <p class="text-muted">This will permanently remove this team member from the admin system<?= $photo !== null ? ', together with their photo' : '' ?>. This action cannot be undone. If you only want to hide them, deactivate them instead.</p>

    <form method="post" action="team-delete.php" class="cluster form-actions" data-single-submit>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="confirm" value="yes">
        <button type="submit" class="btn btn--danger" data-submit-button>Delete <?= e($name) ?></button>
        <a class="btn btn--ghost" href="team-view.php?id=<?= $id ?>">Cancel</a>
    </form>
</section>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
