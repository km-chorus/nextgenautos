<?php

declare(strict_types=1);

/**
 * Team member details (spec 08_spec_team.md): read-only view with the actions available for one
 * member. The id comes from the URL (untrusted) and is validated by TeamController.
 * Protected: requireAdmin() runs first.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/TeamController.php';

$admin = requireAdmin();

$view = (new TeamController())->show($admin, $_GET['id'] ?? null);
if ($view['problem'] !== null) {
    require dirname(__DIR__) . '/includes/admin/team_problem.php';
}

$member   = $view['member'];
$flash    = $view['flash'];
$id       = (int) $member['id'];
$name     = (string) $member['name'];
$isActive = $member['status'] === 'active';
$photo    = admin_team_photo_url($member['photo_path']);

$pageTitle   = $name;
$activeNav   = 'team';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Team', 'team.php'], [$name, null]];

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<div class="stack stack--lg">

    <?php if ($flash !== null): ?>
        <?= admin_alert($flash['type'], $flash['text'], '', true) ?>
    <?php endif; ?>

    <section class="card" aria-labelledby="member-heading">
        <div class="card__row">
            <h2 class="card__title" id="member-heading">Team member</h2>
            <?= admin_status_badge((string) $member['status']) ?>
        </div>

        <div class="member-detail">
            <div class="member-detail__photo">
                <?php if ($photo !== null): ?>
                    <img class="photo-frame" src="<?= $photo ?>" alt="<?= e(TeamService::photoAlt($name)) ?>" width="240" height="320">
                <?php else: ?>
                    <span class="photo-frame photo-frame--empty"><?= admin_icon('user', 'icon--lg') ?><span>No photo</span></span>
                <?php endif; ?>
            </div>
            <dl class="facts facts--wide">
                <div><dt>Full name</dt><dd><?= e($name) ?></dd></div>
                <div><dt>Position</dt><dd><?= e((string) $member['position']) ?></dd></div>
                <div><dt>Category</dt><dd><?= e(TeamValidator::categoryLabel((string) $member['category'])) ?></dd></div>
                <div><dt>Status</dt><dd><?= e(admin_status_label((string) $member['status'])) ?></dd></div>
                <div><dt>Display order</dt><dd><?= e((string) $member['display_order']) ?></dd></div>
                <div><dt>Biography</dt><dd class="facts__text"><?= trim((string) $member['biography']) === '' ? '<span class="text-muted">No biography</span>' : nl2br(e((string) $member['biography']), false) ?></dd></div>
                <div><dt>Created</dt><dd><?= e(admin_format_datetime((string) $member['created_at'])) ?></dd></div>
                <div><dt>Last updated</dt><dd><?= e(admin_format_datetime((string) $member['updated_at'])) ?></dd></div>
            </dl>
        </div>

        <div class="cluster form-actions">
            <a class="btn btn--primary" href="team-edit.php?id=<?= $id ?>">Edit<span class="sr-only"> <?= e($name) ?></span></a>
            <form method="post" action="team-status.php" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="status" value="<?= $isActive ? 'inactive' : 'active' ?>">
                <input type="hidden" name="from" value="view">
                <button type="submit" class="btn btn--secondary"><?= $isActive ? 'Deactivate' : 'Activate' ?><span class="sr-only"> <?= e($name) ?></span></button>
            </form>
            <a class="btn btn--secondary btn--text-danger" href="team-delete.php?id=<?= $id ?>">Delete<span class="sr-only"> <?= e($name) ?></span></a>
            <a class="btn btn--ghost" href="team.php">Back to Team</a>
        </div>
    </section>
</div>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
