<?php

declare(strict_types=1);

/**
 * Part categories, the "type" of a part (spec 16, phase 1): add, rename, reorder, activate / deactivate. A category is
 * never deleted: an inactive one is no longer offered for new parts, and parts already in it keep it.
 * GET ?edit=<id> puts that category in the form. POST saves through PartController (Post/Redirect/Get).
 * Protected: requireAdmin() runs first.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/PartController.php';

$admin = requireAdmin();

$view = (new PartController())->categories($admin, $_SERVER['REQUEST_METHOD'] ?? 'GET', $_POST, admin_client_ip(), admin_user_agent());
if (isset($view['redirect'])) {
    admin_redirect($view['redirect'], 303);
}
if ($view['problem'] !== null && !($view['problem'] === 'csrf' && isset($view['categories']))) {
    require dirname(__DIR__) . '/includes/admin/part_problem.php';
}
http_response_code((int) $view['http']);

$categories = $view['categories'];
$values = $view['values'];
$editing = $view['editing'];
if ($editing === null && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && ($editId = PartValidator::id($_GET['edit'] ?? null)) !== null) {
    foreach ($categories as $c) {
        if ($c['id'] === $editId) {
            $editing = $editId;
            $values = ['name' => $c['name'], 'display_order' => (string) $c['display_order'], 'status' => $c['status']];
        }
    }
}
$errors = $view['errors'];
$formMessage = $view['problem'] === 'csrf' ? (string) $view['message'] : ($view['formMessage'] ?? null);
$flash = $view['flash'] ?? null;
$nameError = $errors['name'] ?? null;
$orderError = $errors['display_order'] ?? null;

$pageTitle   = 'Part Categories';
$activeNav   = 'parts';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Parts', 'parts.php'], ['Categories', null]];

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<div class="stack stack--lg">
    <?php if ($flash !== null): ?>
        <?= admin_alert($flash['type'], $flash['text'], '', true) ?>
    <?php endif; ?>
    <?php if (!empty($formMessage)): ?>
        <div class="section-alert" data-login-notice tabindex="-1"><?= admin_alert('error', $formMessage) ?></div>
    <?php endif; ?>

    <div class="page-header">
        <p class="page-header__text">The types customers browse by on the homepage. Only types that have a published part are shown there.</p>
    </div>

    <form method="post" action="part-categories.php" class="card form-section stack" novalidate data-single-submit aria-labelledby="category-form-title">
        <h2 class="card__title" id="category-form-title"><?= $editing === null ? 'Add a category' : 'Edit category' ?></h2>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $editing === null ? '' : (int) $editing ?>">
        <div class="form-row">
            <div class="field">
                <label class="label" for="category-name">Name <span aria-hidden="true">*</span></label>
                <input class="input" type="text" id="category-name" name="name" value="<?= e($values['name']) ?>" maxlength="<?= PartValidator::CATEGORY_NAME_MAX ?>" autocomplete="off" required
                       aria-describedby="category-name-error"<?= $nameError !== null ? ' aria-invalid="true"' : '' ?>>
                <p class="field-error" id="category-name-error" role="alert"<?= $nameError === null ? ' hidden' : '' ?>><?= admin_icon('alert-circle', 'icon--sm') ?><span data-error-text><?= e((string) $nameError) ?></span></p>
            </div>
            <div class="field">
                <label class="label" for="category-order">Display order <span class="label__note">(optional)</span></label>
                <input class="input" type="text" inputmode="numeric" id="category-order" name="display_order" value="<?= e($values['display_order']) ?>" maxlength="4" autocomplete="off"
                       aria-describedby="category-order-hint category-order-error"<?= $orderError !== null ? ' aria-invalid="true"' : '' ?>>
                <p class="hint" id="category-order-hint">Lower numbers come first.</p>
                <p class="field-error" id="category-order-error" role="alert"<?= $orderError === null ? ' hidden' : '' ?>><?= admin_icon('alert-circle', 'icon--sm') ?><span data-error-text><?= e((string) $orderError) ?></span></p>
            </div>
        </div>
        <label class="check"><input type="checkbox" name="status" value="inactive"<?= $values['status'] === 'inactive' ? ' checked' : '' ?>><span class="check__text">Inactive (not offered for new parts)</span></label>
        <div class="cluster form-actions">
            <button type="submit" class="btn btn--primary" data-submit-button><?= $editing === null ? 'Add category' : 'Save category' ?></button>
            <?php if ($editing !== null): ?><a class="btn btn--ghost" href="part-categories.php">Cancel</a><?php endif; ?>
        </div>
    </form>

    <section class="card card--flush" aria-labelledby="categories-heading">
        <div class="card__header"><h2 class="card__heading" id="categories-heading">Categories</h2></div>
        <div class="table-wrap" role="region" aria-labelledby="categories-heading" tabindex="0">
            <table class="table table--stack">
                <thead><tr><th scope="col">Name</th><th scope="col">Parts</th><th scope="col">Order</th><th scope="col">Status</th><th scope="col" class="col-actions"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                    <?php foreach ($categories as $c): ?>
                        <tr>
                            <td class="cell-primary"><p class="cell-title"><?= e($c['name']) ?></p></td>
                            <td data-label="Parts"><?= $c['parts'] ?></td>
                            <td data-label="Order"><?= $c['display_order'] ?></td>
                            <td data-label="Status"><?= admin_status_badge($c['status']) ?></td>
                            <td class="col-actions"><a class="btn btn--ghost btn--sm" href="part-categories.php?edit=<?= $c['id'] ?>">Edit<span class="sr-only"> <?= e($c['name']) ?></span></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
