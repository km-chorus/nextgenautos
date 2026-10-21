<?php

declare(strict_types=1);

/**
 * Team Management: list, search, filters and ordering (spec 08_spec_team.md).
 *
 * Presentation only: data comes from TeamController -> TeamService -> TeamRepository -> PDO.
 * Protected: requireAdmin() runs before anything else. Search and filters use GET (they change
 * nothing). Status changes, ordering and deletion are separate POST endpoints.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/TeamController.php';

$admin = requireAdmin();

$view = (new TeamController())->index($admin, $_GET);
if ($view['problem'] !== null) {
    require dirname(__DIR__) . '/includes/admin/team_problem.php';
}

$members    = $view['members'];
$filters    = $view['filters'];
$flash      = $view['flash'];
$canReorder = $view['canReorder'];
$allIds     = array_map(static fn (array $m): string => (string) $m['id'], $members);

/** The complete order after moving the member at position $from to position $to (ids, for the reorder form). */
$orderAfterMove = static function (int $from, int $to) use ($allIds): array {
    $ids = $allIds;
    $moved = array_splice($ids, $from, 1);
    array_splice($ids, $to, 0, $moved);

    return $ids;
};

$pageTitle   = 'Team';
$activeNav   = 'team';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Team', null]];

require dirname(__DIR__) . '/includes/admin/shell_start.php';
?>
<div class="stack stack--lg">

    <?php if ($flash !== null): ?>
        <?= admin_alert($flash['type'], $flash['text'], '', true) ?>
    <?php endif; ?>

    <div class="page-header">
        <p class="page-header__text">Manage the people shown on the company's team page. Only active members are meant to appear publicly; the order below is the order they will be shown in.</p>
        <a class="btn btn--primary" href="team-create.php"><?= admin_icon('plus') ?>Add Team Member</a>
    </div>

    <?php if ($view['total_all'] === 0): ?>
        <section class="card card--flush" aria-labelledby="team-heading">
            <h2 class="sr-only" id="team-heading">Team list</h2>
            <?= admin_empty_state(
                'No team members yet.',
                'Add your first team member to begin building the company profile.',
                ['label' => 'Add Team Member', 'icon' => 'plus', 'href' => 'team-create.php'],
                'users'
            ) ?>
        </section>
    <?php else: ?>
        <section class="card card--flush" aria-labelledby="team-heading">
            <div class="card__header"><h2 class="card__heading" id="team-heading">Team list</h2></div>

            <form class="filter-bar filter-bar--team" method="get" action="team.php" role="search" aria-label="Search and filter team members">
                <div class="field filter-bar__search">
                    <label class="label" for="team-q">Search by name or position</label>
                    <input class="input" type="search" id="team-q" name="q" value="<?= e($filters['q']) ?>"
                           maxlength="<?= TeamValidator::SEARCH_MAX ?>" autocomplete="off" placeholder="For example Sales Manager">
                </div>
                <div class="field">
                    <label class="label" for="team-category">Category</label>
                    <select class="select" id="team-category" name="category">
                        <option value="">All categories</option>
                        <?php foreach (TeamValidator::CATEGORIES as $key => $label): ?>
                            <option value="<?= e($key) ?>"<?= $filters['category'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="label" for="team-status">Status</label>
                    <select class="select" id="team-status" name="status">
                        <?php foreach (['all' => 'All', 'active' => 'Active', 'inactive' => 'Inactive'] as $value => $label): ?>
                            <option value="<?= e($value) ?>"<?= $filters['status'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-bar__actions">
                    <button type="submit" class="btn btn--secondary">Apply</button>
                    <?php if ($view['filtered']): ?>
                        <a class="btn btn--ghost" href="team.php">Clear</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($members === []): ?>
                <?= admin_empty_state(
                    'No team members match your search.',
                    'Try a different name, position, category or status.',
                    ['label' => 'Clear search and filters', 'href' => 'team.php'],
                    'users'
                ) ?>
            <?php else: ?>
                <p class="result-count" role="status">
                    <?= e((string) count($members)) ?> <?= count($members) === 1 ? 'team member' : 'team members' ?><?= $view['filtered'] ? ' found' : '' ?>
                </p>
                <?php if (!$canReorder && $view['total_all'] > 1): ?>
                    <p class="hint hint--pad">Clear the search and filters to change the order of the team.</p>
                <?php endif; ?>
                <div class="table-wrap" role="region" aria-labelledby="team-heading" tabindex="0">
                    <table class="table table--stack table--team">
                        <thead>
                            <tr>
                                <th scope="col">Member</th>
                                <th scope="col">Position</th>
                                <th scope="col">Category</th>
                                <th scope="col">Status</th>
                                <th scope="col">Order</th>
                                <th scope="col" class="col-actions"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $index => $member): ?>
                                <?php
                                $id       = (int) $member['id'];
                                $name     = (string) $member['name'];
                                $isActive = $member['status'] === 'active';
                                $thumb    = admin_team_photo_url($member['photo_path'], true);
                                ?>
                                <tr>
                                    <td class="cell-primary">
                                        <div class="member-cell">
                                            <?php if ($thumb !== null): ?>
                                                <img class="avatar" src="<?= $thumb ?>" alt="<?= e(TeamService::photoAlt($name)) ?>" width="48" height="64" loading="lazy" decoding="async">
                                            <?php else: ?>
                                                <span class="avatar avatar--empty"><?= admin_icon('user') ?><span class="sr-only">No photo</span></span>
                                            <?php endif; ?>
                                            <div class="cell-text">
                                                <p class="cell-title"><a href="team-view.php?id=<?= $id ?>"><?= e($name) ?></a></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="Position"><div class="cell-text"><?= e((string) $member['position']) ?></div></td>
                                    <td data-label="Category"><?= e(TeamValidator::categoryLabel((string) $member['category'])) ?></td>
                                    <td data-label="Status"><?= admin_status_badge((string) $member['status']) ?></td>
                                    <td data-label="Order" class="nowrap"><?= e((string) $member['display_order']) ?></td>
                                    <td class="col-actions">
                                        <div class="row-actions">
                                            <a class="btn btn--ghost btn--sm" href="team-view.php?id=<?= $id ?>">View<span class="sr-only"> <?= e($name) ?></span></a>
                                            <a class="btn btn--ghost btn--sm" href="team-edit.php?id=<?= $id ?>">Edit<span class="sr-only"> <?= e($name) ?></span></a>
                                            <form method="post" action="team-status.php" class="inline-form">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= $id ?>">
                                                <input type="hidden" name="status" value="<?= $isActive ? 'inactive' : 'active' ?>">
                                                <input type="hidden" name="q" value="<?= e($filters['q']) ?>">
                                                <input type="hidden" name="f_category" value="<?= e($filters['category']) ?>">
                                                <input type="hidden" name="f_status" value="<?= e($filters['status']) ?>">
                                                <button type="submit" class="btn btn--ghost btn--sm"><?= $isActive ? 'Deactivate' : 'Activate' ?><span class="sr-only"> <?= e($name) ?></span></button>
                                            </form>
                                            <?php if ($canReorder): ?>
                                                <?php foreach ([['Move up', $index > 0, $index - 1], ['Move down', $index < count($members) - 1, $index + 1]] as [$text, $enabled, $to]): ?>
                                                    <?php if ($enabled): ?>
                                                        <form method="post" action="team-order.php" class="inline-form">
                                                            <?= csrf_field() ?>
                                                            <?php foreach ($orderAfterMove($index, $to) as $orderedId): ?>
                                                                <input type="hidden" name="order[]" value="<?= e($orderedId) ?>">
                                                            <?php endforeach; ?>
                                                            <button type="submit" class="btn btn--ghost btn--sm"><?= e($text) ?><span class="sr-only"> <?= e($name) ?></span></button>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                            <a class="btn btn--ghost btn--sm btn--text-danger" href="team-delete.php?id=<?= $id ?>">Delete<span class="sr-only"> <?= e($name) ?></span></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/includes/admin/shell_end.php'; ?>
