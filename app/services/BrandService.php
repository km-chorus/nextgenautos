<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/repositories/BrandRepository.php';
require_once dirname(__DIR__) . '/repositories/AuditLogRepository.php';
require_once dirname(__DIR__) . '/validation/BrandValidator.php';
require_once __DIR__ . '/Transaction.php';

/**
 * Business rules for Brand Management (spec 05_spec_brand_crud.md):
 * duplicate handling, status changes, deletion safety, audit logging.
 *
 * Returns plain data and result codes only: no markup, no HTTP. Audit rows are
 * written in the same transaction as the change they describe, so a change is
 * never recorded without its audit entry (or the reverse).
 *
 * Audit descriptions contain the brand name and slug (public catalogue data),
 * never passwords, tokens, session ids or keys.
 */
class BrandService
{
    public const RESULT_OK = 'ok';
    public const RESULT_UNCHANGED = 'unchanged';
    public const RESULT_INVALID = 'invalid';
    public const RESULT_NOT_FOUND = 'not_found';
    public const RESULT_REFERENCED = 'referenced';
    public const RESULT_ERROR = 'error';

    public const MESSAGE_DUPLICATE_NAME = 'A brand with this name already exists.';
    public const MESSAGE_DUPLICATE_SLUG = 'This slug is already in use.';

    public function __construct(
        private PDO $pdo,
        private BrandRepository $brands,
        private AuditLogRepository $audit
    ) {
    }

    public static function create(): self
    {
        $pdo = Database::connection();

        return new self($pdo, new BrandRepository($pdo), new AuditLogRepository($pdo));
    }

    /**
     * @param array{q:string,status:string} $filters already normalised by BrandValidator::filters()
     * @return array{brands:list<array<string,mixed>>,total_all:int}
     */
    public function listBrands(array $filters): array
    {
        return [
            'brands'    => $this->brands->search($filters['q'], $filters['status']),
            'total_all' => $this->brands->countAll(),
        ];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->brands->find($id);
    }

    public function nextDisplayOrder(): int
    {
        return $this->brands->nextDisplayOrder();
    }

    /**
     * @param array<string,mixed> $input raw browser data
     * @return array{status:string,id:?int,values:array<string,mixed>,errors:array<string,string>}
     */
    public function createBrand(array $input, int $adminId, string $ip, string $userAgent): array
    {
        $checked = BrandValidator::validate($input);
        $values = $checked['values'];
        $errors = $checked['errors'] + $this->duplicateErrors($values, null, $checked['errors']);
        if ($errors !== []) {
            return ['status' => self::RESULT_INVALID, 'id' => null, 'values' => $values, 'errors' => $errors];
        }

        try {
            $id = Transaction::run($this->pdo, function () use ($values, $adminId, $ip, $userAgent): int {
                $id = $this->brands->insert($values);
                $this->audit->record('BRAND_CREATED', $adminId, 'brand', $id,
                    'Brand created: ' . $values['name'] . ' (' . $values['slug'] . '), ' . $values['status'], $ip, $userAgent);

                return $id;
            });
        } catch (PDOException $e) {
            return $this->failedWrite($e, $values);   // duplicate that slipped past the pre-check (concurrent request)
        }

        return ['status' => self::RESULT_OK, 'id' => $id, 'values' => $values, 'errors' => []];
    }

    /**
     * @param array<string,mixed> $input raw browser data
     * @return array{status:string,id:int,values:array<string,mixed>,errors:array<string,string>}
     */
    public function updateBrand(int $id, array $input, int $adminId, string $ip, string $userAgent): array
    {
        $existing = $this->brands->find($id);
        if ($existing === null) {
            return ['status' => self::RESULT_NOT_FOUND, 'id' => $id, 'values' => [], 'errors' => []];
        }

        $checked = BrandValidator::validate($input);
        $values = $checked['values'];
        $errors = $checked['errors'] + $this->duplicateErrors($values, $id, $checked['errors']);
        if ($errors !== []) {
            return ['status' => self::RESULT_INVALID, 'id' => $id, 'values' => $values, 'errors' => $errors];
        }

        $changed = [];
        foreach (['name', 'slug', 'description', 'display_order', 'status'] as $field) {
            if ((string) ($existing[$field] ?? '') !== (string) ($values[$field] ?? '')) {
                $changed[] = $field;
            }
        }
        if ($changed === []) {
            return ['status' => self::RESULT_UNCHANGED, 'id' => $id, 'values' => $values, 'errors' => []];
        }

        try {
            $outcome = Transaction::run($this->pdo, function () use ($id, $values, $changed, $existing, $adminId, $ip, $userAgent): string {
                if ($this->brands->find($id, true) === null) {
                    return self::RESULT_NOT_FOUND;       // deleted by someone else in the meantime
                }
                $this->brands->update($id, $values);
                $this->audit->record('BRAND_UPDATED', $adminId, 'brand', $id,
                    'Brand updated: ' . $existing['name'] . ' (changed: ' . implode(', ', $changed) . ')', $ip, $userAgent);
                // Status changed through the edit form is also an activation/deactivation.
                if (in_array('status', $changed, true)) {
                    $this->audit->record($values['status'] === 'active' ? 'BRAND_ACTIVATED' : 'BRAND_DEACTIVATED', $adminId, 'brand', $id,
                        'Brand ' . ($values['status'] === 'active' ? 'activated' : 'deactivated') . ': ' . $values['name'], $ip, $userAgent);
                }

                return self::RESULT_OK;
            });
        } catch (PDOException $e) {
            return $this->failedWrite($e, $values) + ['id' => $id];
        }

        return ['status' => $outcome, 'id' => $id, 'values' => $values, 'errors' => []];
    }

    /**
     * Sets the status explicitly (idempotent: a repeated click does not flip it back).
     *
     * @return array{status:string,brand:?array<string,mixed>}
     */
    public function setStatus(int $id, string $target, int $adminId, string $ip, string $userAgent): array
    {
        if (!in_array($target, BrandValidator::STATUSES, true)) {
            return ['status' => self::RESULT_INVALID, 'brand' => null];
        }

        return Transaction::run($this->pdo, function () use ($id, $target, $adminId, $ip, $userAgent): array {
            $brand = $this->brands->find($id, true);
            if ($brand === null) {
                return ['status' => self::RESULT_NOT_FOUND, 'brand' => null];
            }
            if ($brand['status'] === $target) {
                return ['status' => self::RESULT_UNCHANGED, 'brand' => $brand];
            }
            $this->brands->setStatus($id, $target);
            $this->audit->record($target === 'active' ? 'BRAND_ACTIVATED' : 'BRAND_DEACTIVATED', $adminId, 'brand', $id,
                'Brand ' . ($target === 'active' ? 'activated' : 'deactivated') . ': ' . $brand['name'], $ip, $userAgent);

            return ['status' => self::RESULT_OK, 'brand' => $brand];
        });
    }

    /**
     * Deletes a brand only when no vehicle references it. The check and the
     * delete run in one transaction on the locked row, and the foreign key
     * (RESTRICT) is the final guard: a vehicle added at the same moment makes
     * the database refuse, which is reported as "referenced". Nothing is
     * cascaded, nulled or force-deleted and foreign-key checks stay on.
     *
     * @return array{status:string,brand:?array<string,mixed>}
     */
    public function deleteBrand(int $id, int $adminId, string $ip, string $userAgent): array
    {
        $blocked = null;

        try {
            $result = Transaction::run($this->pdo, function () use ($id, $adminId, $ip, $userAgent, &$blocked): array {
                $brand = $this->brands->find($id, true);
                if ($brand === null) {
                    return ['status' => self::RESULT_NOT_FOUND, 'brand' => null];
                }
                if ((int) $brand['vehicle_count'] > 0) {
                    $blocked = $brand;
                    return ['status' => self::RESULT_REFERENCED, 'brand' => $brand];
                }
                $this->brands->delete($id);
                $this->audit->record('BRAND_DELETED', $adminId, 'brand', $id,
                    'Brand deleted: ' . $brand['name'] . ' (' . $brand['slug'] . ')', $ip, $userAgent);

                return ['status' => self::RESULT_OK, 'brand' => $brand];
            });
        } catch (PDOException $e) {
            if (!$this->isForeignKeyViolation($e)) {
                throw $e;
            }
            $result = ['status' => self::RESULT_REFERENCED, 'brand' => $this->brands->find($id)];
            $blocked = $result['brand'];
        }

        if ($result['status'] === self::RESULT_REFERENCED && $blocked !== null) {
            $this->audit->record('BRAND_DELETION_ATTEMPTED', $adminId, 'brand', $id,
                'Deletion refused: ' . $blocked['name'] . ' is used by vehicles', $ip, $userAgent);
        }

        return $result;
    }

    /**
     * Name/slug uniqueness errors (skipping fields that already failed validation).
     *
     * @param array<string,mixed> $values
     * @param array<string,string> $existingErrors
     * @return array<string,string>
     */
    private function duplicateErrors(array $values, ?int $exceptId, array $existingErrors): array
    {
        $errors = [];
        if (!isset($existingErrors['name']) && $this->brands->nameTaken($values['name'], $exceptId)) {
            $errors['name'] = self::MESSAGE_DUPLICATE_NAME;
        }
        if (!isset($existingErrors['slug']) && $values['slug'] !== '' && $this->brands->slugTaken($values['slug'], $exceptId)) {
            $errors['slug'] = self::MESSAGE_DUPLICATE_SLUG;
        }

        return $errors;
    }

    /**
     * Turns a database constraint failure into a field error; anything else is rethrown.
     *
     * @param array<string,mixed> $values
     * @return array{status:string,id:?int,values:array<string,mixed>,errors:array<string,string>}
     */
    private function failedWrite(PDOException $e, array $values): array
    {
        if ($this->isDuplicateKey($e)) {
            $message = (string) $e->getMessage();
            $field = str_contains($message, 'uq_brands_slug') ? 'slug' : 'name';

            return [
                'status' => self::RESULT_INVALID, 'id' => null, 'values' => $values,
                'errors' => [$field => $field === 'slug' ? self::MESSAGE_DUPLICATE_SLUG : self::MESSAGE_DUPLICATE_NAME],
            ];
        }

        throw $e;
    }

    private function isDuplicateKey(PDOException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1062;
    }

    private function isForeignKeyViolation(PDOException $e): bool
    {
        return in_array((int) ($e->errorInfo[1] ?? 0), [1451, 1452], true);
    }
}
