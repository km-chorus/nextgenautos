<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/repositories/VehicleRepository.php';
require_once dirname(__DIR__) . '/repositories/VehicleImageRepository.php';
require_once dirname(__DIR__) . '/repositories/AuditLogRepository.php';
require_once dirname(__DIR__) . '/validation/ImageValidator.php';
require_once __DIR__ . '/ImageService.php';
require_once __DIR__ . '/Transaction.php';

/**
 * Business rules for vehicle images (spec 07_spec_vehicle_image.md): the pipeline
 * validate -> process -> store -> database record -> audit, primary image,
 * ordering, alt text and deletion.
 *
 * Returns plain data and result codes only (no markup, no HTTP).
 *
 * Principles:
 *  - an image is only ever reached through ITS vehicle (vehicle id AND image id), so
 *    knowing an image id is not enough to touch another vehicle's image;
 *  - every file in an upload is judged on its own; one bad file never blocks or
 *    hides the others, and every failure is reported by name;
 *  - the database and the filesystem cannot share a transaction, so a file written
 *    for a record that then fails to save is removed, and on delete the record goes
 *    first (a leftover file is a harmless orphan, a record pointing at a missing file
 *    would be a broken page);
 *  - the database guarantees "at most one primary image per vehicle" with a unique key;
 *    this class changes primaries inside a transaction on the locked vehicle row.
 *
 * Audit text holds ids, counts and short reason codes. Original file names are stored
 * as metadata on the image row, not in the audit trail.
 */
class VehicleImageService
{
    public const MAX_FILES_PER_REQUEST = 10;
    public const THROTTLE_LIMIT = 60;
    public const THROTTLE_WINDOW_MINUTES = 10;
    public const ALT_MAX = 255;

    public const RESULT_OK = 'ok';
    public const RESULT_UNCHANGED = 'unchanged';
    public const RESULT_INVALID = 'invalid';
    public const RESULT_NOT_FOUND = 'not_found';
    public const RESULT_THROTTLED = 'throttled';

    public const ERROR_PROCESS = 'The image could not be processed. Please try another image.';
    public const ERROR_SAVE = 'The image could not be saved. Please try again.';
    public const MESSAGE_ORDER = 'The image order could not be saved. Reload the page and try again.';

    public function __construct(
        private PDO $pdo,
        private VehicleRepository $vehicles,
        private VehicleImageRepository $images,
        private AuditLogRepository $audit,
        private ImageService $files
    ) {
    }

    public static function create(): self
    {
        $pdo = Database::connection();

        return new self($pdo, new VehicleRepository($pdo), new VehicleImageRepository($pdo), new AuditLogRepository($pdo), new ImageService());
    }

    /**
     * The vehicle and its gallery for the management page and the details page.
     *
     * @return array{vehicle:array<string,mixed>,images:list<array<string,mixed>>,primary_id:?int}|null null when the vehicle does not exist
     */
    public function gallery(int $vehicleId): ?array
    {
        $vehicle = $this->vehicles->find($vehicleId);
        if ($vehicle === null) {
            return null;
        }
        $images = $this->images->forVehicle($vehicleId);
        $primary = null;
        foreach ($images as &$image) {
            $image['thumb_path'] = ImageService::thumbPath((string) $image['file_path']);
            if ((int) $image['is_primary'] === 1) {
                $primary = (int) $image['id'];
            }
        }
        unset($image);

        return ['vehicle' => $vehicle, 'images' => $images, 'primary_id' => $primary];
    }

    /**
     * One image of one vehicle (for the delete confirmation page).
     *
     * @return array{vehicle:array<string,mixed>,image:array<string,mixed>,images_count:int,next:?array<string,mixed>}|null
     */
    public function imageOfVehicle(int $vehicleId, int $imageId): ?array
    {
        $gallery = $this->gallery($vehicleId);
        if ($gallery === null) {
            return null;
        }
        foreach ($gallery['images'] as $i => $image) {
            if ((int) $image['id'] === $imageId) {
                // The image promoted if this one is the primary and the administrator agrees: the next one, or the previous one when this is the last.
                $next = $gallery['images'][$i + 1] ?? $gallery['images'][$i - 1] ?? null;

                return ['vehicle' => $gallery['vehicle'], 'image' => $image, 'images_count' => count($gallery['images']), 'next' => $next];
            }
        }

        return null;
    }

    /**
     * Processes the files of one upload request.
     *
     * @param list<array{name:string,tmp_name:string,error:int,size:int}> $files normalised $_FILES entries
     * @param bool $requireHttpUpload true in production: each file must really be an HTTP upload (tests pass false)
     * @return array{status:string,results:list<array{name:string,ok:bool,message:?string,id:?int}>,primary_assigned:bool}
     */
    public function upload(int $vehicleId, array $files, int $adminId, string $ip, string $userAgent, bool $requireHttpUpload = true): array
    {
        $none = static fn (string $status, array $results = []): array => ['status' => $status, 'results' => $results, 'primary_assigned' => false];

        if ($this->vehicles->find($vehicleId) === null) {
            return $none(self::RESULT_NOT_FOUND);
        }
        if ($files === []) {
            return $none(self::RESULT_INVALID, [['name' => '', 'ok' => false, 'message' => 'No file was selected.', 'id' => null]]);
        }
        if (count($files) > self::MAX_FILES_PER_REQUEST) {
            $this->audit->record('VEHICLE_IMAGE_UPLOAD_REJECTED', $adminId, 'vehicle', $vehicleId, 'Upload refused: too many files in one request (' . count($files) . ')', $ip, $userAgent);

            return $none(self::RESULT_INVALID, [['name' => '', 'ok' => false, 'message' => 'Upload at most ' . self::MAX_FILES_PER_REQUEST . ' images at a time.', 'id' => null]]);
        }
        if ($this->audit->countRecent('admin', $adminId, ['VEHICLE_IMAGE_UPLOADED', 'VEHICLE_IMAGE_UPLOAD_REJECTED'], self::THROTTLE_WINDOW_MINUTES) >= self::THROTTLE_LIMIT) {
            return $none(self::RESULT_THROTTLED);
        }

        $results = [];
        $primaryAssigned = false;
        foreach ($files as $file) {
            $name = ImageValidator::displayName((string) $file['name']);
            $outcome = $this->uploadOne($vehicleId, $file, $name, $adminId, $ip, $userAgent, $requireHttpUpload);
            $results[] = ['name' => $name, 'ok' => $outcome['ok'], 'message' => $outcome['message'], 'id' => $outcome['id']];
            $primaryAssigned = $primaryAssigned || $outcome['primary'];
        }

        return ['status' => self::RESULT_OK, 'results' => $results, 'primary_assigned' => $primaryAssigned];
    }

    /**
     * Makes one image the vehicle's primary image and demotes the previous one, atomically.
     *
     * @return array{status:string}
     */
    public function setPrimary(int $vehicleId, int $imageId, int $adminId, string $ip, string $userAgent): array
    {
        return Transaction::run($this->pdo, function () use ($vehicleId, $imageId, $adminId, $ip, $userAgent): array {
            if ($this->vehicles->find($vehicleId, true) === null) {
                return ['status' => self::RESULT_NOT_FOUND];
            }
            $images = $this->images->forVehicle($vehicleId, true);
            $target = $this->pick($images, $imageId);
            if ($target === null) {
                return ['status' => self::RESULT_NOT_FOUND];
            }
            if ((int) $target['is_primary'] === 1) {
                return ['status' => self::RESULT_UNCHANGED];
            }
            $previous = null;
            foreach ($images as $image) {
                if ((int) $image['is_primary'] === 1) {
                    $previous = (int) $image['id'];
                }
            }
            $this->images->clearPrimary($vehicleId);
            $this->images->markPrimary($vehicleId, $imageId);
            $this->audit->record('VEHICLE_IMAGE_PRIMARY_CHANGED', $adminId, 'vehicle_image', $imageId,
                'Primary image of vehicle ' . $vehicleId . ' set to image ' . $imageId . ($previous !== null ? ' (was image ' . $previous . ')' : ' (there was none)'), $ip, $userAgent);

            return ['status' => self::RESULT_OK];
        });
    }

    /**
     * Saves an image's alt text (empty clears it; the page then falls back to the vehicle's own label).
     *
     * @return array{status:string,error:?string}
     */
    public function updateAlt(int $vehicleId, int $imageId, mixed $alt, int $adminId, string $ip, string $userAgent): array
    {
        $clean = self::cleanAlt($alt);
        if ($clean['error'] !== null) {
            return ['status' => self::RESULT_INVALID, 'error' => $clean['error']];
        }

        return Transaction::run($this->pdo, function () use ($vehicleId, $imageId, $clean, $adminId, $ip, $userAgent): array {
            $image = $this->images->findForVehicle($vehicleId, $imageId, true);
            if ($image === null || $this->vehicles->find($vehicleId) === null) {
                return ['status' => self::RESULT_NOT_FOUND, 'error' => null];
            }
            if ((string) ($image['alt_text'] ?? '') === (string) ($clean['value'] ?? '')) {
                return ['status' => self::RESULT_UNCHANGED, 'error' => null];
            }
            $this->images->updateAlt($vehicleId, $imageId, $clean['value']);
            $this->audit->record('VEHICLE_IMAGE_ALT_UPDATED', $adminId, 'vehicle_image', $imageId,
                'Alt text ' . ($clean['value'] === null ? 'cleared' : 'updated') . ' on image ' . $imageId . ' of vehicle ' . $vehicleId, $ip, $userAgent);

            return ['status' => self::RESULT_OK, 'error' => null];
        });
    }

    /** Photo types: the two tabs of the public gallery. */
    public const TYPES = ['exterior' => 'Exterior', 'interior' => 'Interior', 'damage' => 'Damage'];   // damage: spec 23 (accident vehicles)

    /**
     * Marks a photo as an exterior or interior photo (the gallery tab it appears under on the vehicle page).
     *
     * @return array{status:string}
     */
    public function updateType(int $vehicleId, int $imageId, mixed $type, int $adminId, string $ip, string $userAgent): array
    {
        if (!is_string($type) || !isset(self::TYPES[$type])) {
            return ['status' => self::RESULT_INVALID];
        }

        return Transaction::run($this->pdo, function () use ($vehicleId, $imageId, $type, $adminId, $ip, $userAgent): array {
            $image = $this->images->findForVehicle($vehicleId, $imageId, true);
            if ($image === null || $this->vehicles->find($vehicleId) === null) {
                return ['status' => self::RESULT_NOT_FOUND];
            }
            if ((string) ($image['photo_type'] ?? 'exterior') === $type) {
                return ['status' => self::RESULT_UNCHANGED];
            }
            $this->images->updateType($vehicleId, $imageId, $type);
            $this->audit->record('VEHICLE_IMAGE_TYPE_UPDATED', $adminId, 'vehicle_image', $imageId,
                'Image ' . $imageId . ' of vehicle ' . $vehicleId . ' marked ' . $type, $ip, $userAgent);

            return ['status' => self::RESULT_OK];
        });
    }

    /**
     * Saves a complete new gallery order. The submitted list is not trusted: it must contain
     * exactly this vehicle's image ids, each once, and nothing else.
     *
     * @param mixed $order list of image ids as submitted by the browser
     * @return array{status:string}
     */
    public function reorder(int $vehicleId, mixed $order, int $adminId, string $ip, string $userAgent): array
    {
        $ids = self::parseIdList($order);
        if ($ids === null) {
            return ['status' => self::RESULT_INVALID];
        }

        return Transaction::run($this->pdo, function () use ($vehicleId, $ids, $adminId, $ip, $userAgent): array {
            if ($this->vehicles->find($vehicleId, true) === null) {
                return ['status' => self::RESULT_NOT_FOUND];
            }
            $current = $this->images->forVehicle($vehicleId, true);
            $currentIds = array_map(static fn (array $i): int => (int) $i['id'], $current);
            $sorted = $ids;
            sort($sorted);
            $expected = $currentIds;
            sort($expected);
            if ($sorted !== $expected) {
                return ['status' => self::RESULT_INVALID];          // missing, extra, foreign or repeated ids
            }
            if ($ids === $currentIds) {
                return ['status' => self::RESULT_UNCHANGED];
            }
            foreach ($ids as $position => $imageId) {
                $this->images->setOrder($vehicleId, $imageId, $position + 1);
            }
            $this->audit->record('VEHICLE_IMAGE_REORDERED', $adminId, 'vehicle', $vehicleId,
                'Gallery of vehicle ' . $vehicleId . ' reordered (' . count($ids) . ' images)', $ip, $userAgent);

            return ['status' => self::RESULT_OK];
        });
    }

    /**
     * Deletes an image: the record first (in a transaction that also closes the gap in the
     * order and, only if asked, promotes the next image), then the files.
     *
     * @param bool $promoteNext when the primary image is deleted: make the next image in the gallery the primary one
     * @return array{status:string,was_primary:bool,promoted:?int,file:?string}
     */
    public function delete(int $vehicleId, int $imageId, bool $promoteNext, int $adminId, string $ip, string $userAgent): array
    {
        $outcome = Transaction::run($this->pdo, function () use ($vehicleId, $imageId, $promoteNext, $adminId, $ip, $userAgent): array {
            if ($this->vehicles->find($vehicleId, true) === null) {
                return ['status' => self::RESULT_NOT_FOUND, 'was_primary' => false, 'promoted' => null, 'path' => null];
            }
            $images = $this->images->forVehicle($vehicleId, true);
            $target = $this->pick($images, $imageId);
            if ($target === null) {
                return ['status' => self::RESULT_NOT_FOUND, 'was_primary' => false, 'promoted' => null, 'path' => null];
            }
            $wasPrimary = (int) $target['is_primary'] === 1;
            $remaining = array_values(array_filter($images, static fn (array $i): bool => (int) $i['id'] !== $imageId));

            $this->images->delete($vehicleId, $imageId);
            foreach ($remaining as $position => $image) {                 // close the gap: 1..n again
                if ((int) $image['display_order'] !== $position + 1) {
                    $this->images->setOrder($vehicleId, (int) $image['id'], $position + 1);
                }
            }
            $promoted = null;
            if ($wasPrimary && $promoteNext && $remaining !== []) {
                // Deterministic rule shown to the administrator: the image that follows the deleted one
                // in the gallery (or the one before it when the deleted one was last).
                $index = 0;
                foreach ($images as $i => $image) {
                    if ((int) $image['id'] === $imageId) {
                        $index = min($i, count($remaining) - 1);
                    }
                }
                $promoted = (int) $remaining[$index]['id'];
                $this->images->markPrimary($vehicleId, $promoted);
            }
            $this->audit->record('VEHICLE_IMAGE_DELETED', $adminId, 'vehicle_image', $imageId,
                'Image ' . $imageId . ' of vehicle ' . $vehicleId . ' deleted' . ($wasPrimary ? ' (it was the primary image' . ($promoted !== null ? '; image ' . $promoted . ' is now primary' : '; the vehicle now has no primary image') . ')' : ''), $ip, $userAgent);

            return ['status' => self::RESULT_OK, 'was_primary' => $wasPrimary, 'promoted' => $promoted, 'path' => (string) $target['file_path']];
        });

        $file = null;
        if ($outcome['status'] === self::RESULT_OK) {
            try {
                $file = $this->files->delete((string) $outcome['path']);
            } catch (RuntimeException $e) {
                $file = 'error';
            }
            if ($file === 'missing' || $file === 'invalid' || $file === 'error') {
                error_log('Vehicle image ' . $imageId . ': stored file was ' . $file . ' when its record was deleted.');
                $this->audit->record('VEHICLE_IMAGE_FILE_MISSING', $adminId, 'vehicle_image', $imageId,
                    'Image ' . $imageId . ' of vehicle ' . $vehicleId . ': file was ' . $file . ' when the record was deleted', $ip, $userAgent);
            }
        }

        return ['status' => $outcome['status'], 'was_primary' => $outcome['was_primary'], 'promoted' => $outcome['promoted'], 'file' => $file];
    }

    /**
     * Cleans alt text: trimmed, single line, at most 255 characters, no control characters.
     *
     * @return array{value:?string,error:?string}
     */
    public static function cleanAlt(mixed $alt): array
    {
        if ($alt === null) {
            return ['value' => null, 'error' => null];
        }
        if (!is_string($alt) || strlen($alt) > 4000 || !mb_check_encoding($alt, 'UTF-8')) {
            return ['value' => null, 'error' => 'Enter valid alt text.'];
        }
        $text = trim(preg_replace('/\s+/u', ' ', $alt) ?? '');
        if ($text === '') {
            return ['value' => null, 'error' => null];
        }
        if (preg_match('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u', $text) === 1) {
            return ['value' => null, 'error' => 'Alt text cannot contain control or hidden characters.'];
        }
        if (mb_strlen($text, 'UTF-8') > self::ALT_MAX) {
            return ['value' => null, 'error' => 'Alt text must be ' . self::ALT_MAX . ' characters or fewer.'];
        }

        return ['value' => $text, 'error' => null];
    }

    /**
     * The alt text to render: the stored text, or the vehicle's own label when none was entered.
     * Nothing is invented (no "front view" guesses): the label states only what the record says.
     */
    public static function altFor(array $image, string $vehicleLabel): string
    {
        $alt = trim((string) ($image['alt_text'] ?? ''));

        return $alt !== '' ? $alt : $vehicleLabel;
    }

    /**
     * A submitted list of ids: a plain array of digit strings, at most 500, or null when malformed.
     *
     * @return list<int>|null
     */
    public static function parseIdList(mixed $value): ?array
    {
        if (!is_array($value) || $value === [] || count($value) > 500 || !array_is_list($value)) {
            return null;
        }
        $ids = [];
        foreach ($value as $item) {
            if (!is_string($item) || preg_match('/^[1-9][0-9]{0,9}$/', $item) !== 1) {
                return null;
            }
            $ids[] = (int) $item;
        }

        return $ids;
    }

    /* ---------------------------------------------------------------- */

    /**
     * One file of an upload, start to finish.
     *
     * @param array{name:string,tmp_name:string,error:int,size:int} $file
     * @return array{ok:bool,message:?string,id:?int,primary:bool}
     */
    private function uploadOne(int $vehicleId, array $file, string $name, int $adminId, string $ip, string $userAgent, bool $requireHttpUpload): array
    {
        $reject = function (string $reason, string $message) use ($vehicleId, $adminId, $ip, $userAgent): array {
            try {
                $this->audit->record('VEHICLE_IMAGE_UPLOAD_REJECTED', $adminId, 'vehicle', $vehicleId, 'Image rejected: ' . $reason, $ip, $userAgent);
            } catch (Throwable $e) {
                error_log('Vehicle image: could not log a rejection: ' . get_class($e));
            }

            return ['ok' => false, 'message' => $message, 'id' => null, 'primary' => false];
        };

        $uploadError = ImageValidator::uploadErrorMessage((int) $file['error'], ImageService::serverUploadLimit());
        if ($uploadError !== null) {
            return $reject('upload_error_' . (int) $file['error'], $uploadError);
        }
        $tmp = (string) $file['tmp_name'];
        if ($requireHttpUpload && !is_uploaded_file($tmp)) {
            return $reject('not_an_http_upload', ImageValidator::ERROR_UPLOAD);   // a path the browser should never be able to name
        }

        try {
            $check = ImageValidator::inspect($tmp);
        } catch (Throwable $e) {
            // An unexpected failure while inspecting ONE file must not turn the whole request into an error page.
            error_log('Vehicle image: inspecting a file failed: ' . get_class($e) . ': ' . $e->getMessage());

            return $reject('inspect_failed', self::ERROR_PROCESS);
        }
        if (!$check['ok']) {
            return $reject((string) $check['reason'], (string) $check['error']);
        }

        try {
            $stored = $this->files->process($tmp);
        } catch (RuntimeException $e) {
            return $reject('processing_failed', self::ERROR_PROCESS);
        }

        try {
            $result = Transaction::run($this->pdo, function () use ($vehicleId, $stored, $name, $adminId, $ip, $userAgent): array {
                if ($this->vehicles->find($vehicleId, true) === null) {
                    throw new RuntimeException('vehicle disappeared');
                }
                $existing = $this->images->forVehicle($vehicleId, true);
                $hasPrimary = array_filter($existing, static fn (array $i): bool => (int) $i['is_primary'] === 1) !== [];
                $id = $this->images->insert($vehicleId, $stored['path'], $name, null, $this->images->nextOrder($vehicleId), !$hasPrimary);
                $this->audit->record('VEHICLE_IMAGE_UPLOADED', $adminId, 'vehicle_image', $id,
                    'Image ' . $id . ' added to vehicle ' . $vehicleId . ' (' . $stored['width'] . 'x' . $stored['height'] . ', ' . $stored['bytes'] . ' bytes WebP)' . (!$hasPrimary ? ', set as primary' : ''), $ip, $userAgent);

                return ['id' => $id, 'primary' => !$hasPrimary];
            });
        } catch (Throwable $e) {
            // The record did not save: do not leave its files behind.
            try {
                $this->files->delete($stored['path']);
            } catch (Throwable $cleanup) {
                error_log('Vehicle image: cleanup after a failed save also failed.');
            }
            error_log('Vehicle image: saving the record failed: ' . get_class($e) . ': ' . $e->getMessage());

            return $reject('save_failed', self::ERROR_SAVE);
        }

        return ['ok' => true, 'message' => null, 'id' => $result['id'], 'primary' => $result['primary']];
    }

    /**
     * @param list<array<string,mixed>> $images
     * @return array<string,mixed>|null
     */
    private function pick(array $images, int $imageId): ?array
    {
        foreach ($images as $image) {
            if ((int) $image['id'] === $imageId) {
                return $image;
            }
        }

        return null;
    }
}
