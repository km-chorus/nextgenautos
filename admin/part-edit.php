<?php

declare(strict_types=1);

/**
 * Edit Part (spec 16, phase 1): the same form as Add Part, plus the part's photos (remove / make main) and the
 * publish and archive actions. POST saves through PartController -> PartService (Post/Redirect/Get).
 * Protected: requireAdmin() runs first.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/PartController.php';
require_once dirname(__DIR__) . '/app/controllers/TeamController.php';   // TeamController::photoLimits(): the shared upload limits

$admin = requireAdmin();

$view = (new PartController())->edit($admin, $_GET['id'] ?? null, $_SERVER['REQUEST_METHOD'] ?? 'GET', $_POST, $_FILES, $_SERVER, admin_client_ip(), admin_user_agent());

if (isset($view['redirect'])) {
    admin_redirect($view['redirect'], 303);
}
if ($view['problem'] !== null && !($view['problem'] === 'csrf' && isset($view['values']))) {
    require dirname(__DIR__) . '/includes/admin/part_problem.php';
}
http_response_code((int) $view['http']);

$part        = $view['part'];
$partId      = (int) $part['id'];
$published   = (int) $part['is_published'] === 1;
$pageTitle   = 'Edit Part';
$activeNav   = 'parts';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Parts', 'parts.php'], [(string) $part['name'], null]];

$formAction      = 'part-edit.php?id=' . $partId;
$values          = $view['values'];
$errors          = $view['errors'];
$fitments        = $view['fitments'];
$numbers         = $view['numbers'] ?? [];
$copySources     = $view['copySources'] ?? [];
$copyNotice      = $view['copyNotice'] ?? null;
$copySelected    = $view['copySelected'] ?? '';
$categoryChoices = $view['categoryChoices'];
$brandChoices    = $view['brandChoices'];
$photoLimits     = TeamController::photoLimits();
$submitLabel     = 'Save changes';
$cancelHref      = 'parts.php';
$formMessage     = $view['problem'] === 'csrf' ? (string) $view['message'] : ($view['formMessage'] ?? null);
$flash           = $view['flash'] ?? null;

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<div class="stack stack--lg">
    <?php if ($flash !== null): ?>
        <?= admin_alert($flash['type'], $flash['text'], '', true) ?>
    <?php endif; ?>
    <div class="page-header">
        <p class="page-header__text"><?= $published ? 'Shown on the website.' : 'Not shown on the website.' ?> Added <?= e(admin_format_date((string) $part['created_at'])) ?>.</p>
        <div class="cluster">
            <form method="post" action="part-state.php">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $partId ?>"><input type="hidden" name="from" value="edit">
                <button type="submit" class="btn btn--secondary" name="action" value="<?= $published ? 'unpublish' : 'publish' ?>"><?= $published ? 'Unpublish' : 'Publish' ?></button>
            </form>
            <a class="btn btn--ghost btn--text-danger" href="part-archive.php?id=<?= $partId ?>">Archive</a>
        </div>
    </div>
    <?php require dirname(__DIR__) . '/includes/admin/part_form.php'; ?>
</div>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
