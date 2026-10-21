<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/TeamService.php';
require_once dirname(__DIR__) . '/services/AuthorizationService.php';
require_once dirname(__DIR__) . '/helpers/csrf.php';
require_once dirname(__DIR__) . '/helpers/flash.php';
require_once dirname(__DIR__) . '/helpers/diagnostics.php';

/**
 * Team Management controller (spec 08_spec_team.md). Same pattern as the Brand and Vehicle
 * controllers. For every request it:
 *   1. checks the permission (the page has already passed requireAdmin()),
 *   2. for POST: verifies the CSRF token,
 *   3. validates the id and the input (and, for a photo, the real file content, in the service),
 *   4. calls TeamService,
 *   5. returns a view model. Pages render it, or follow its 'redirect'.
 *
 * A view model always has 'http' and 'problem' (null, or forbidden | not_found | csrf | error).
 * Messages are fixed sentences; technical detail goes to the server error log (and, in
 * development only, a path-free one-liner on the error page).
 */
final class TeamController
{
    public const PERMISSION = 'team.manage';

    public const MESSAGE_FORBIDDEN = 'Your account does not have permission to manage the team.';
    public const MESSAGE_NOT_FOUND = 'The requested team member could not be found.';
    public const MESSAGE_CSRF = "We couldn't verify your request. Reload the page and try again.";
    public const MESSAGE_ERROR = 'Something went wrong. Please try again.';
    public const MESSAGE_INVALID_FORM = "We couldn't save this team member. Please check the highlighted fields.";
    public const MESSAGE_TOO_LARGE = 'The upload was too large for the server configuration. Choose a smaller photo (up to 10 MB).';
    public const MESSAGE_DELETE_FAILED = "We couldn't delete this team member. Please try again.";

    public function __construct(private ?TeamService $service = null)
    {
    }

    /**
     * GET: the team list with search and filters.
     *
     * @param array<string,mixed> $admin
     * @param array<string,mixed> $query $_GET
     * @return array<string,mixed>
     */
    public function index(array $admin, array $query): array
    {
        return $this->run($admin, function (TeamService $service) use ($query): array {
            $filters = TeamValidator::filters($query);
            $data = $service->listMembers($filters);
            $filtered = $filters['q'] !== '' || $filters['category'] !== '' || $filters['status'] !== 'all';

            return [
                'members'    => $data['members'],
                'total_all'  => $data['total_all'],
                'filters'    => $filters,
                'filtered'   => $filtered,
                // Moving up/down needs the complete, unfiltered list in front of the administrator.
                'canReorder' => !$filtered && $data['total_all'] > 1,
                'flash'      => flash_take(),
            ];
        });
    }

    /**
     * GET/POST: the Add Team Member form.
     *
     * @param array<string,mixed> $post
     * @param array<string,mixed> $files $_FILES
     * @param array<string,mixed> $server $_SERVER (only CONTENT_LENGTH is read)
     * @return array<string,mixed>
     */
    public function create(array $admin, string $method, array $post, array $files, array $server, string $ip, string $userAgent, bool $requireHttpUpload = true): array
    {
        return $this->run($admin, function (TeamService $service) use ($admin, $method, $post, $files, $server, $ip, $userAgent, $requireHttpUpload): array {
            $view = [
                'values' => ['name' => '', 'position' => '', 'category' => 'other', 'biography' => '', 'display_order' => (string) $service->nextDisplayOrder(), 'status' => 'active'],
                'errors' => [],
                'member' => null,
            ];
            if ($method !== 'POST') {
                return $view;
            }
            if ($tooLarge = $this->tooLarge($post, $files, $server, 'team-create.php')) {
                return $tooLarge;
            }
            if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                $view['values'] = self::postedValues($post, $view['values']);

                return $this->csrfFailure($admin, $ip, $userAgent) + $view;
            }

            $result = $service->createMember($post, self::singlePhoto($files), $admin['id'], $ip, $userAgent, $requireHttpUpload);
            if ($result['status'] === TeamService::RESULT_OK) {
                flash_set('success', 'Team member created successfully.');

                return ['redirect' => 'team-view.php?id=' . $result['id']];
            }

            return $this->invalidForm($view, $result);
        });
    }

    /**
     * GET/POST: the Edit Team Member form.
     *
     * @param array<string,mixed> $post
     * @param array<string,mixed> $files $_FILES
     * @param array<string,mixed> $server $_SERVER
     * @return array<string,mixed>
     */
    public function edit(array $admin, mixed $rawId, string $method, array $post, array $files, array $server, string $ip, string $userAgent, bool $requireHttpUpload = true): array
    {
        return $this->run($admin, function (TeamService $service) use ($admin, $rawId, $method, $post, $files, $server, $ip, $userAgent, $requireHttpUpload): array {
            $id = TeamValidator::id($rawId);
            $member = $id === null ? null : $service->find($id);
            if ($member === null) {
                return $this->notFound();
            }
            $view = [
                'member' => $member,
                'errors' => [],
                'values' => [
                    'name' => (string) $member['name'], 'position' => (string) $member['position'], 'category' => (string) $member['category'],
                    'biography' => (string) ($member['biography'] ?? ''), 'display_order' => (string) $member['display_order'], 'status' => (string) $member['status'],
                ],
            ];
            if ($method !== 'POST') {
                return $view;
            }
            if ($tooLarge = $this->tooLarge($post, $files, $server, 'team-edit.php?id=' . $id)) {
                return $tooLarge;
            }
            if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                $view['values'] = self::postedValues($post, $view['values']);

                return $this->csrfFailure($admin, $ip, $userAgent) + $view;
            }

            $result = $service->updateMember($id, $post, self::singlePhoto($files), $admin['id'], $ip, $userAgent, $requireHttpUpload);
            switch ($result['status']) {
                case TeamService::RESULT_OK:
                    flash_set('success', $result['photo_replaced'] ? 'Team member updated successfully. The photo was replaced.' : 'Team member updated successfully.');
                    return ['redirect' => 'team-view.php?id=' . $id];
                case TeamService::RESULT_UNCHANGED:
                    flash_set('info', 'No changes were made.');
                    return ['redirect' => 'team-view.php?id=' . $id];
                case TeamService::RESULT_NOT_FOUND:
                    return $this->notFound();
            }

            return $this->invalidForm($view, $result);
        });
    }

    /**
     * GET: details of one team member.
     *
     * @return array<string,mixed>
     */
    public function show(array $admin, mixed $rawId): array
    {
        return $this->run($admin, function (TeamService $service) use ($rawId): array {
            $id = TeamValidator::id($rawId);
            $member = $id === null ? null : $service->find($id);

            return $member === null ? $this->notFound() : ['member' => $member, 'flash' => flash_take()];
        });
    }

    /**
     * POST: activate or deactivate (the target is explicit, so repeating it cannot flip it back).
     *
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    public function changeStatus(array $admin, array $post, string $ip, string $userAgent): array
    {
        return $this->run($admin, function (TeamService $service) use ($admin, $post, $ip, $userAgent): array {
            if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                return $this->csrfFailure($admin, $ip, $userAgent);
            }
            $id = TeamValidator::id($post['id'] ?? null);
            $target = TeamValidator::targetStatus($post['status'] ?? null);
            if ($id === null) {
                return $this->notFound();
            }
            if ($target === null) {
                return $this->failure(400, self::MESSAGE_ERROR);
            }
            // Built from validated values only, never from a URL the browser sent (no open redirect).
            $back = ($post['from'] ?? null) === 'view' ? 'team-view.php?id=' . $id : $this->listUrl($post);

            $result = $service->setStatus($id, $target, $admin['id'], $ip, $userAgent);
            switch ($result['status']) {
                case TeamService::RESULT_OK:
                    flash_set('success', $target === 'active' ? 'Team member activated successfully.' : 'Team member deactivated successfully.');
                    break;
                case TeamService::RESULT_UNCHANGED:
                    flash_set('info', $target === 'active' ? 'The team member is already active.' : 'The team member is already inactive.');
                    break;
                default:
                    flash_set('error', self::MESSAGE_NOT_FOUND);
                    $back = 'team.php';
            }

            return ['redirect' => $back];
        });
    }

    /**
     * POST: save a new order of all team members (the full list, validated by the service).
     *
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    public function reorder(array $admin, array $post, string $ip, string $userAgent): array
    {
        return $this->run($admin, function (TeamService $service) use ($admin, $post, $ip, $userAgent): array {
            if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                return $this->csrfFailure($admin, $ip, $userAgent);
            }
            $result = $service->reorder($post['order'] ?? null, $admin['id'], $ip, $userAgent);
            switch ($result['status']) {
                case TeamService::RESULT_OK:
                    flash_set('success', 'The team order was saved.');
                    break;
                case TeamService::RESULT_UNCHANGED:
                    flash_set('info', 'The team order is already like that.');
                    break;
                default:
                    flash_set('error', TeamService::MESSAGE_ORDER);
            }

            return ['redirect' => 'team.php'];
        });
    }

    /**
     * POST: remove a member's photo (the member stays).
     *
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    public function removePhoto(array $admin, array $post, string $ip, string $userAgent): array
    {
        return $this->run($admin, function (TeamService $service) use ($admin, $post, $ip, $userAgent): array {
            if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                return $this->csrfFailure($admin, $ip, $userAgent);
            }
            $id = TeamValidator::id($post['id'] ?? null);
            if ($id === null) {
                return $this->notFound();
            }
            $result = $service->removePhoto($id, $admin['id'], $ip, $userAgent);
            switch ($result['status']) {
                case TeamService::RESULT_OK:
                    flash_set('success', 'The photo was removed.');
                    break;
                case TeamService::RESULT_UNCHANGED:
                    flash_set('info', 'This team member has no photo.');
                    break;
                default:
                    flash_set('error', self::MESSAGE_NOT_FOUND);

                    return ['redirect' => 'team.php'];
            }

            return ['redirect' => 'team-edit.php?id=' . $id];
        });
    }

    /**
     * GET: the delete confirmation ("Delete John Mensah?"). Reading this page changes nothing.
     *
     * @return array<string,mixed>
     */
    public function confirmDelete(array $admin, mixed $rawId): array
    {
        return $this->run($admin, function (TeamService $service) use ($rawId): array {
            $id = TeamValidator::id($rawId);
            $member = $id === null ? null : $service->find($id);

            return $member === null ? $this->notFound() : ['member' => $member];
        });
    }

    /**
     * POST: delete after confirmation.
     *
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    public function delete(array $admin, array $post, string $ip, string $userAgent): array
    {
        return $this->run($admin, function (TeamService $service) use ($admin, $post, $ip, $userAgent): array {
            if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                return $this->csrfFailure($admin, $ip, $userAgent);
            }
            $id = TeamValidator::id($post['id'] ?? null);
            if ($id === null) {
                return $this->notFound();
            }
            if (($post['confirm'] ?? null) !== 'yes') {
                return $this->failure(400, self::MESSAGE_ERROR);
            }
            $result = $service->deleteMember($id, $admin['id'], $ip, $userAgent);
            if ($result['status'] === TeamService::RESULT_OK) {
                flash_set('success', 'Team member deleted successfully.');
            } else {
                flash_set('error', self::MESSAGE_NOT_FOUND);
            }

            return ['redirect' => 'team.php'];
        });
    }

    /**
     * What the photo field should promise: the largest file the server really accepts (the
     * application's 10 MB, or less if PHP is configured lower), the whole-request limit the
     * browser checks, and a notice when this server cannot process images at all.
     *
     * @return array{maxBytes:int,maxTotal:int,limitText:string,notice:?string}
     */
    public static function photoLimits(): array
    {
        $server = ImageService::serverUploadLimit();
        $max = ($server > 0 && $server < ImageValidator::MAX_BYTES) ? $server : ImageValidator::MAX_BYTES;
        $mb = $max / 1048576;
        $missing = ImageService::missingRequirements();

        return [
            'maxBytes'  => $max,
            'maxTotal'  => ImageService::iniBytes('post_max_size'),
            'limitText' => (fmod($mb, 1) == 0.0 ? (string) intdiv($max, 1048576) : (string) round($mb, 1)) . ' MB',
            'notice'    => $missing === [] ? null : 'The PHP component(s) ' . implode(', ', $missing) . ' are not enabled. Enable them in php.ini and restart the PHP server.',
        ];
    }

    /**
     * The one uploaded photo from $_FILES['photo'], or null when none was chosen. Odd shapes (arrays,
     * several files under one name) become an "invalid upload" entry so the service reports them.
     *
     * @return array{name:string,tmp_name:string,error:int,size:int}|null
     */
    public static function singlePhoto(array $files): ?array
    {
        $f = $files['photo'] ?? null;
        if (!is_array($f)) {
            return null;
        }
        if (!is_string($f['name'] ?? null) || !is_string($f['tmp_name'] ?? null) || !is_int($f['error'] ?? null) || !is_int($f['size'] ?? null)) {
            return ['name' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_PARTIAL, 'size' => 0];
        }
        if ($f['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return ['name' => $f['name'], 'tmp_name' => $f['tmp_name'], 'error' => $f['error'], 'size' => $f['size']];
    }

    /* ---------------------------------------------------------------- */

    /**
     * PHP drops the whole body (no $_POST, no $_FILES) when it exceeds post_max_size; explain and return to the form.
     *
     * @return array<string,mixed>|null
     */
    private function tooLarge(array $post, array $files, array $server, string $back): ?array
    {
        if ($post === [] && $files === [] && (int) ($server['CONTENT_LENGTH'] ?? 0) > 0) {
            flash_set('error', self::MESSAGE_TOO_LARGE);

            return ['redirect' => $back];
        }

        return null;
    }

    /**
     * Permission check, service creation and error containment shared by every action.
     *
     * @param callable(TeamService):array<string,mixed> $action
     * @return array<string,mixed>
     */
    private function run(array $admin, callable $action): array
    {
        if (!AuthorizationService::can((string) ($admin['role'] ?? ''), self::PERMISSION)) {
            return $this->failure(403, self::MESSAGE_FORBIDDEN, 'forbidden');
        }
        try {
            $result = $action($this->service ?? TeamService::create());
        } catch (Throwable $e) {
            error_log('Team management failed: ' . get_class($e) . ': ' . $e->getMessage());

            return $this->failure(500, self::MESSAGE_ERROR) + ['detail' => development_error_detail($e)];
        }

        return $result + ['http' => 200, 'problem' => null];
    }

    /** @return array<string,mixed> */
    private function failure(int $http, string $message, string $problem = 'error'): array
    {
        return ['http' => $http, 'problem' => $problem, 'message' => $message];
    }

    /** @return array<string,mixed> */
    private function notFound(): array
    {
        return $this->failure(404, self::MESSAGE_NOT_FOUND, 'not_found');
    }

    /** @return array<string,mixed> */
    private function csrfFailure(array $admin, string $ip, string $userAgent): array
    {
        try {
            (new AuditLogRepository(Database::connection()))->record('CSRF_FAILURE', (int) $admin['id'], null, null,
                'Team management request rejected: missing or invalid CSRF token', $ip, $userAgent);
        } catch (Throwable $e) {
            error_log('Team management: could not log CSRF failure: ' . get_class($e));
        }

        return $this->failure(400, self::MESSAGE_CSRF, 'csrf');
    }

    /**
     * The submitted text fields laid over the current form values (non-text input is ignored).
     *
     * @param array<string,mixed> $post
     * @param array<string,string> $values
     * @return array<string,string>
     */
    private static function postedValues(array $post, array $values): array
    {
        foreach ($values as $key => $current) {
            if (is_string($post[$key] ?? null)) {
                $values[$key] = $post[$key];
            }
        }

        return $values;
    }

    /**
     * Re-shows a form with the cleaned values and field errors (HTTP 200, like the login form: a typo is not a server error).
     *
     * @param array<string,mixed> $view
     * @param array<string,mixed> $result
     * @return array<string,mixed>
     */
    private function invalidForm(array $view, array $result): array
    {
        $values = [];
        foreach ($result['values'] as $key => $value) {
            $values[$key] = (string) ($value ?? '');
        }
        $view['values'] = $values + $view['values'];
        $view['errors'] = $result['errors'];
        $view['http'] = 200;
        $view['formMessage'] = self::MESSAGE_INVALID_FORM;

        return $view;
    }

    /** The list URL the administrator came from, rebuilt from validated filters only. */
    private function listUrl(array $post): string
    {
        $filters = TeamValidator::filters(['q' => $post['q'] ?? null, 'category' => $post['f_category'] ?? null, 'status' => $post['f_status'] ?? null]);
        $query = array_filter(['q' => $filters['q'], 'category' => $filters['category'], 'status' => $filters['status'] === 'all' ? '' : $filters['status']], 'strlen');

        return 'team.php' . ($query === [] ? '' : '?' . http_build_query($query));
    }
}
