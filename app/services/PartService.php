<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/repositories/PartRepository.php';
require_once dirname(__DIR__) . '/repositories/AuditLogRepository.php';
require_once dirname(__DIR__) . '/validation/PartValidator.php';
require_once dirname(__DIR__) . '/validation/ImageValidator.php';
require_once __DIR__ . '/ImageService.php';
require_once __DIR__ . '/Transaction.php';

/**
 * Business rules for the parts catalogue admin (spec 16, phase 1): categories and makes must exist (and be active for new
 * choices), slugs are generated and never reused, every change is audited in the same transaction, archiving replaces
 * deletion, and photos go through the shared image pipeline (validated, re-encoded to WebP, random names).
 *
 * Files are not transactional: new photos are processed first, the database change follows, and a file is only removed
 * once the database no longer refers to it. Any failure removes the files this request wrote.
 *
 * Returns plain data and result codes; no markup, no HTTP.
 */
class PartService
{
    public const RESULT_OK = 'ok';
    public const RESULT_UNCHANGED = 'unchanged';
    public const RESULT_INVALID = 'invalid';
    public const RESULT_NOT_FOUND = 'not_found';

    /** Part photos may be smaller than vehicle photos (close-ups taken with a phone), but not thumbnails. */
    public const PHOTO_MIN_LONG = 600;
    public const PHOTO_MIN_SHORT = 450;
    public const ERROR_PHOTO_PROCESS = 'The photo could not be processed. Please try another image.';
    public const ERROR_PHOTO_FORMAT = 'Use a JPEG, PNG or WebP photo.';

    public function __construct(private PDO $pdo, private PartRepository $parts, private AuditLogRepository $audit, private ImageService $images)
    {
    }

    public static function create(): self
    {
        $pdo = Database::connection();

        return new self($pdo, new PartRepository($pdo), new AuditLogRepository($pdo), new ImageService(null, ImageService::KIND_PART));
    }

    /** @param array{q:string,category:?int,brand:?int,published:string,stock:string,page:int} $filters */
    public function listParts(array $filters): array
    {
        $total = $this->parts->countMatching($filters);
        $pages = max(1, (int) ceil($total / PartValidator::PAGE_SIZE));
        $page = min($filters['page'], $pages);
        $offset = ($page - 1) * PartValidator::PAGE_SIZE;
        $rows = $total === 0 ? [] : $this->parts->search($filters, PartValidator::PAGE_SIZE, $offset);
        $fitments = $this->parts->fitmentsFor(array_map(static fn (array $r): int => (int) $r['id'], $rows));
        foreach ($rows as &$row) {
            $row['fits'] = self::fitsSummary($fitments[(int) $row['id']] ?? []);
        }
        unset($row);

        return [
            'parts' => $rows, 'total' => $total, 'total_all' => $this->parts->countAll(), 'page' => $page, 'pages' => $pages,
            'from' => $total === 0 ? 0 : $offset + 1, 'to' => $offset + count($rows),
            'categories' => $this->parts->categories(), 'brands' => $this->parts->brands(),
        ];
    }

    /** @return array<string,mixed>|null the part with 'fitments' and 'images' */
    public function find(int $id): ?array
    {
        $part = $this->parts->find($id);
        if ($part === null) {
            return null;
        }
        $part['fitments'] = $this->parts->fitmentsFor([$id])[$id] ?? [];
        $part['images'] = $this->parts->images($id);

        return $part;
    }

    /** Categories for the form: active ones, plus the part's current one if it was deactivated later. */
    public function categoryChoices(?int $currentId = null): array
    {
        return array_values(array_filter($this->parts->categories(),
            static fn (array $c): bool => $c['status'] === 'active' || $c['id'] === $currentId));
    }

    /** Makes for the fitment rows: active brands, plus any inactive brand the part already uses. @param list<int> $currentIds */
    public function brandChoices(array $currentIds = []): array
    {
        return array_values(array_filter($this->parts->brands(),
            static fn (array $b): bool => $b['status'] === 'active' || in_array($b['id'], $currentIds, true)));
    }

    /**
     * @param array<string,mixed> $post
     * @param list<array{name:string,tmp_name:string,error:int,size:int}> $photos
     * @return array{status:string,id:?int,values:array<string,mixed>,errors:array<string,string>,fitments:list<array<string,string>>}
     */
    public function createPart(array $post, array $photos, int $adminId, string $ip, string $userAgent, bool $requireHttpUpload = true): array
    {
        $checked = PartValidator::validate($post);
        $errors = $checked['errors'] + $this->referenceErrors($checked, null, []);
        if (count($photos) > PartValidator::PHOTOS_MAX) {
            $errors['photos'] = 'Add at most ' . PartValidator::PHOTOS_MAX . ' photos.';
        }
        $errors += $this->photoErrors($photos, $adminId, $ip, $userAgent, $requireHttpUpload);
        $fail = ['id' => null, 'values' => $checked['values'], 'fitments' => $checked['fitments']];
        if ($errors !== []) {
            return ['status' => self::RESULT_INVALID, 'errors' => $errors] + $fail;
        }

        $stored = $this->storePhotos($photos, $errors);
        if ($stored === null) {
            return ['status' => self::RESULT_INVALID, 'errors' => $errors] + $fail;
        }

        $values = $checked['values'];
        try {
            $id = Transaction::run($this->pdo, function () use ($values, $checked, $stored, $adminId, $ip, $userAgent): int {
                $slug = $this->uniqueSlug($values['name'], $values['part_number']);
                $id = $this->parts->insert($values, $slug);
                $this->parts->replaceFitments($id, PartValidator::fitmentRecords($checked['fitments']));
                foreach ($stored as $n => $photo) {
                    $this->parts->insertImage($id, $photo['path'], $photo['original'], $values['name'], $n === 0);
                }
                $this->audit->record('PART_CREATED', $adminId, 'part', $id, 'Part created: ' . $values['name'] . ' (' . $slug . ')'
                    . ($values['is_published'] === 1 ? ', published' : ', not published') . ', ' . count($stored) . ' photo(s)', $ip, $userAgent);

                return $id;
            });
        } catch (Throwable $e) {
            $this->discard($stored);
            throw $e;
        }

        return ['status' => self::RESULT_OK, 'id' => $id, 'values' => $values, 'errors' => [], 'fitments' => $checked['fitments']];
    }

    /**
     * @param array<string,mixed> $post
     * @param list<array{name:string,tmp_name:string,error:int,size:int}> $photos new photos to add
     * @return array{status:string,id:int,values:array<string,mixed>,errors:array<string,string>,fitments:list<array<string,string>>}
     */
    public function updatePart(int $id, array $post, array $photos, int $adminId, string $ip, string $userAgent, bool $requireHttpUpload = true): array
    {
        $existing = $this->find($id);
        $checked = PartValidator::validate($post);
        $fail = ['id' => $id, 'values' => $checked['values'], 'fitments' => $checked['fitments']];
        if ($existing === null) {
            return ['status' => self::RESULT_NOT_FOUND, 'errors' => []] + $fail;
        }
        $currentBrands = array_map(static fn (array $f): int => $f['brand_id'], $existing['fitments']);
        $errors = $checked['errors'] + $this->referenceErrors($checked, (int) $existing['category_id'], $currentBrands);
        if (count($existing['images']) + count($photos) > PartValidator::PHOTOS_MAX) {
            $errors['photos'] = 'A part can have at most ' . PartValidator::PHOTOS_MAX . ' photos. Remove one before adding more.';
        }
        $errors += $this->photoErrors($photos, $adminId, $ip, $userAgent, $requireHttpUpload);
        if ($errors !== []) {
            return ['status' => self::RESULT_INVALID, 'errors' => $errors] + $fail;
        }

        $values = $checked['values'];
        $changed = [];
        foreach (['category_id', 'name', 'part_number', 'condition', 'price', 'currency', 'stock_status', 'description', 'is_published'] as $field) {
            $old = $field === 'price' ? number_format((float) $existing['price'], 2, '.', '') : (string) ($existing[$field] ?? '');
            if ($old !== (string) ($values[$field] ?? '')) {
                $changed[] = $field;
            }
        }
        $newFitments = PartValidator::fitmentRecords($checked['fitments']);
        $oldFitments = array_map(static fn (array $f): array => ['brand_id' => $f['brand_id'], 'model' => $f['model'], 'year_from' => $f['year_from'], 'year_to' => $f['year_to']], $existing['fitments']);
        if ($newFitments !== $oldFitments) {
            $changed[] = 'compatible vehicles';
        }
        if ($changed === [] && $photos === []) {
            return ['status' => self::RESULT_UNCHANGED, 'errors' => []] + $fail;
        }

        $stored = $this->storePhotos($photos, $errors);
        if ($stored === null) {
            return ['status' => self::RESULT_INVALID, 'errors' => $errors] + $fail;
        }
        try {
            $outcome = Transaction::run($this->pdo, function () use ($id, $values, $changed, $newFitments, $stored, $existing, $adminId, $ip, $userAgent): string {
                if ($this->parts->find($id, true) === null) {
                    return self::RESULT_NOT_FOUND;   // archived by someone else in the meantime
                }
                $this->parts->update($id, $values);
                if (in_array('compatible vehicles', $changed, true)) {
                    $this->parts->replaceFitments($id, $newFitments);
                }
                $hasPrimary = $existing['images'] !== [];
                foreach ($stored as $n => $photo) {
                    $this->parts->insertImage($id, $photo['path'], $photo['original'], $values['name'], !$hasPrimary && $n === 0);
                }
                $this->audit->record('PART_UPDATED', $adminId, 'part', $id, 'Part updated: ' . $existing['name']
                    . ($changed === [] ? '' : ' (changed: ' . implode(', ', $changed) . ')') . ($stored === [] ? '' : ', ' . count($stored) . ' photo(s) added'), $ip, $userAgent);

                return self::RESULT_OK;
            });
        } catch (Throwable $e) {
            $this->discard($stored);
            throw $e;
        }
        if ($outcome !== self::RESULT_OK) {
            $this->discard($stored);
        }

        return ['status' => $outcome, 'errors' => []] + $fail;
    }

    /** 'publish' | 'unpublish' | 'in_stock' | 'out_of_stock' | 'archive'. @return string a RESULT_* code */
    public function changeState(int $id, string $action, int $adminId, string $ip, string $userAgent): string
    {
        return Transaction::run($this->pdo, function () use ($id, $action, $adminId, $ip, $userAgent): string {
            $part = $this->parts->find($id, true);
            if ($part === null) {
                return self::RESULT_NOT_FOUND;
            }
            [$event, $text] = match ($action) {
                'publish'      => ['PART_PUBLISHED', 'Part published'],
                'unpublish'    => ['PART_UNPUBLISHED', 'Part unpublished'],
                'in_stock'     => ['PART_STOCK_CHANGED', 'Part marked in stock'],
                'out_of_stock' => ['PART_STOCK_CHANGED', 'Part marked out of stock'],
                'archive'      => ['PART_ARCHIVED', 'Part archived'],
            };
            match ($action) {
                'publish', 'unpublish' => $this->parts->setPublished($id, $action === 'publish'),
                'in_stock', 'out_of_stock' => $this->parts->setStock($id, $action),
                'archive' => $this->parts->archive($id),
            };
            $this->audit->record($event, $adminId, 'part', $id, $text . ': ' . $part['name'] . ' (' . $part['slug'] . ')', $ip, $userAgent);

            return self::RESULT_OK;
        });
    }

    /** 'remove' or 'primary' for one photo of one part (the photo must belong to that part). @return string a RESULT_* code */
    public function changePhoto(int $partId, int $imageId, string $action, int $adminId, string $ip, string $userAgent): string
    {
        $removed = null;
        $outcome = Transaction::run($this->pdo, function () use ($partId, $imageId, $action, $adminId, $ip, $userAgent, &$removed): string {
            if ($this->parts->find($partId, true) === null) {
                return self::RESULT_NOT_FOUND;
            }
            $image = $this->parts->findImage($partId, $imageId, true);
            if ($image === null) {
                return self::RESULT_NOT_FOUND;
            }
            if ($action === 'primary') {
                if ($image['is_primary'] === 1) {
                    return self::RESULT_UNCHANGED;
                }
                $this->parts->setPrimaryImage($partId, $imageId);
                $this->audit->record('PART_PHOTO_PRIMARY', $adminId, 'part', $partId, 'Main photo changed for part ' . $partId, $ip, $userAgent);

                return self::RESULT_OK;
            }
            $this->parts->deleteImage($partId, $imageId);
            if ($image['is_primary'] === 1) {
                $this->parts->promoteFirstImage($partId);
            }
            $this->audit->record('PART_PHOTO_REMOVED', $adminId, 'part', $partId, 'Photo removed from part ' . $partId, $ip, $userAgent);
            $removed = $image['file_path'];

            return self::RESULT_OK;
        });
        if ($removed !== null) {
            $this->removeFiles($removed);   // only now: the database no longer refers to it
        }

        return $outcome;
    }

    // ---- categories -------------------------------------------------------------------------------------------------

    /** @return list<array<string,mixed>> */
    public function categories(): array
    {
        return $this->parts->categories();
    }

    /**
     * Adds ($id null) or edits a category. The slug follows the name; a category in use can be deactivated (no new parts)
     * but never deleted.
     *
     * @return array{status:string,id:?int,values:array<string,mixed>,errors:array<string,string>}
     */
    public function saveCategory(?int $id, array $post, int $adminId, string $ip, string $userAgent): array
    {
        $checked = PartValidator::category($post);
        $values = $checked['values'];
        $errors = $checked['errors'];
        $existing = $id === null ? null : $this->parts->findCategory($id);
        if ($id !== null && $existing === null) {
            return ['status' => self::RESULT_NOT_FOUND, 'id' => $id, 'values' => $values, 'errors' => []];
        }
        $slug = PartValidator::slugify($values['name']);
        if ($errors === [] && $this->parts->categoryTaken($values['name'], $slug, $id)) {
            $errors['name'] = 'A category with this name already exists.';
        }
        if ($errors !== []) {
            return ['status' => self::RESULT_INVALID, 'id' => $id, 'values' => $values, 'errors' => $errors];
        }
        if ($existing !== null && $existing['name'] === $values['name'] && (int) $existing['display_order'] === $values['display_order'] && $existing['status'] === $values['status']) {
            return ['status' => self::RESULT_UNCHANGED, 'id' => $id, 'values' => $values, 'errors' => []];
        }

        $newId = Transaction::run($this->pdo, function () use ($id, $values, $slug, $adminId, $ip, $userAgent): int {
            if ($id === null) {
                $id = $this->parts->insertCategory($values['name'], $slug, $values['display_order'], $values['status']);
                $this->audit->record('PART_CATEGORY_CREATED', $adminId, 'part_category', $id, 'Part category created: ' . $values['name'], $ip, $userAgent);
            } else {
                $this->parts->updateCategory($id, $values['name'], $slug, $values['display_order'], $values['status']);
                $this->audit->record('PART_CATEGORY_UPDATED', $adminId, 'part_category', $id, 'Part category updated: ' . $values['name'] . ' (' . $values['status'] . ')', $ip, $userAgent);
            }

            return $id;
        });

        return ['status' => self::RESULT_OK, 'id' => $newId, 'values' => $values, 'errors' => []];
    }

    // ---- helpers ----------------------------------------------------------------------------------------------------

    /**
     * "Toyota Corolla 2014–2018": one readable line per fitment. Model null = "all models"; open years read naturally.
     *
     * @param array{brand_name:string,model:?string,year_from:?int,year_to:?int} $f
     */
    public static function fitmentLabel(array $f): string
    {
        $label = $f['brand_name'] . ' ' . ($f['model'] ?? 'all models');
        $from = $f['year_from'];
        $to = $f['year_to'];
        $years = match (true) {
            $from !== null && $to !== null => $from === $to ? (string) $from : $from . '–' . $to,
            $from !== null => $from . ' onwards',
            $to !== null => 'up to ' . $to,
            default => '',
        };

        return trim($label . ' ' . $years);
    }

    /** "Toyota Corolla 2014–2018 and 2 more" (admin list). */
    public static function fitsSummary(array $fitments): string
    {
        if ($fitments === []) {
            return 'No compatible vehicles entered';
        }

        return self::fitmentLabel($fitments[0]) . (count($fitments) > 1 ? ' and ' . (count($fitments) - 1) . ' more' : '');
    }

    /**
     * Category and makes must exist; new choices must be active (an inactive one already on this part may stay).
     *
     * @param list<int> $currentBrands
     * @return array<string,string>
     */
    private function referenceErrors(array $checked, ?int $currentCategory, array $currentBrands): array
    {
        $errors = [];
        $categoryId = $checked['values']['category_id'];
        if ($categoryId !== null && !isset($checked['errors']['category_id'])) {
            $category = $this->parts->findCategory($categoryId);
            if ($category === null || ($category['status'] !== 'active' && $categoryId !== $currentCategory)) {
                $errors['category_id'] = 'Choose a type from the list.';
            }
        }
        $brands = [];
        foreach ($this->parts->brands() as $b) {
            $brands[$b['id']] = $b['status'];
        }
        foreach ($checked['fitments'] as $n => $row) {
            if (isset($checked['errors']['fitment_' . $n])) {
                continue;
            }
            $brandId = (int) $row['brand_id'];
            if (!isset($brands[$brandId]) || ($brands[$brandId] !== 'active' && !in_array($brandId, $currentBrands, true))) {
                $errors['fitment_' . $n] = 'Choose a make from the list.';
            }
        }

        return $errors;
    }

    /** A slug from the name (and part number), made unique with -2, -3 ... @throws RuntimeException when none is free */
    private function uniqueSlug(string $name, ?string $partNumber): string
    {
        $base = PartValidator::slugify($name . ($partNumber !== null ? ' ' . $partNumber : ''));
        if ($base === '') {
            $base = 'part';
        }
        $base = substr($base, 0, PartValidator::SLUG_MAX - 5);
        for ($n = 1; $n <= 100; $n++) {
            $candidate = rtrim($n === 1 ? $base : $base . '-' . $n, '-');
            if (!$this->parts->slugTaken($candidate)) {
                return $candidate;
            }
        }
        throw new RuntimeException('No free part slug.');
    }

    /**
     * @param list<array{name:string,tmp_name:string,error:int,size:int}> $photos
     * @return array<string,string> 'photos' => the first problem found (the audit log records each rejection)
     */
    private function photoErrors(array $photos, int $adminId, string $ip, string $userAgent, bool $requireHttpUpload): array
    {
        if ($photos === []) {
            return [];
        }
        $missing = ImageService::missingRequirements();
        if ($missing !== []) {
            error_log('Part photo refused: missing ' . implode(', ', $missing));

            return ['photos' => 'Photo uploads are not available on this server yet: the PHP component(s) ' . implode(', ', $missing) . ' are not enabled.'];
        }
        foreach ($photos as $photo) {
            $reason = null;
            $message = null;
            $uploadError = ImageValidator::uploadErrorMessage((int) $photo['error'], ImageService::serverUploadLimit());
            if ($uploadError !== null) {
                [$reason, $message] = ['upload_error_' . (int) $photo['error'], $uploadError];
            } elseif ($requireHttpUpload && !is_uploaded_file((string) $photo['tmp_name'])) {
                [$reason, $message] = ['not_an_http_upload', ImageValidator::ERROR_UPLOAD];
            } else {
                try {
                    $check = ImageValidator::inspect((string) $photo['tmp_name'], self::PHOTO_MIN_LONG, self::PHOTO_MIN_SHORT);
                } catch (Throwable $e) {
                    error_log('Part photo: inspecting the file failed: ' . get_class($e));
                    $check = ['ok' => false, 'reason' => 'inspect_failed', 'error' => self::ERROR_PHOTO_PROCESS];
                }
                if (!$check['ok']) {
                    $reason = (string) $check['reason'];
                    $message = str_starts_with($reason, 'format_') || $reason === 'animated' ? self::ERROR_PHOTO_FORMAT : (string) $check['error'];
                }
            }
            if ($reason !== null) {
                try {
                    $this->audit->record('PART_PHOTO_REJECTED', $adminId, 'part', null, 'Photo rejected: ' . $reason, $ip, $userAgent);
                } catch (Throwable $e) {
                    error_log('Part photo: could not log a rejection: ' . get_class($e));
                }
                $name = ImageValidator::displayName((string) $photo['name']);

                return ['photos' => ($name !== '' ? $name . ': ' : '') . $message];
            }
        }

        return [];
    }

    /**
     * Processes every photo; on any failure removes what was written and returns null (with $errors['photos'] set).
     *
     * @return list<array{path:string,original:?string}>|null
     */
    private function storePhotos(array $photos, array &$errors): ?array
    {
        $stored = [];
        foreach ($photos as $photo) {
            try {
                $result = $this->images->process((string) $photo['tmp_name']);
            } catch (RuntimeException $e) {
                $this->discard($stored);
                $errors['photos'] = self::ERROR_PHOTO_PROCESS;

                return null;
            }
            $original = ImageValidator::displayName((string) $photo['name']);
            $stored[] = ['path' => $result['path'], 'original' => $original === '' ? null : $original];
        }

        return $stored;
    }

    /** Removes files written for a save that did not complete (never throws). */
    private function discard(array $stored): void
    {
        foreach ($stored as $photo) {
            try {
                $this->images->delete($photo['path']);
            } catch (Throwable $e) {
                error_log('Part photo: cleanup of an unused file failed.');
            }
        }
    }

    private function removeFiles(string $path): void
    {
        try {
            $result = $this->images->delete($path);
            if ($result !== 'deleted') {
                error_log('Part photo: the stored file was ' . $result . ' when its record was removed.');
            }
        } catch (Throwable $e) {
            error_log('Part photo: a file could not be deleted after its record was removed: ' . get_class($e));
        }
    }
}
