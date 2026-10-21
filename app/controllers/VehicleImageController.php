<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/VehicleImageService.php';
require_once dirname(__DIR__) . '/services/AuthorizationService.php';
require_once dirname(__DIR__) . '/helpers/csrf.php';
require_once dirname(__DIR__) . '/helpers/flash.php';
require_once dirname(__DIR__) . '/helpers/diagnostics.php';
require_once __DIR__ . '/VehicleController.php';

/**
 * Vehicle image management controller (spec 07_spec_vehicle_image.md). Same pattern as
 * the Brand and Vehicle controllers. For every request it:
 *   1. checks the permission (the page has already passed requireAdmin()),
 *   2. for POST: verifies the CSRF token,
 *   3. validates the vehicle id and image id (an image is only ever reached through
 *      its vehicle: both ids must match),
 *   4. calls VehicleImageService,
 *   5. returns a view model. Pages render it, or follow its 'redirect'.
 *
 * A view model always has 'http' and 'problem' (null, or forbidden | not_found | csrf | error).
 * Messages are fixed sentences; technical detail goes to the server error log.
 */
final class VehicleImageController
{
    /** Image management is part of managing vehicles: the same permission. */
    public const PERMISSION = VehicleController::PERMISSION;

    public const MESSAGE_FORBIDDEN = 'Your account does not have permission to manage vehicle images.';
    public const MESSAGE_VEHICLE_NOT_FOUND = 'The requested vehicle could not be found.';
    public const MESSAGE_IMAGE_NOT_FOUND = 'The requested image could not be found.';
    public const MESSAGE_CSRF = "We couldn't verify your request. Reload the page and try again.";
    public const MESSAGE_ERROR = 'Something went wrong. Please try again.';
    public const MESSAGE_TOO_LARGE = 'The upload was too large for the server configuration. Send fewer or smaller images (up to 10 MB each).';
    public const MESSAGE_THROTTLED = 'Too many uploads in a short time. Wait a few minutes and try again.';
    public const MESSAGE_DELETE_FAILED = "We couldn't delete this image. Please try again.";

    public function __construct(private ?VehicleImageService $service = null)
    {
    }

    /**
     * GET: the gallery and upload form of one vehicle.
     *
     * @param array<string,mixed> $admin
     * @return array<string,mixed>
     */
    public function manage(array $admin, mixed $rawVehicleId): array
    {
        return $this->run($admin, function (VehicleImageService $service) use ($rawVehicleId): array {
            $vehicleId = VehicleValidator::id($rawVehicleId);
            $gallery = $vehicleId === null ? null : $service->gallery($vehicleId);
            if ($gallery === null) {
                return $this->failure(404, self::MESSAGE_VEHICLE_NOT_FOUND, 'not_found');
            }

            return $gallery + [
                'flash'        => flash_take(),
                'report'       => flash_take_report(),
                'maxFiles'     => VehicleImageService::MAX_FILES_PER_REQUEST,
                'missing'      => ImageService::missingRequirements(),   // PHP extensions/packages that are not available (empty when fine)
                'serverLimit'  => ImageService::serverUploadLimit(),    // bytes PHP will accept per upload (0 = no PHP limit)
            ];
        });
    }

    /**
     * POST: upload one or more images. The vehicle id comes from the URL so that even a request
     * PHP discarded for being too large (empty body) still knows where to return.
     *
     * @param array<string,mixed> $post
     * @param array<string,mixed> $files $_FILES
     * @param array<string,mixed> $server $_SERVER (only CONTENT_LENGTH is read)
     * @return array<string,mixed>
     */
    public function upload(array $admin, mixed $rawVehicleId, array $post, array $files, array $server, string $ip, string $userAgent, bool $requireHttpUpload = true): array
    {
        return $this->run($admin, function (VehicleImageService $service) use ($admin, $rawVehicleId, $post, $files, $server, $ip, $userAgent, $requireHttpUpload): array {
            $vehicleId = VehicleValidator::id($rawVehicleId);
            if ($vehicleId === null) {
                return $this->failure(404, self::MESSAGE_VEHICLE_NOT_FOUND, 'not_found');
            }
            $back = 'vehicle-images.php?id=' . $vehicleId;

            // PHP drops the whole body (no $_POST, no $_FILES) when it exceeds post_max_size.
            if ($post === [] && $files === [] && (int) ($server['CONTENT_LENGTH'] ?? 0) > 0) {
                flash_set('error', self::MESSAGE_TOO_LARGE);

                return ['redirect' => $back];
            }
            if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                return $this->csrfFailure($admin, $ip, $userAgent);
            }

            $missing = ImageService::missingRequirements();
            if ($missing !== []) {
                // Not an application bug: the server lacks something. Say what, in words an administrator can act on.
                error_log('Vehicle image upload refused: missing ' . implode(', ', $missing));
                flash_set('error', self::unavailableMessage($missing));

                return ['redirect' => $back];
            }

            $result = $service->upload($vehicleId, self::normaliseFiles($files['images'] ?? null), $admin['id'], $ip, $userAgent, $requireHttpUpload);
            if ($result['status'] === VehicleImageService::RESULT_NOT_FOUND) {
                return $this->failure(404, self::MESSAGE_VEHICLE_NOT_FOUND, 'not_found');
            }
            if ($result['status'] === VehicleImageService::RESULT_THROTTLED) {
                flash_set('error', self::MESSAGE_THROTTLED);

                return ['redirect' => $back];
            }

            $total = count($result['results']);
            $done = count(array_filter($result['results'], static fn (array $r): bool => $r['ok']));
            if ($result['status'] === VehicleImageService::RESULT_INVALID) {
                flash_set('error', (string) ($result['results'][0]['message'] ?? self::MESSAGE_ERROR));
            } elseif ($done === $total) {
                flash_set('success', ($done === 1 ? '1 image was uploaded successfully.' : $done . ' images were uploaded successfully.')
                    . ($result['primary_assigned'] ? ' The first image was set as the primary image.' : ''));
            } elseif ($done === 0) {
                flash_set('error', $total === 1 ? 'The image was not uploaded.' : 'None of the ' . $total . ' images were uploaded.');
            } else {
                flash_set('warning', $done . ' of ' . $total . ' images were uploaded. ' . ($total - $done) . ' could not be added:'
                    . ($result['primary_assigned'] ? ' (The first uploaded image was set as the primary image.)' : ''));
            }
            if ($total > 1 || $done !== $total) {
                flash_set_report($result['results']);
            }

            return ['redirect' => $back];
        });
    }

    /**
     * POST: set the primary image, save alt text or the photo type (exterior / interior), or save a new gallery order.
     *
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    public function update(array $admin, array $post, string $ip, string $userAgent): array
    {
        return $this->run($admin, function (VehicleImageService $service) use ($admin, $post, $ip, $userAgent): array {
            if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                return $this->csrfFailure($admin, $ip, $userAgent);
            }
            $vehicleId = VehicleValidator::id($post['vehicle_id'] ?? null);
            if ($vehicleId === null) {
                return $this->failure(404, self::MESSAGE_VEHICLE_NOT_FOUND, 'not_found');
            }
            $back = 'vehicle-images.php?id=' . $vehicleId;
            $action = is_string($post['action'] ?? null) ? $post['action'] : '';

            if ($action === 'reorder') {
                $result = $service->reorder($vehicleId, $post['order'] ?? null, $admin['id'], $ip, $userAgent);
                return $this->finish($result['status'], $back, 'The gallery order was saved.', 'The gallery order is already like that.', VehicleImageService::MESSAGE_ORDER);
            }

            $imageId = VehicleValidator::id($post['image_id'] ?? null);
            if ($imageId === null) {
                return $this->imageNotFound();
            }
            if ($action === 'primary') {
                $result = $service->setPrimary($vehicleId, $imageId, $admin['id'], $ip, $userAgent);
                return $this->finish($result['status'], $back, 'The primary image was changed.', 'That image is already the primary image.', self::MESSAGE_ERROR);
            }
            if ($action === 'type') {
                $result = $service->updateType($vehicleId, $imageId, $post['photo_type'] ?? null, $admin['id'], $ip, $userAgent);
                return $this->finish($result['status'], $back, 'The photo type was saved.', 'The photo type is unchanged.', 'Choose Exterior or Interior.');
            }
            if ($action === 'alt') {
                $result = $service->updateAlt($vehicleId, $imageId, $post['alt_text'] ?? null, $admin['id'], $ip, $userAgent);
                return $this->finish($result['status'], $back, 'The alt text was saved.', 'The alt text is unchanged.', (string) ($result['error'] ?? self::MESSAGE_ERROR));
            }

            return $this->failure(400, self::MESSAGE_ERROR);
        });
    }

    /**
     * GET: the delete confirmation for one image (the image must belong to the vehicle in the URL).
     *
     * @return array<string,mixed>
     */
    public function confirmDelete(array $admin, mixed $rawVehicleId, mixed $rawImageId): array
    {
        return $this->run($admin, function (VehicleImageService $service) use ($rawVehicleId, $rawImageId): array {
            $vehicleId = VehicleValidator::id($rawVehicleId);
            $imageId = VehicleValidator::id($rawImageId);
            $found = ($vehicleId === null || $imageId === null) ? null : $service->imageOfVehicle($vehicleId, $imageId);

            return $found === null ? $this->imageNotFound() : $found;
        });
    }

    /**
     * POST: delete an image, optionally promoting the next image when it was the primary one.
     *
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    public function delete(array $admin, array $post, string $ip, string $userAgent): array
    {
        return $this->run($admin, function (VehicleImageService $service) use ($admin, $post, $ip, $userAgent): array {
            if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                return $this->csrfFailure($admin, $ip, $userAgent);
            }
            $vehicleId = VehicleValidator::id($post['vehicle_id'] ?? null);
            $imageId = VehicleValidator::id($post['image_id'] ?? null);
            if ($vehicleId === null || $imageId === null) {
                return $this->imageNotFound();
            }
            if (($post['confirm'] ?? null) !== 'yes') {
                return $this->failure(400, self::MESSAGE_ERROR);
            }

            $result = $service->delete($vehicleId, $imageId, ($post['promote_next'] ?? null) === '1', $admin['id'], $ip, $userAgent);
            if ($result['status'] === VehicleImageService::RESULT_NOT_FOUND) {
                flash_set('error', self::MESSAGE_IMAGE_NOT_FOUND);

                return ['redirect' => 'vehicle-images.php?id=' . $vehicleId];
            }
            if ($result['file'] === 'error') {
                flash_set('warning', 'The image was removed from the gallery, but its file could not be deleted from the server. It has been logged.');
            } else {
                $note = $result['was_primary']
                    ? ($result['promoted'] !== null ? ' The next image is now the primary image.' : ' This vehicle now has no primary image.')
                    : '';
                flash_set('success', 'The image was deleted.' . $note);
            }

            return ['redirect' => 'vehicle-images.php?id=' . $vehicleId];
        });
    }

    /** The sentence shown when the server cannot process images (extension names only: nothing sensitive). */
    public static function unavailableMessage(array $missing): string
    {
        return 'Image uploads are not available on this server yet: the PHP component(s) ' . implode(', ', $missing)
            . ' are not enabled. Enable them in php.ini and restart the PHP server, then try again.';
    }

    /**
     * Turns $_FILES['images'] (one file or a list) into a flat list of entries; anything of an
     * unexpected shape yields no files. Entries for empty file slots are dropped.
     *
     * @return list<array{name:string,tmp_name:string,error:int,size:int}>
     */
    public static function normaliseFiles(mixed $field): array
    {
        if (!is_array($field) || !isset($field['name'], $field['tmp_name'], $field['error'], $field['size'])) {
            return [];
        }
        $out = [];
        $names = is_array($field['name']) ? $field['name'] : [$field['name']];
        foreach (array_keys($names) as $i) {
            $entry = [
                'name'     => is_array($field['name']) ? ($field['name'][$i] ?? null) : $field['name'],
                'tmp_name' => is_array($field['tmp_name']) ? ($field['tmp_name'][$i] ?? null) : $field['tmp_name'],
                'error'    => is_array($field['error']) ? ($field['error'][$i] ?? null) : $field['error'],
                'size'     => is_array($field['size']) ? ($field['size'][$i] ?? null) : $field['size'],
            ];
            if (!is_string($entry['name']) || !is_string($entry['tmp_name']) || !is_int($entry['error']) || !is_int($entry['size'])) {
                return [];   // nested arrays or odd types: not a normal upload
            }
            if ($entry['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = $entry;
        }

        return $out;
    }

    /* ---------------------------------------------------------------- */

    /**
     * Maps a service result code to a redirect with a message.
     *
     * @return array<string,mixed>
     */
    private function finish(string $status, string $back, string $okText, string $sameText, string $errorText): array
    {
        switch ($status) {
            case VehicleImageService::RESULT_OK:
                flash_set('success', $okText);
                break;
            case VehicleImageService::RESULT_UNCHANGED:
                flash_set('info', $sameText);
                break;
            case VehicleImageService::RESULT_NOT_FOUND:
                flash_set('error', self::MESSAGE_IMAGE_NOT_FOUND);
                break;
            default:
                flash_set('error', $errorText);
        }

        return ['redirect' => $back];
    }

    /**
     * Permission check, service creation and error containment shared by every action.
     *
     * @param callable(VehicleImageService):array<string,mixed> $action
     * @return array<string,mixed>
     */
    private function run(array $admin, callable $action): array
    {
        if (!AuthorizationService::can((string) ($admin['role'] ?? ''), self::PERMISSION)) {
            return $this->failure(403, self::MESSAGE_FORBIDDEN, 'forbidden');
        }

        try {
            $result = $action($this->service ?? VehicleImageService::create());
        } catch (Throwable $e) {
            error_log('Vehicle image management failed: ' . get_class($e) . ': ' . $e->getMessage());

            return $this->failure(500, self::MESSAGE_ERROR) + ['detail' => self::developmentDetail($e)];
        }

        return $result + ['http' => 200, 'problem' => null];
    }

    /**
     * A one-line description of an unexpected error, for the developer: ONLY when APP_ENV is development.
     * In production this is null and the page stays generic. File-system paths are replaced and the text
     * is cut short; request data, credentials and tokens are never part of an exception message here.
     */
    private static function developmentDetail(Throwable $e): ?string
    {
        return development_error_detail($e);   // shared helper: development only, paths removed
    }

    /** @return array<string,mixed> */
    private function imageNotFound(): array
    {
        return $this->failure(404, self::MESSAGE_IMAGE_NOT_FOUND, 'not_found') + ['title' => 'Image not found'];
    }

    /** @return array<string,mixed> */
    private function failure(int $http, string $message, string $problem = 'error'): array
    {
        return ['http' => $http, 'problem' => $problem, 'message' => $message];
    }

    /** @return array<string,mixed> */
    private function csrfFailure(array $admin, string $ip, string $userAgent): array
    {
        try {
            (new AuditLogRepository(Database::connection()))->record('CSRF_FAILURE', (int) $admin['id'], null, null,
                'Vehicle image request rejected: missing or invalid CSRF token', $ip, $userAgent);
        } catch (Throwable $e) {
            error_log('Vehicle image management: could not log CSRF failure: ' . get_class($e));
        }

        return $this->failure(400, self::MESSAGE_CSRF, 'csrf');
    }
}
