<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/PartService.php';
require_once dirname(__DIR__) . '/services/AuthorizationService.php';
require_once dirname(__DIR__) . '/helpers/csrf.php';
require_once dirname(__DIR__) . '/helpers/flash.php';
require_once dirname(__DIR__) . '/helpers/diagnostics.php';

/**
 * Parts catalogue admin controller (spec 16, phase 1). Same pattern as VehicleController and TeamController: permission
 * check, CSRF on every POST, ids and input validated, PartService does the work, a view model comes back (or a
 * 'redirect'). A view model always has 'http' and 'problem' (null | forbidden | not_found | csrf | error). Messages are
 * fixed sentences; technical detail goes to the server log.
 */
final class PartController
{
    public const PERMISSION = 'parts.manage';

    public const MESSAGE_FORBIDDEN = 'Your account does not have permission to manage parts.';
    public const MESSAGE_NOT_FOUND = 'The requested part could not be found.';
    public const MESSAGE_CSRF = "We couldn't verify your request. Reload the page and try again.";
    public const MESSAGE_ERROR = 'Something went wrong. Please try again.';
    public const MESSAGE_INVALID_FORM = 'Please correct the highlighted fields.';
    public const MESSAGE_TOO_LARGE = 'The photos were too large to upload together. Please choose fewer or smaller photos.';

    /** Blank fitment rows offered on the form (more can be added with "Add another vehicle"). */
    public const BLANK_FITMENT_ROWS = 2;
    /** Blank part-number rows offered on the form (spec 27). */
    public const BLANK_NUMBER_ROWS = 2;

    public function __construct(private ?PartService $service = null)
    {
    }

    /** GET: the parts list with search, filters and pages. */
    public function index(array $admin, array $query): array
    {
        return $this->run($admin, function (PartService $service) use ($query): array {
            $filters = PartValidator::filters($query);
            $data = $service->listParts($filters);
            $filters['page'] = $data['page'];

            return $data + [
                'filters'  => $filters,
                'filtered' => $filters['q'] !== '' || $filters['category'] !== null || $filters['brand'] !== null || $filters['published'] !== 'all' || $filters['stock'] !== 'all'
                    || $filters['price'] !== 'all' || $filters['fits'] !== 'all',
                'flash'    => flash_take(),
            ];
        });
    }

    /** GET/POST: Add Part. */
    public function create(array $admin, string $method, array $post, array $files, array $server, string $ip, string $userAgent, bool $requireHttpUpload = true): array
    {
        return $this->run($admin, function (PartService $service) use ($admin, $method, $post, $files, $server, $ip, $userAgent, $requireHttpUpload): array {
            $view = [
                'part' => null, 'values' => self::blankValues(), 'numbers' => [], 'fitments' => [], 'errors' => [],
                'categoryChoices' => $service->categoryChoices(), 'brandChoices' => $service->brandChoices(), 'copySources' => $service->copySources(),
            ];
            if ($method !== 'POST') {
                return $view;
            }
            if ($tooLarge = $this->tooLarge($post, $files, $server, 'part-create.php')) {
                return $tooLarge;
            }
            if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                return $this->csrfFailure($admin, $ip, $userAgent) + $this->keepTyped($view, $post);
            }
            if (($post['form_action'] ?? null) === 'copy_fitments') {
                return $this->copied($service, $view, $post, null);
            }
            $result = $service->createPart($post, self::photos($files), (int) $admin['id'], $ip, $userAgent, $requireHttpUpload);
            if ($result['status'] === PartService::RESULT_OK) {
                flash_set('success', 'Part created successfully.');

                return ['redirect' => 'part-edit.php?id=' . $result['id']];
            }

            return $this->invalidForm($view, $result);
        });
    }

    /** GET/POST: Edit Part (also shows its photos). */
    public function edit(array $admin, mixed $rawId, string $method, array $post, array $files, array $server, string $ip, string $userAgent, bool $requireHttpUpload = true): array
    {
        return $this->run($admin, function (PartService $service) use ($admin, $rawId, $method, $post, $files, $server, $ip, $userAgent, $requireHttpUpload): array {
            $id = PartValidator::id($rawId);
            $part = $id === null ? null : $service->find($id);
            if ($part === null) {
                return $this->notFound();
            }
            $view = [
                'part' => $part, 'values' => self::valuesFromRecord($part), 'numbers' => $part['numbers'], 'errors' => [],
                'copySources' => $service->copySources($id),
                'fitments' => array_map(static fn (array $f): array => ['brand_id' => (string) $f['brand_id'], 'model' => (string) ($f['model'] ?? ''),
                    'year_from' => (string) ($f['year_from'] ?? ''), 'year_to' => (string) ($f['year_to'] ?? '')], $part['fitments']),
                'categoryChoices' => $service->categoryChoices((int) $part['category_id']),
                'brandChoices' => $service->brandChoices(array_map(static fn (array $f): int => $f['brand_id'], $part['fitments'])),
                'flash' => $method === 'POST' ? null : flash_take(),
            ];
            if ($method !== 'POST') {
                return $view;
            }
            if ($tooLarge = $this->tooLarge($post, $files, $server, 'part-edit.php?id=' . $id)) {
                return $tooLarge;
            }
            if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                return $this->csrfFailure($admin, $ip, $userAgent) + $this->keepTyped($view, $post);
            }
            if (($post['form_action'] ?? null) === 'copy_fitments') {
                return $this->copied($service, $view, $post, $id);
            }
            $result = $service->updatePart($id, $post, self::photos($files), (int) $admin['id'], $ip, $userAgent, $requireHttpUpload);
            switch ($result['status']) {
                case PartService::RESULT_OK:
                    flash_set('success', 'Part updated successfully.');

                    return ['redirect' => 'part-edit.php?id=' . $id];
                case PartService::RESULT_UNCHANGED:
                    flash_set('info', 'No changes were made.');

                    return ['redirect' => 'part-edit.php?id=' . $id];
                case PartService::RESULT_NOT_FOUND:
                    return $this->notFound();
            }

            return $this->invalidForm($view, $result);
        });
    }

    /** POST: publish / unpublish / stock / archive. Returns to the list (keeping its filters) or to the edit page. */
    public function changeState(array $admin, array $post, string $ip, string $userAgent): array
    {
        return $this->run($admin, function (PartService $service) use ($admin, $post, $ip, $userAgent): array {
            if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                return $this->csrfFailure($admin, $ip, $userAgent);
            }
            $id = PartValidator::id($post['id'] ?? null);
            $action = is_string($post['action'] ?? null) ? $post['action'] : '';
            if ($id === null || !in_array($action, ['publish', 'unpublish', 'in_stock', 'out_of_stock', 'archive'], true)) {
                return $this->notFound();
            }
            if ($service->changeState($id, $action, (int) $admin['id'], $ip, $userAgent) === PartService::RESULT_NOT_FOUND) {
                return $this->notFound();
            }
            flash_set('success', match ($action) {
                'publish' => 'The part is now published on the website.',
                'unpublish' => 'The part is no longer shown on the website.',
                'archive' => 'The part was archived. It is no longer shown anywhere.',
                default => 'Stock status updated.',
            });

            return ['redirect' => $action !== 'archive' && ($post['from'] ?? null) === 'edit' ? 'part-edit.php?id=' . $id : self::listUrl($post)];
        });
    }

    /** POST: remove a photo, or make it the main photo. */
    public function changePhoto(array $admin, array $post, string $ip, string $userAgent): array
    {
        return $this->run($admin, function (PartService $service) use ($admin, $post, $ip, $userAgent): array {
            if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                return $this->csrfFailure($admin, $ip, $userAgent);
            }
            $partId = PartValidator::id($post['id'] ?? null);
            $imageId = PartValidator::id($post['image'] ?? null);
            $action = in_array($post['action'] ?? null, ['remove', 'primary'], true) ? (string) $post['action'] : null;
            if ($partId === null || $imageId === null || $action === null) {
                return $this->notFound();
            }
            $outcome = $service->changePhoto($partId, $imageId, $action, (int) $admin['id'], $ip, $userAgent);
            if ($outcome === PartService::RESULT_NOT_FOUND) {
                return $this->notFound();
            }
            flash_set('success', $action === 'remove' ? 'Photo removed.' : 'Main photo updated.');

            return ['redirect' => 'part-edit.php?id=' . $partId . '#photos'];
        });
    }

    /** GET/POST: part categories (add, rename, reorder, activate/deactivate). */
    public function categories(array $admin, string $method, array $post, string $ip, string $userAgent): array
    {
        return $this->run($admin, function (PartService $service) use ($admin, $method, $post, $ip, $userAgent): array {
            $view = ['categories' => $service->categories(), 'errors' => [], 'values' => ['name' => '', 'display_order' => '', 'status' => 'active'],
                'editing' => null, 'flash' => $method === 'POST' ? null : flash_take()];
            if ($method !== 'POST') {
                return $view;
            }
            if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                return $this->csrfFailure($admin, $ip, $userAgent) + $view;
            }
            $id = ($post['id'] ?? '') === '' ? null : PartValidator::id($post['id']);
            if (($post['id'] ?? '') !== '' && $id === null) {
                return $this->notFound();
            }
            $result = $service->saveCategory($id, $post, (int) $admin['id'], $ip, $userAgent);
            switch ($result['status']) {
                case PartService::RESULT_OK:
                    flash_set('success', $id === null ? 'Category added.' : 'Category updated.');

                    return ['redirect' => 'part-categories.php'];
                case PartService::RESULT_UNCHANGED:
                    flash_set('info', 'No changes were made.');

                    return ['redirect' => 'part-categories.php'];
                case PartService::RESULT_NOT_FOUND:
                    return $this->notFound();
            }
            $view['errors'] = $result['errors'];
            $view['values'] = ['name' => (string) ($post['name'] ?? ''), 'display_order' => (string) ($post['display_order'] ?? ''), 'status' => $result['values']['status']];
            $view['editing'] = $id;
            $view['formMessage'] = self::MESSAGE_INVALID_FORM;

            return $view;
        });
    }

    /**
     * The uploaded photos from $_FILES['photos'] (multiple). "No file chosen" slots are dropped; odd shapes become an
     * invalid entry so the service reports them instead of silently ignoring them.
     *
     * @return list<array{name:string,tmp_name:string,error:int,size:int}>
     */
    public static function photos(array $files): array
    {
        $f = $files['photos'] ?? null;
        if (!is_array($f) || !isset($f['error'])) {
            return [];
        }
        if (!is_array($f['error']) || !is_array($f['name'] ?? null) || !is_array($f['tmp_name'] ?? null)) {
            return [['name' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_TMP_DIR, 'size' => 0]];
        }
        $out = [];
        foreach ($f['error'] as $i => $error) {
            if ((int) $error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = [
                'name' => is_string($f['name'][$i] ?? null) ? $f['name'][$i] : '',
                'tmp_name' => is_string($f['tmp_name'][$i] ?? null) ? $f['tmp_name'][$i] : '',
                'error' => is_int($error) || ctype_digit((string) $error) ? (int) $error : UPLOAD_ERR_NO_TMP_DIR,
                'size' => (int) ($f['size'][$i] ?? 0),
            ];
        }

        return $out;
    }

    /** The list address with the filters the browser sent back, rebuilt from validated values only (never a URL from the browser). */
    private static function listUrl(array $post): string
    {
        $f = PartValidator::filters(['q' => $post['q'] ?? null, 'category' => $post['f_category'] ?? null, 'brand' => $post['f_brand'] ?? null,
            'published' => $post['f_published'] ?? null, 'stock' => $post['f_stock'] ?? null, 'price' => $post['f_price'] ?? null,
            'fits' => $post['f_fits'] ?? null, 'page' => $post['page'] ?? null]);
        $query = self::filterQuery($f);

        return 'parts.php' . ($query === [] ? '' : '?' . http_build_query($query));
    }

    /**
     * The list's query string from validated filters (only values that differ from "all" / page 1).
     *
     * @param array<string,mixed> $f PartValidator::filters() result
     * @return array<string,string>
     */
    public static function filterQuery(array $f): array
    {
        return array_filter(['q' => $f['q'], 'category' => (string) ($f['category'] ?? ''), 'brand' => (string) ($f['brand'] ?? ''),
            'published' => $f['published'] === 'all' ? '' : $f['published'], 'stock' => $f['stock'] === 'all' ? '' : $f['stock'],
            'price' => $f['price'] === 'all' ? '' : $f['price'], 'fits' => $f['fits'] === 'all' ? '' : $f['fits'],
            'page' => $f['page'] > 1 ? (string) $f['page'] : ''], 'strlen');
    }

    /** @return array<string,string> */
    private static function blankValues(): array
    {
        return ['category_id' => '', 'name' => '', 'condition' => '', 'price' => '', 'currency' => PartValidator::DEFAULT_CURRENCY,
            'stock_status' => '', 'description' => '', 'is_published' => '0'];
    }

    /** @return array<string,string> */
    private static function valuesFromRecord(array $part): array
    {
        $t = static fn (string $k): string => (string) ($part[$k] ?? '');

        return ['category_id' => $t('category_id'), 'name' => $t('name'), 'condition' => $t('condition'),
            'price' => $part['price'] === null ? '' : number_format((float) $part['price'], 2, '.', ''), 'currency' => $t('currency'), 'stock_status' => $t('stock_status'),
            'description' => $t('description'), 'is_published' => $t('is_published')];
    }

    /** The form again with exactly what was typed (after a CSRF failure nothing was validated). */
    private function keepTyped(array $view, array $post): array
    {
        foreach ($view['values'] as $key => $current) {
            if (is_string($post[$key] ?? null)) {
                $view['values'][$key] = $post[$key];
            }
        }
        $view['fitments'] = PartValidator::fitments($post['fitments'] ?? [])[0];
        $view['numbers'] = PartValidator::partNumbers($post['part_numbers'] ?? [])[0];

        return $view;
    }

    /**
     * "Copy compatible vehicles from another part" (spec 27): the form comes back with everything typed, plus the copied
     * rows. Nothing is saved and no photo is processed.
     */
    private function copied(PartService $service, array $view, array $post, ?int $exceptId): array
    {
        $view = $this->keepTyped($view, $post);
        $copy = $service->copyFitments($post['copy_from'] ?? null, $view['fitments'], $exceptId);
        $view['fitments'] = $copy['rows'];
        $view['copyNotice'] = $copy['error'] !== null && $copy['added'] === 0
            ? ['type' => 'error', 'text' => $copy['error']]
            : ['type' => $copy['error'] === null ? 'success' : 'warning',
               'text' => $copy['error'] ?? ($copy['added'] === 0
                   ? 'Every compatible vehicle of ' . $copy['source'] . ' is already in the list.'
                   : $copy['added'] . ' compatible vehicle(s) copied from ' . $copy['source'] . '. Check them, then save.')];
        if (($view['part'] ?? null) !== null) {
            $view['flash'] = null;
        }
        $view['copySelected'] = (string) ($post['copy_from'] ?? '');

        return $view;
    }

    private function invalidForm(array $view, array $result): array
    {
        $values = [];
        foreach ($result['values'] as $key => $value) {
            if ($key === 'part_numbers') {
                continue;
            }
            $values[$key] = (string) ($value ?? '');
        }
        $view['values'] = $values + $view['values'];
        $view['numbers'] = $result['values']['part_numbers'] ?? [];
        $view['fitments'] = $result['fitments'];
        $view['errors'] = $result['errors'];
        $view['http'] = 200;
        $view['formMessage'] = self::MESSAGE_INVALID_FORM;

        return $view;
    }

    private function tooLarge(array $post, array $files, array $server, string $back): ?array
    {
        if ($post === [] && $files === [] && (int) ($server['CONTENT_LENGTH'] ?? 0) > 0) {
            flash_set('error', self::MESSAGE_TOO_LARGE);

            return ['redirect' => $back];
        }

        return null;
    }

    /** @param callable(PartService):array<string,mixed> $action */
    private function run(array $admin, callable $action): array
    {
        if (!AuthorizationService::can((string) ($admin['role'] ?? ''), self::PERMISSION)) {
            return $this->failure(403, self::MESSAGE_FORBIDDEN, 'forbidden');
        }
        try {
            $result = $action($this->service ?? PartService::create());
        } catch (Throwable $e) {
            error_log('Parts management failed: ' . get_class($e) . ': ' . $e->getMessage());

            return $this->failure(500, self::MESSAGE_ERROR) + ['detail' => development_error_detail($e)];
        }

        return $result + ['http' => 200, 'problem' => null];
    }

    private function failure(int $http, string $message, string $problem = 'error'): array
    {
        return ['http' => $http, 'problem' => $problem, 'message' => $message];
    }

    private function notFound(): array
    {
        return $this->failure(404, self::MESSAGE_NOT_FOUND, 'not_found');
    }

    private function csrfFailure(array $admin, string $ip, string $userAgent): array
    {
        try {
            (new AuditLogRepository(Database::connection()))->record('CSRF_FAILURE', (int) $admin['id'], null, null,
                'Parts management request rejected: missing or invalid CSRF token', $ip, $userAgent);
        } catch (Throwable $e) {
            error_log('Parts management: could not log CSRF failure: ' . get_class($e));
        }

        return $this->failure(400, self::MESSAGE_CSRF, 'csrf');
    }
}
