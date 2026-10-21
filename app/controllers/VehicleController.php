<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/VehicleService.php';
require_once dirname(__DIR__) . '/services/AuthorizationService.php';
require_once dirname(__DIR__) . '/helpers/csrf.php';
require_once dirname(__DIR__) . '/helpers/flash.php';

/**
 * Vehicle Management controller (spec 06_spec_vehicle_managt). Same pattern as
 * BrandController. For every request it:
 *   1. checks the permission (the page has already passed requireAdmin()),
 *   2. for POST: verifies the CSRF token,
 *   3. validates the id and the input,
 *   4. calls VehicleService,
 *   5. returns a view model. Pages render it, or follow its 'redirect'.
 *
 * A view model always has 'http' (status code) and 'problem' (null, or one of
 * forbidden | not_found | csrf | error). Messages shown to the user are fixed
 * sentences; technical detail goes to the server error log.
 */
final class VehicleController
{
    public const PERMISSION = 'vehicles.manage';

    public const MESSAGE_FORBIDDEN = 'Your account does not have permission to manage vehicles.';
    public const MESSAGE_NOT_FOUND = 'The requested vehicle could not be found.';
    public const MESSAGE_CSRF = "We couldn't verify your request. Reload the page and try again.";
    public const MESSAGE_ERROR = 'Something went wrong. Please try again.';
    public const MESSAGE_INVALID_FORM = 'Please correct the highlighted fields.';

    public function __construct(private ?VehicleService $service = null)
    {
    }

    /**
     * GET: the vehicle list with search, filters and pages.
     *
     * @param array<string,mixed> $admin
     * @param array<string,mixed> $query $_GET
     * @return array<string,mixed>
     */
    public function index(array $admin, array $query): array
    {
        return $this->run($admin, function (VehicleService $service) use ($query): array {
            $filters = VehicleValidator::filters($query, $service->maxYear());
            $data = $service->listVehicles($filters);
            $filters['page'] = $data['page'];

            return $data + [
                'filters'  => $filters,
                'filtered' => $filters['q'] !== '' || $filters['brand'] !== null || $filters['status'] !== 'all' || $filters['year'] !== null,
                'flash'    => flash_take(),
            ];
        });
    }

    /**
     * GET/POST: the Add Vehicle form.
     *
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    public function create(array $admin, string $method, array $post, string $ip, string $userAgent): array
    {
        return $this->run($admin, function (VehicleService $service) use ($admin, $method, $post, $ip, $userAgent): array {
            $view = [
                'values'       => self::blankValues(),
                'errors'       => [],
                'brandChoices' => $service->brandChoices(),
                'maxYear'      => $service->maxYear(),
                'currencies'   => [VehicleValidator::DEFAULT_CURRENCY],
            ];
            if ($method !== 'POST') {
                return $view;
            }
            if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                $view['values'] = self::postedValues($post, $view['values']);   // keep what was typed
                return $this->csrfFailure($admin, $ip, $userAgent) + $view;
            }

            $result = $service->createVehicle($post, $admin['id'], $ip, $userAgent);
            if ($result['status'] === VehicleService::RESULT_OK) {
                flash_set('success', 'Vehicle created successfully.');

                return ['redirect' => 'vehicle-view.php?id=' . $result['id']];
            }

            return $this->invalidForm($view, $result, $post);
        });
    }

    /**
     * GET/POST: the Edit Vehicle form.
     *
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    public function edit(array $admin, mixed $rawId, string $method, array $post, string $ip, string $userAgent): array
    {
        return $this->run($admin, function (VehicleService $service) use ($admin, $rawId, $method, $post, $ip, $userAgent): array {
            $id = VehicleValidator::id($rawId);
            $vehicle = $id === null ? null : $service->find($id);
            if ($vehicle === null) {
                return $this->notFound();
            }

            $view = [
                'vehicle'      => $vehicle,
                'values'       => self::valuesFromRecord($vehicle),
                'errors'       => [],
                'brandChoices' => $service->brandChoices((int) $vehicle['brand_id']),
                'maxYear'      => $service->maxYear(),
                'currencies'   => array_values(array_unique([VehicleValidator::DEFAULT_CURRENCY, (string) $vehicle['currency']])),
            ];
            if ($method !== 'POST') {
                return $view;
            }
            if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                $view['values'] = self::postedValues($post, $view['values']);   // keep what was typed
                return $this->csrfFailure($admin, $ip, $userAgent) + $view;
            }

            $result = $service->updateVehicle($id, $post, $admin['id'], $ip, $userAgent);
            switch ($result['status']) {
                case VehicleService::RESULT_OK:
                    flash_set('success', 'Vehicle updated successfully.');
                    return ['redirect' => 'vehicle-view.php?id=' . $id];
                case VehicleService::RESULT_UNCHANGED:
                    flash_set('info', 'No changes were made.');
                    return ['redirect' => 'vehicle-view.php?id=' . $id];
                case VehicleService::RESULT_NOT_FOUND:
                    return $this->notFound();
            }

            return $this->invalidForm($view, $result, $post);
        });
    }

    /**
     * GET: details of one vehicle.
     *
     * @return array<string,mixed>
     */
    public function show(array $admin, mixed $rawId): array
    {
        return $this->run($admin, function (VehicleService $service) use ($rawId): array {
            $id = VehicleValidator::id($rawId);
            $vehicle = $id === null ? null : $service->find($id);

            return $vehicle === null ? $this->notFound() : ['vehicle' => $vehicle, 'images' => $service->imagesOf((int) $vehicle['id']), 'flash' => flash_take()];
        });
    }

    /**
     * POST: change the availability status. The target is explicit, so repeating
     * the request cannot flip the vehicle back.
     *
     * @param array<string,mixed> $post
     * @return array<string,mixed> either a problem view or ['redirect' => url]
     */
    public function changeStatus(array $admin, array $post, string $ip, string $userAgent): array
    {
        return $this->run($admin, function (VehicleService $service) use ($admin, $post, $ip, $userAgent): array {
            if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                return $this->csrfFailure($admin, $ip, $userAgent);
            }

            $id = VehicleValidator::id($post['id'] ?? null);
            $target = VehicleValidator::targetStatus($post['status'] ?? null);
            if ($id === null) {
                return $this->notFound();
            }
            if ($target === null) {
                return $this->failure(400, self::MESSAGE_ERROR);
            }
            $back = $this->statusRedirect($post, $id, $service->maxYear());

            $result = $service->setStatus($id, $target, $admin['id'], $ip, $userAgent);
            switch ($result['status']) {
                case VehicleService::RESULT_OK:
                    flash_set('success', 'Vehicle status updated successfully.');
                    break;
                case VehicleService::RESULT_UNCHANGED:
                    flash_set('info', 'The vehicle already has that status.');
                    break;
                default:
                    flash_set('error', self::MESSAGE_NOT_FOUND);
                    $back = 'vehicles.php';
            }

            return ['redirect' => $back];
        });
    }

    /**
     * GET: the delete confirmation page ("Delete 2024 Toyota Camry? This action cannot be undone.").
     * Reading this page changes nothing.
     *
     * @return array<string,mixed>
     */
    public function confirmDelete(array $admin, mixed $rawId): array
    {
        return $this->run($admin, function (VehicleService $service) use ($rawId): array {
            $id = VehicleValidator::id($rawId);
            $vehicle = $id === null ? null : $service->find($id);

            return $vehicle === null ? $this->notFound() : ['vehicle' => $vehicle, 'reasons' => VehicleService::referenceReasons($vehicle)];
        });
    }

    /**
     * POST: delete after confirmation. The server decides again whether the
     * vehicle may be deleted, whatever the browser showed.
     *
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    public function delete(array $admin, array $post, string $ip, string $userAgent): array
    {
        return $this->run($admin, function (VehicleService $service) use ($admin, $post, $ip, $userAgent): array {
            if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                return $this->csrfFailure($admin, $ip, $userAgent);
            }
            $id = VehicleValidator::id($post['id'] ?? null);
            if ($id === null) {
                return $this->notFound();
            }
            // The confirmation page sends this field; a bare forged POST without it is refused.
            if (($post['confirm'] ?? null) !== 'yes') {
                return $this->failure(400, self::MESSAGE_ERROR);
            }

            $result = $service->deleteVehicle($id, $admin['id'], $ip, $userAgent);
            switch ($result['status']) {
                case VehicleService::RESULT_OK:
                    flash_set('success', 'Vehicle deleted successfully.');
                    return ['redirect' => 'vehicles.php'];
                case VehicleService::RESULT_REFERENCED:
                    flash_set('error', self::referencedMessage($result['reasons']));
                    return ['redirect' => 'vehicle-view.php?id=' . $id];
            }
            flash_set('error', self::MESSAGE_NOT_FOUND);

            return ['redirect' => 'vehicles.php'];
        });
    }

    /**
     * The sentence shown when deletion is refused, naming the kinds of records involved.
     *
     * @param list<string> $reasons shipment | enquiry | image
     */
    public static function referencedMessage(array $reasons): string
    {
        $names = ['shipment' => 'shipment', 'enquiry' => 'enquiry', 'image' => 'image'];
        $parts = array_values(array_intersect_key($names, array_flip($reasons)));
        $text = $parts === [] ? 'shipment' : (count($parts) === 1 ? $parts[0] : implode(', ', array_slice($parts, 0, -1)) . ' and ' . end($parts));

        return 'This vehicle cannot be deleted because it is associated with ' . $text . ' records. Change its status instead.';
    }

    /* ---------------------------------------------------------------- */

    /**
     * Permission check, service creation and error containment shared by every action.
     *
     * @param callable(VehicleService):array<string,mixed> $action
     * @return array<string,mixed>
     */
    private function run(array $admin, callable $action): array
    {
        if (!AuthorizationService::can((string) ($admin['role'] ?? ''), self::PERMISSION)) {
            return $this->failure(403, self::MESSAGE_FORBIDDEN, 'forbidden');
        }

        try {
            $result = $action($this->service ?? VehicleService::create());
        } catch (Throwable $e) {
            error_log('Vehicle management failed: ' . get_class($e) . ': ' . $e->getMessage());

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
            (new AuditLogRepository(Database::connection()))->record('CSRF_FAILURE', (int) $admin['id'], null, null,
                'Vehicle management request rejected: missing or invalid CSRF token', $ip, $userAgent);
        } catch (Throwable $e) {
            error_log('Vehicle management: could not log CSRF failure: ' . get_class($e));
        }

        return $this->failure(400, self::MESSAGE_CSRF, 'csrf');
    }

    /** @return array<string,string> */
    private static function blankValues(): array
    {
        return [
            'brand_id' => '', 'year' => '', 'model' => '', 'trim' => '', 'engine_size' => '', 'mileage' => '', 'mileage_unit' => 'km',
            'transmission' => '', 'fuel_type' => '', 'drive_type' => '', 'price' => '', 'currency' => VehicleValidator::DEFAULT_CURRENCY,
            'description' => '', 'key_features' => '', 'shipping_info' => '', 'duty_included' => '0',
            'availability_status' => 'available', 'is_published' => '0', 'slug' => '',
        ];
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
     * Form values (all strings) from a stored record.
     *
     * @param array<string,mixed> $vehicle
     * @return array<string,string>
     */
    private static function valuesFromRecord(array $vehicle): array
    {
        $text = static fn (string $key): string => (string) ($vehicle[$key] ?? '');

        return [
            'brand_id' => $text('brand_id'), 'year' => $text('year'), 'model' => $text('model'), 'trim' => $text('trim'),
            'engine_size' => $text('engine_size'), 'mileage' => $text('mileage'),
            'mileage_unit' => $text('mileage_unit') !== '' ? $text('mileage_unit') : 'km',
            'transmission' => $text('transmission'), 'fuel_type' => $text('fuel_type'), 'drive_type' => $text('drive_type'),
            'price' => number_format((float) $vehicle['price'], 2, '.', ''), 'currency' => $text('currency'),
            'description' => $text('description'), 'key_features' => implode("\n", $vehicle['features'] ?? []),
            'shipping_info' => $text('shipping_info'), 'duty_included' => $text('duty_included'),
            'availability_status' => $text('availability_status'), 'is_published' => $text('is_published'), 'slug' => $text('slug'),
        ];
    }

    /**
     * Re-shows a form with the cleaned values and field errors.
     *
     * @param array<string,mixed> $view
     * @param array<string,mixed> $result
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    private function invalidForm(array $view, array $result, array $post): array
    {
        $values = [];
        foreach ($result['values'] as $key => $value) {
            $values[$key] = is_array($value) ? implode("\n", $value) : (string) ($value ?? '');
        }
        // Keep exactly what was typed in the features box so nothing is lost while fixing another field.
        if (is_string($post['key_features'] ?? null)) {
            $values['key_features'] = $post['key_features'];
        }
        $view['values'] = $values + $view['values'];
        $view['errors'] = $result['errors'];
        $view['http'] = 200; // like the login form: a typo is not a server error
        $view['formMessage'] = self::MESSAGE_INVALID_FORM;

        return $view;
    }

    /**
     * Where to return after a status change. Built from validated values only
     * (never from a URL supplied by the browser), so it cannot redirect elsewhere.
     */
    private function statusRedirect(array $post, int $id, int $maxYear): string
    {
        if (($post['from'] ?? null) === 'view') {
            return 'vehicle-view.php?id=' . $id;
        }
        $filters = VehicleValidator::filters([
            'q' => $post['q'] ?? null, 'brand' => $post['f_brand'] ?? null, 'status' => $post['f_status'] ?? null,
            'year' => $post['f_year'] ?? null, 'page' => $post['page'] ?? null,
        ], $maxYear);
        $query = array_filter([
            'q'      => $filters['q'],
            'brand'  => (string) ($filters['brand'] ?? ''),
            'status' => $filters['status'] === 'all' ? '' : $filters['status'],
            'year'   => (string) ($filters['year'] ?? ''),
            'page'   => $filters['page'] > 1 ? (string) $filters['page'] : '',
        ], 'strlen');

        return 'vehicles.php' . ($query === [] ? '' : '?' . http_build_query($query));
    }
}
