<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/repositories/VehicleRepository.php';
require_once dirname(__DIR__) . '/repositories/VehicleImageRepository.php';
require_once dirname(__DIR__) . '/repositories/AuditLogRepository.php';
require_once dirname(__DIR__) . '/validation/VehicleValidator.php';
require_once __DIR__ . '/Transaction.php';

/**
 * Business rules for Vehicle Management (spec 06_spec_vehicle_managt):
 * brand relationship (existing, active brand), slug generation and uniqueness,
 * status changes, deletion safety, audit logging.
 *
 * Returns plain data and result codes only: no markup, no HTTP. Audit rows are
 * written in the same transaction as the change they describe.
 *
 * Status rules: the four database statuses (available, reserved, sold,
 * unavailable). No transition is restricted: a vehicle may move between any two
 * (for example a reservation falling through, or a sold vehicle returning).
 *
 * Audit descriptions hold the vehicle label and slug (inventory data) and the
 * names of changed fields, never passwords, tokens, session ids or keys.
 */
class VehicleService
{
    public const RESULT_OK = 'ok';
    public const RESULT_UNCHANGED = 'unchanged';
    public const RESULT_INVALID = 'invalid';
    public const RESULT_NOT_FOUND = 'not_found';
    public const RESULT_REFERENCED = 'referenced';

    public const MESSAGE_DUPLICATE_SLUG = 'This vehicle URL is already in use.';
    private const SLUG_ATTEMPTS = 100;

    public function __construct(
        private PDO $pdo,
        private VehicleRepository $vehicles,
        private AuditLogRepository $audit
    ) {
    }

    public static function create(): self
    {
        $pdo = Database::connection();

        return new self($pdo, new VehicleRepository($pdo), new AuditLogRepository($pdo));
    }

    /**
     * One page of the list plus what the filter bar needs.
     *
     * @param array{q:string,brand:?int,status:string,year:?int,page:int} $filters
     * @return array{vehicles:list<array<string,mixed>>,total:int,total_all:int,page:int,pages:int,from:int,to:int,brands:list<array<string,mixed>>,years:list<int>}
     */
    public function listVehicles(array $filters): array
    {
        $total = $this->vehicles->countMatching($filters);
        $pages = max(1, (int) ceil($total / VehicleValidator::PAGE_SIZE));
        $page = min($filters['page'], $pages);          // a page beyond the end shows the last page
        $offset = ($page - 1) * VehicleValidator::PAGE_SIZE;
        $rows = $total === 0 ? [] : $this->vehicles->search($filters, VehicleValidator::PAGE_SIZE, $offset);

        return [
            'vehicles'  => $rows,
            'total'     => $total,
            'total_all' => $this->vehicles->countAll(),
            'page'      => $page,
            'pages'     => $pages,
            'from'      => $total === 0 ? 0 : $offset + 1,
            'to'        => $offset + count($rows),
            'brands'    => $this->vehicles->allBrands(),
            'years'     => $this->vehicles->years(),
        ];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $vehicle = $this->vehicles->find($id);
        if ($vehicle !== null) {
            $vehicle['features'] = VehicleRepository::decodeFeatures($vehicle['key_features']);
        }

        return $vehicle;
    }

    /**
     * The vehicle's images in gallery order (for the details page), with each thumbnail path.
     *
     * @return list<array<string,mixed>>
     */
    public function imagesOf(int $vehicleId): array
    {
        require_once __DIR__ . '/ImageService.php';
        $images = (new VehicleImageRepository($this->pdo))->forVehicle($vehicleId);
        foreach ($images as &$image) {
            $image['thumb_path'] = ImageService::thumbPath((string) $image['file_path']);
        }

        return $images;
    }

    /**
     * Brands offered in the form's drop-down: active brands only, plus the
     * vehicle's current brand when it was made inactive later (so editing an
     * existing vehicle never silently moves it).
     *
     * @return list<array{id:int|string,name:string,status:string}>
     */
    public function brandChoices(?int $currentBrandId = null): array
    {
        return array_values(array_filter(
            $this->vehicles->allBrands(),
            static fn (array $b): bool => $b['status'] === 'active' || ($currentBrandId !== null && (int) $b['id'] === $currentBrandId)
        ));
    }

    /** Value of the "currently highest sensible year", for validation and the form's max attribute. */
    public function maxYear(): int
    {
        return VehicleValidator::maxYear();
    }

    /**
     * @param array<string,mixed> $input raw browser data
     * @return array{status:string,id:?int,values:array<string,mixed>,errors:array<string,string>}
     */
    public function createVehicle(array $input, int $adminId, string $ip, string $userAgent): array
    {
        $checked = VehicleValidator::validate($input, $this->maxYear());
        $values = $checked['values'];
        $errors = $checked['errors'];

        $brand = null;
        if (!isset($errors['brand_id'])) {
            $brand = $this->vehicles->findBrand((int) $values['brand_id']);
            if ($brand === null || $brand['status'] !== 'active') {
                $errors['brand_id'] = VehicleValidator::MESSAGE_BRAND;   // missing, or inactive brands are not offered for new vehicles
            }
        }
        if (!isset($errors['slug']) && $values['slug'] !== '' && $this->vehicles->slugTaken($values['slug'])) {
            $errors['slug'] = self::MESSAGE_DUPLICATE_SLUG;
        }
        if ($errors !== []) {
            return ['status' => self::RESULT_INVALID, 'id' => null, 'values' => $values, 'errors' => $errors];
        }

        if ($values['slug'] === '') {
            $generated = $this->uniqueSlug($values, (string) $brand['name'], null);
            if ($generated === null) {
                return ['status' => self::RESULT_INVALID, 'id' => null, 'values' => $values, 'errors' => ['slug' => self::MESSAGE_DUPLICATE_SLUG]];
            }
            $values['slug'] = $generated;
        }

        try {
            $id = Transaction::run($this->pdo, function () use ($values, $brand, $adminId, $ip, $userAgent): int {
                $id = $this->vehicles->insert($values);
                $this->audit->record('VEHICLE_CREATED', $adminId, 'vehicle', $id,
                    'Vehicle created: ' . self::label($values, (string) $brand['name']) . ' (' . $values['slug'] . '), ' . $values['availability_status'], $ip, $userAgent);

                return $id;
            });
        } catch (PDOException $e) {
            return $this->failedWrite($e, $values);
        }

        return ['status' => self::RESULT_OK, 'id' => $id, 'values' => $values, 'errors' => []];
    }

    /**
     * @param array<string,mixed> $input raw browser data
     * @return array{status:string,id:?int,values:array<string,mixed>,errors:array<string,string>}
     */
    public function updateVehicle(int $id, array $input, int $adminId, string $ip, string $userAgent): array
    {
        $existing = $this->vehicles->find($id);
        if ($existing === null) {
            return ['status' => self::RESULT_NOT_FOUND, 'id' => $id, 'values' => [], 'errors' => []];
        }

        $currencies = array_values(array_unique([VehicleValidator::DEFAULT_CURRENCY, (string) $existing['currency']]));
        $checked = VehicleValidator::validate($input, $this->maxYear(), $currencies);
        $values = $checked['values'];
        $errors = $checked['errors'];

        $brand = null;
        if (!isset($errors['brand_id'])) {
            $brand = $this->vehicles->findBrand((int) $values['brand_id']);
            $keepsBrand = (int) $values['brand_id'] === (int) $existing['brand_id'];
            if ($brand === null || ($brand['status'] !== 'active' && !$keepsBrand)) {
                $errors['brand_id'] = VehicleValidator::MESSAGE_BRAND;
            }
        }
        if (!isset($errors['slug']) && $values['slug'] === '') {
            $errors['slug'] = 'Enter the vehicle URL.';                 // on edit the slug is always shown, so blank is a mistake
        }
        if (!isset($errors['slug']) && $this->vehicles->slugTaken($values['slug'], $id)) {
            $errors['slug'] = self::MESSAGE_DUPLICATE_SLUG;
        }
        if ($errors !== []) {
            return ['status' => self::RESULT_INVALID, 'id' => $id, 'values' => $values, 'errors' => $errors];
        }

        $changed = $this->changedFields($existing, $values);
        if ($changed === []) {
            return ['status' => self::RESULT_UNCHANGED, 'id' => $id, 'values' => $values, 'errors' => []];
        }

        try {
            $outcome = Transaction::run($this->pdo, function () use ($id, $values, $changed, $existing, $brand, $adminId, $ip, $userAgent): string {
                if ($this->vehicles->find($id, true) === null) {
                    return self::RESULT_NOT_FOUND;                      // deleted by someone else in the meantime
                }
                $this->vehicles->update($id, $values);
                $label = self::label($values, (string) $brand['name']);
                $this->audit->record('VEHICLE_UPDATED', $adminId, 'vehicle', $id,
                    'Vehicle updated: ' . $label . ' (changed: ' . implode(', ', $changed) . ')', $ip, $userAgent);
                if (in_array('availability_status', $changed, true)) {
                    $this->audit->record('VEHICLE_STATUS_CHANGED', $adminId, 'vehicle', $id,
                        'Vehicle status changed: ' . $label . ' ' . $existing['availability_status'] . ' -> ' . $values['availability_status'], $ip, $userAgent);
                }

                return self::RESULT_OK;
            });
        } catch (PDOException $e) {
            return $this->failedWrite($e, $values) + ['id' => $id];
        }

        return ['status' => $outcome, 'id' => $id, 'values' => $values, 'errors' => []];
    }

    /**
     * Sets the availability status explicitly (idempotent: repeating it does not flip it back).
     *
     * @return array{status:string,vehicle:?array<string,mixed>,from:?string}
     */
    public function setStatus(int $id, string $target, int $adminId, string $ip, string $userAgent): array
    {
        if (!in_array($target, VehicleValidator::STATUSES, true)) {
            return ['status' => self::RESULT_INVALID, 'vehicle' => null, 'from' => null];
        }

        return Transaction::run($this->pdo, function () use ($id, $target, $adminId, $ip, $userAgent): array {
            $vehicle = $this->vehicles->find($id, true);
            if ($vehicle === null) {
                return ['status' => self::RESULT_NOT_FOUND, 'vehicle' => null, 'from' => null];
            }
            $from = (string) $vehicle['availability_status'];
            if ($from === $target) {
                return ['status' => self::RESULT_UNCHANGED, 'vehicle' => $vehicle, 'from' => $from];
            }
            $this->vehicles->setStatus($id, $target);
            $this->audit->record('VEHICLE_STATUS_CHANGED', $adminId, 'vehicle', $id,
                'Vehicle status changed: ' . self::label($vehicle, (string) $vehicle['brand_name']) . ' ' . $from . ' -> ' . $target, $ip, $userAgent);

            return ['status' => self::RESULT_OK, 'vehicle' => $vehicle, 'from' => $from];
        });
    }

    /**
     * Deletes a vehicle only when nothing refers to it: no shipment, no enquiry,
     * no image. The check and the delete run in one transaction on the locked row,
     * and the foreign keys (RESTRICT) remain the last guard: a shipment or
     * enquiry created at the same moment makes the database refuse, which is
     * reported as "referenced". Images are checked explicitly because their
     * foreign key would cascade instead of refusing. Nothing is cascaded by this
     * module and foreign-key checks stay on. A sold or withdrawn vehicle should
     * get a status change instead (that is the normal path).
     *
     * @return array{status:string,vehicle:?array<string,mixed>,reasons:list<string>}
     */
    public function deleteVehicle(int $id, int $adminId, string $ip, string $userAgent): array
    {
        $blocked = null;
        $reasons = [];

        try {
            $result = Transaction::run($this->pdo, function () use ($id, $adminId, $ip, $userAgent, &$blocked, &$reasons): array {
                $vehicle = $this->vehicles->find($id, true);
                if ($vehicle === null) {
                    return ['status' => self::RESULT_NOT_FOUND, 'vehicle' => null, 'reasons' => []];
                }
                $reasons = self::referenceReasons($vehicle);
                if ($reasons !== []) {
                    $blocked = $vehicle;
                    return ['status' => self::RESULT_REFERENCED, 'vehicle' => $vehicle, 'reasons' => $reasons];
                }
                $this->vehicles->delete($id);
                $this->audit->record('VEHICLE_DELETED', $adminId, 'vehicle', $id,
                    'Vehicle deleted: ' . self::label($vehicle, (string) $vehicle['brand_name']) . ' (' . $vehicle['slug'] . ')', $ip, $userAgent);

                return ['status' => self::RESULT_OK, 'vehicle' => $vehicle, 'reasons' => []];
            });
        } catch (PDOException $e) {
            if (!in_array((int) ($e->errorInfo[1] ?? 0), [1451, 1452], true)) {
                throw $e;
            }
            $blocked = $this->vehicles->find($id);
            $reasons = $blocked === null ? ['shipment'] : (self::referenceReasons($blocked) ?: ['shipment']);
            $result = ['status' => self::RESULT_REFERENCED, 'vehicle' => $blocked, 'reasons' => $reasons];
        }

        if ($result['status'] === self::RESULT_REFERENCED && $blocked !== null) {
            $this->audit->record('VEHICLE_DELETION_ATTEMPTED', $adminId, 'vehicle', $id,
                'Deletion refused: ' . self::label($blocked, (string) $blocked['brand_name']) . ' is used by ' . implode(', ', $reasons) . ' records', $ip, $userAgent);
        }

        return $result;
    }

    /**
     * Which kinds of records stop a deletion ("shipment", "enquiry", "image").
     *
     * @param array<string,mixed> $vehicle
     * @return list<string>
     */
    public static function referenceReasons(array $vehicle): array
    {
        $reasons = [];
        foreach (['shipment' => 'shipment_count', 'enquiry' => 'enquiry_count', 'image' => 'image_count'] as $name => $column) {
            if ((int) ($vehicle[$column] ?? 0) > 0) {
                $reasons[] = $name;
            }
        }

        return $reasons;
    }

    /**
     * "2024 Toyota Camry XSE": year, brand, model and trim (plain text, not escaped).
     *
     * @param array<string,mixed> $v
     */
    public static function label(array $v, string $brandName): string
    {
        return trim(implode(' ', array_filter([(string) $v['year'], $brandName, (string) $v['model'], (string) ($v['trim'] ?? '')], 'strlen')));
    }

    /* ---------------------------------------------------------------- */

    /**
     * Generates a unique slug from year + brand + model + trim. Two identical
     * units are legitimate stock, so a taken generated slug gets -2, -3 ...
     * (a slug typed by the administrator is never altered: it is rejected if taken).
     *
     * @param array<string,mixed> $values
     */
    private function uniqueSlug(array $values, string $brandName, ?int $exceptId): ?string
    {
        $base = VehicleValidator::slugify(self::label($values, $brandName));
        if ($base === '') {
            return null;
        }
        $candidate = $base;
        for ($n = 2; $n <= self::SLUG_ATTEMPTS + 1; $n++) {
            if (!$this->vehicles->slugTaken($candidate, $exceptId)) {
                return $candidate;
            }
            $suffix = '-' . $n;
            $candidate = rtrim(substr($base, 0, VehicleValidator::SLUG_MAX - strlen($suffix)), '-') . $suffix;
        }

        return null;
    }

    /**
     * Names of the fields whose value differs from the stored record.
     *
     * @param array<string,mixed> $existing
     * @param array<string,mixed> $values
     * @return list<string>
     */
    private function changedFields(array $existing, array $values): array
    {
        $changed = [];
        foreach (['brand_id', 'year', 'model', 'trim', 'engine_size', 'mileage', 'mileage_unit', 'transmission', 'fuel_type', 'drive_type',
            'currency', 'description', 'shipping_info', 'duty_included', 'availability_status', 'is_published', 'slug'] as $field) {
            if ((string) ($existing[$field] ?? '') !== (string) ($values[$field] ?? '')) {
                $changed[] = $field;
            }
        }
        if (number_format((float) $existing['price'], 2, '.', '') !== (string) $values['price']) {
            $changed[] = 'price';
        }
        if (VehicleRepository::decodeFeatures($existing['key_features']) !== $values['key_features']) {
            $changed[] = 'key_features';
        }

        return $changed;
    }

    /**
     * Turns a constraint failure into a field error; anything else is rethrown.
     *
     * @param array<string,mixed> $values
     * @return array{status:string,id:?int,values:array<string,mixed>,errors:array<string,string>}
     */
    private function failedWrite(PDOException $e, array $values): array
    {
        $code = (int) ($e->errorInfo[1] ?? 0);
        if ($code === 1062 && str_contains($e->getMessage(), 'uq_vehicles_slug')) {
            return ['status' => self::RESULT_INVALID, 'id' => null, 'values' => $values, 'errors' => ['slug' => self::MESSAGE_DUPLICATE_SLUG]];
        }
        if ($code === 1452) {
            return ['status' => self::RESULT_INVALID, 'id' => null, 'values' => $values, 'errors' => ['brand_id' => VehicleValidator::MESSAGE_BRAND]];
        }

        throw $e;
    }
}
