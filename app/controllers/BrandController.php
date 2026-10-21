<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/BrandService.php';
require_once dirname(__DIR__) . '/services/AuthorizationService.php';
require_once dirname(__DIR__) . '/helpers/csrf.php';
require_once dirname(__DIR__) . '/helpers/flash.php';

/**
 * Brand Management controller (spec 05_spec_brand_crud.md).
 *
 * Reference pattern for later admin CRUD modules. For every request it:
 *   1. checks the permission (the page has already passed requireAdmin()),
 *   2. for POST: verifies the CSRF token,
 *   3. validates the id and the input,
 *   4. calls BrandService,
 *   5. returns a view model. Pages render it, or follow its 'redirect'.
 *
 * A view model always has 'http' (status code) and 'problem' (null, or one of
 * forbidden | not_found | csrf | error). Technical detail goes to the server
 * error log; messages shown to the user are fixed sentences.
 */
final class BrandController
{
    public const PERMISSION = 'brands.manage';

    public const MESSAGE_FORBIDDEN = 'Your account does not have permission to manage brands.';
    public const MESSAGE_NOT_FOUND = 'The requested brand could not be found.';
    public const MESSAGE_CSRF = "We couldn't verify your request. Reload the page and try again.";
    public const MESSAGE_ERROR = 'Something went wrong. Please try again.';
    public const MESSAGE_REFERENCED = 'This brand cannot be deleted because it is associated with vehicles. Deactivate it instead.';
    public const MESSAGE_INVALID_FORM = 'Please correct the highlighted fields.';

    public function __construct(private ?BrandService $service = null)
    {
    }

    /**
     * GET: the brand list with search and status filter.
     *
     * @param array<string,mixed> $admin
     * @param array<string,mixed> $query $_GET
     * @return array<string,mixed>
     */
    public function index(array $admin, array $query): array
    {
        return $this->run($admin, function (BrandService $service) use ($query): array {
            $filters = BrandValidator::filters($query);
            $data = $service->listBrands($filters);

            return [
                'brands'    => $data['brands'],
                'total_all' => $data['total_all'],
                'filters'   => $filters,
                'filtered'  => $filters['q'] !== '' || $filters['status'] !== 'all',
                'flash'     => flash_take(),
            ];
        });
    }

    /**
     * GET/POST: the Add Brand form.
     *
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    public function create(array $admin, string $method, array $post, string $ip, string $userAgent): array
    {
        return $this->run($admin, function (BrandService $service) use ($admin, $method, $post, $ip, $userAgent): array {
            $view = [
                'values' => ['name' => '', 'slug' => '', 'description' => '', 'display_order' => (string) $service->nextDisplayOrder(), 'status' => 'active'],
                'errors' => [],
            ];
            if ($method !== 'POST') {
                return $view;
            }
            if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                return $this->csrfFailure($admin, $ip, $userAgent) + $view;
            }

            $result = $service->createBrand($post, $admin['id'], $ip, $userAgent);
            if ($result['status'] === BrandService::RESULT_OK) {
                flash_set('success', 'Brand created successfully.');

                return ['redirect' => 'brand-view.php?id=' . $result['id']];
            }

            return $this->invalidForm($view, $result);
        });
    }

    /**
     * GET/POST: the Edit Brand form.
     *
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    public function edit(array $admin, mixed $rawId, string $method, array $post, string $ip, string $userAgent): array
    {
        return $this->run($admin, function (BrandService $service) use ($admin, $rawId, $method, $post, $ip, $userAgent): array {
            $id = BrandValidator::id($rawId);
            $brand = $id === null ? null : $service->find($id);
            if ($brand === null) {
                return $this->notFound();
            }

            $view = ['brand' => $brand, 'errors' => [], 'values' => [
                'name'          => (string) $brand['name'],
                'slug'          => (string) $brand['slug'],
                'description'   => (string) ($brand['description'] ?? ''),
                'display_order' => (string) $brand['display_order'],
                'status'        => (string) $brand['status'],
            ]];
            if ($method !== 'POST') {
                return $view;
            }
            if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                return $this->csrfFailure($admin, $ip, $userAgent) + $view;
            }

            $result = $service->updateBrand($id, $post, $admin['id'], $ip, $userAgent);
            switch ($result['status']) {
                case BrandService::RESULT_OK:
                    flash_set('success', 'Brand updated successfully.');
                    return ['redirect' => 'brand-view.php?id=' . $id];
                case BrandService::RESULT_UNCHANGED:
                    flash_set('info', 'No changes were made.');
                    return ['redirect' => 'brand-view.php?id=' . $id];
                case BrandService::RESULT_NOT_FOUND:
                    return $this->notFound();
            }

            return $this->invalidForm($view, $result);
        });
    }

    /**
     * GET: details of one brand.
     *
     * @return array<string,mixed>
     */
    public function show(array $admin, mixed $rawId): array
    {
        return $this->run($admin, function (BrandService $service) use ($rawId): array {
            $id = BrandValidator::id($rawId);
            $brand = $id === null ? null : $service->find($id);
            if ($brand === null) {
                return $this->notFound();
            }

            return ['brand' => $brand, 'flash' => flash_take()];
        });
    }

    /**
     * POST: activate or deactivate. The target status is explicit, so repeating
     * the request cannot flip the brand back.
     *
     * @param array<string,mixed> $post
     * @return array<string,mixed> either a problem view or ['redirect' => url]
     */
    public function changeStatus(array $admin, array $post, string $ip, string $userAgent): array
    {
        return $this->run($admin, function (BrandService $service) use ($admin, $post, $ip, $userAgent): array {
            if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                return $this->csrfFailure($admin, $ip, $userAgent);
            }

            $id = BrandValidator::id($post['id'] ?? null);
            $target = BrandValidator::targetStatus($post['status'] ?? null);
            $back = $this->statusRedirect($post, $id);

            if ($id === null || $target === null) {
                return $id === null ? $this->notFound() : $this->failure(400, self::MESSAGE_ERROR);
            }

            $result = $service->setStatus($id, $target, $admin['id'], $ip, $userAgent);
            switch ($result['status']) {
                case BrandService::RESULT_OK:
                    flash_set('success', $target === 'active' ? 'Brand activated successfully.' : 'Brand deactivated successfully.');
                    break;
                case BrandService::RESULT_UNCHANGED:
                    flash_set('info', $target === 'active' ? 'The brand is already active.' : 'The brand is already inactive.');
                    break;
                default:
                    flash_set('error', self::MESSAGE_NOT_FOUND);
                    $back = 'brands.php';
            }

            return ['redirect' => $back];
        });
    }

    /**
     * GET: the delete confirmation page ("Delete Toyota? This action cannot be undone.").
     * Reading this page changes nothing.
     *
     * @return array<string,mixed>
     */
    public function confirmDelete(array $admin, mixed $rawId): array
    {
        return $this->run($admin, function (BrandService $service) use ($rawId): array {
            $id = BrandValidator::id($rawId);
            $brand = $id === null ? null : $service->find($id);

            return $brand === null ? $this->notFound() : ['brand' => $brand];
        });
    }

    /**
     * POST: delete after confirmation. The server decides again whether the
     * brand may be deleted, whatever the browser showed.
     *
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    public function delete(array $admin, array $post, string $ip, string $userAgent): array
    {
        return $this->run($admin, function (BrandService $service) use ($admin, $post, $ip, $userAgent): array {
            if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                return $this->csrfFailure($admin, $ip, $userAgent);
            }
            $id = BrandValidator::id($post['id'] ?? null);
            if ($id === null) {
                return $this->notFound();
            }
            // The confirmation page sends this field; a bare forged POST without it is refused.
            if (($post['confirm'] ?? null) !== 'yes') {
                return $this->failure(400, self::MESSAGE_ERROR);
            }

            $result = $service->deleteBrand($id, $admin['id'], $ip, $userAgent);
            switch ($result['status']) {
                case BrandService::RESULT_OK:
                    flash_set('success', 'Brand deleted successfully.');
                    return ['redirect' => 'brands.php'];
                case BrandService::RESULT_REFERENCED:
                    flash_set('error', self::MESSAGE_REFERENCED);
                    return ['redirect' => 'brand-view.php?id=' . $id];
            }
            flash_set('error', self::MESSAGE_NOT_FOUND);

            return ['redirect' => 'brands.php'];
        });
    }

    /* ---------------------------------------------------------------- */

    /**
     * Permission check, service creation and error containment shared by every action.
     *
     * @param callable(BrandService):array<string,mixed> $action
     * @return array<string,mixed>
     */
    private function run(array $admin, callable $action): array
    {
        if (!AuthorizationService::can((string) ($admin['role'] ?? ''), self::PERMISSION)) {
            return $this->failure(403, self::MESSAGE_FORBIDDEN, 'forbidden');
        }

        try {
            $result = $action($this->service ?? BrandService::create());
        } catch (Throwable $e) {
            error_log('Brand management failed: ' . get_class($e) . ': ' . $e->getMessage());

            return $this->failure(500, self::MESSAGE_ERROR);
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
            $pdo = Database::connection();
            (new AuditLogRepository($pdo))->record('CSRF_FAILURE', (int) $admin['id'], null, null,
                'Brand management request rejected: missing or invalid CSRF token', $ip, $userAgent);
        } catch (Throwable $e) {
            error_log('Brand management: could not log CSRF failure: ' . get_class($e));
        }

        return $this->failure(400, self::MESSAGE_CSRF, 'csrf');
    }

    /**
     * Re-shows a form with the cleaned values and field errors.
     *
     * @param array<string,mixed> $view
     * @param array<string,mixed> $result
     * @return array<string,mixed>
     */
    private function invalidForm(array $view, array $result): array
    {
        $values = $result['values'];
        $view['values'] = [
            'name'          => (string) ($values['name'] ?? ''),
            'slug'          => (string) ($values['slug'] ?? ''),
            'description'   => (string) ($values['description'] ?? ''),
            'display_order' => (string) ($values['display_order'] ?? ''),
            'status'        => (string) ($values['status'] ?? 'active'),
        ];
        $view['errors'] = $result['errors'];
        $view['http'] = 200; // like the login form: a typo is not a server error
        $view['formMessage'] = self::MESSAGE_INVALID_FORM;

        return $view;
    }

    /**
     * Where to return after a status change. Built from validated values only
     * (never from a URL supplied by the browser), so it cannot redirect elsewhere.
     */
    private function statusRedirect(array $post, ?int $id): string
    {
        if (($post['from'] ?? null) === 'view' && $id !== null) {
            return 'brand-view.php?id=' . $id;
        }
        $filters = BrandValidator::filters(['q' => $post['q'] ?? null, 'status' => $post['filter_status'] ?? null]);
        $query = array_filter(['q' => $filters['q'], 'status' => $filters['status'] === 'all' ? '' : $filters['status']], 'strlen');

        return 'brands.php' . ($query === [] ? '' : '?' . http_build_query($query));
    }
}
