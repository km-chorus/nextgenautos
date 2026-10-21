<?php

declare(strict_types=1);

/**
 * Archive confirmation (spec 16, phase 1). GET only shows what will happen; the Archive button POSTs to part-state.php.
 * An archived part is hidden everywhere and never hard-deleted. Protected: requireAdmin() runs first.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/PartController.php';

$admin = requireAdmin();

$view = (new PartController())->edit($admin, $_GET['id'] ?? null, 'GET', [], [], [], admin_client_ip(), admin_user_agent());
if ($view['problem'] !== null) {
    require dirname(__DIR__) . '/includes/admin/part_problem.php';
}
$part = $view['part'];
$partId = (int) $part['id'];

$pageTitle   = 'Archive Part';
$activeNav   = 'parts';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Parts', 'parts.php'], [(string) $part['name'], 'part-edit.php?id=' . $partId], ['Archive', null]];

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<section class="card stack" aria-labelledby="archive-title">
    <h2 class="card__title" id="archive-title">Archive this part?</h2>
    <p><strong><?= e((string) $part['name']) ?></strong> will be removed from the website and from the parts list. Its record is kept for the company's history; it is not deleted.</p>
    <div class="cluster form-actions">
        <form method="post" action="part-state.php">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $partId ?>">
            <button type="submit" class="btn btn--danger" name="action" value="archive">Archive part</button>
        </form>
        <a class="btn btn--ghost" href="part-edit.php?id=<?= $partId ?>">Cancel</a>
    </div>
</section>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
