<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/repositories/TeamRepository.php';
require_once dirname(__DIR__) . '/repositories/AuditLogRepository.php';
require_once dirname(__DIR__) . '/validation/TeamValidator.php';
require_once dirname(__DIR__) . '/validation/ImageValidator.php';
require_once __DIR__ . '/ImageService.php';
require_once __DIR__ . '/Transaction.php';

/**
 * Business rules for Team Management (spec 08_spec_team.md): members, their category,
 * status, display order and professional photo.
 *
 * Returns plain data and result codes only (no markup, no HTTP).
 *
 * Photos reuse the Spec 07 pipeline unchanged: ImageValidator judges the real file content
 * (with a 400 x 400 minimum for portraits), ImageService (kind "team") re-encodes it to WebP
 * inside uploads/team with a random name and a thumbnail. The principles are the same as for
 * vehicle images:
 *   - nothing is written until every field and the photo have passed validation;
 *   - the NEW file is written first, the database is updated second, and the OLD file is
 *     removed last, only after the database change succeeded (a failed replacement leaves the
 *     old photo untouched; a failed save removes the new file);
 *   - on delete the record goes first, then the files.
 *
 * Audit text holds ids, the member's name and short reason codes: no secrets, no file names.
 */
class TeamService
{
    public const RESULT_OK = 'ok';
    public const RESULT_UNCHANGED = 'unchanged';
    public const RESULT_INVALID = 'invalid';
    public const RESULT_NOT_FOUND = 'not_found';

    public const ERROR_PHOTO_FORMAT = 'The selected photo is not supported.';
    public const ERROR_PHOTO_PROCESS = 'The photo could not be processed.';
    public const MESSAGE_ORDER = 'The order could not be saved. Reload the page and try again.';

    public function __construct(
        private PDO $pdo,
        private TeamRepository $team,
        private AuditLogRepository $audit,
        private ImageService $images
    ) {
    }

    public static function create(): self
    {
        $pdo = Database::connection();

        return new self($pdo, new TeamRepository($pdo), new AuditLogRepository($pdo), new ImageService(null, ImageService::KIND_TEAM));
    }

    /**
     * @param array{q:string,category:string,status:string} $filters
     * @return array{members:list<array<string,mixed>>,total_all:int}
     */
    public function listMembers(array $filters): array
    {
        return ['members' => $this->team->search($filters), 'total_all' => $this->team->countAll()];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->team->find($id);
    }

    public function nextDisplayOrder(): int
    {
        return $this->team->nextDisplayOrder();
    }

    /**
     * Active members in display order, for the future public Team page (none exists yet).
     *
     * @return list<array<string,mixed>>
     */
    public function publicMembers(): array
    {
        return $this->team->activeInDisplayOrder();
    }

    /**
     * @param array<string,mixed> $input raw browser data
     * @param array{name:string,tmp_name:string,error:int,size:int}|null $photo the uploaded photo, or null when none was chosen
     * @return array{status:string,id:?int,values:array<string,mixed>,errors:array<string,string>,photo:?string}
     */
    public function createMember(array $input, ?array $photo, int $adminId, string $ip, string $userAgent, bool $requireHttpUpload = true): array
    {
        $checked = TeamValidator::validate($input);
        $values = $checked['values'];
        $errors = $checked['errors'];

        if ($photo !== null) {
            $photoError = $this->photoError($photo, $adminId, $ip, $userAgent, $requireHttpUpload);
            if ($photoError !== null) {
                $errors['photo'] = $photoError;
            }
        }
        if ($errors !== []) {
            return ['status' => self::RESULT_INVALID, 'id' => null, 'values' => $values, 'errors' => $errors, 'photo' => null];
        }

        $stored = null;
        if ($photo !== null) {
            $stored = $this->storePhoto($photo, $errors);
            if ($stored === null) {
                return ['status' => self::RESULT_INVALID, 'id' => null, 'values' => $values, 'errors' => $errors, 'photo' => null];
            }
        }

        try {
            $id = Transaction::run($this->pdo, function () use ($values, $stored, $adminId, $ip, $userAgent): int {
                $id = $this->team->insert($values, $stored['path'] ?? null);
                $this->audit->record('TEAM_MEMBER_CREATED', $adminId, 'team_member', $id,
                    'Team member created: ' . $values['name'] . ' (' . $values['category'] . ', ' . $values['status'] . ')', $ip, $userAgent);
                if ($stored !== null) {
                    $this->audit->record('TEAM_MEMBER_PHOTO_UPLOADED', $adminId, 'team_member', $id, 'Photo added to team member ' . $id, $ip, $userAgent);
                }

                return $id;
            });
        } catch (Throwable $e) {
            $this->discard($stored);
            throw $e;
        }

        return ['status' => self::RESULT_OK, 'id' => $id, 'values' => $values, 'errors' => [], 'photo' => $stored['path'] ?? null];
    }

    /**
     * @param array<string,mixed> $input raw browser data
     * @param array{name:string,tmp_name:string,error:int,size:int}|null $photo a NEW photo, or null to keep the existing one
     * @return array{status:string,id:int,values:array<string,mixed>,errors:array<string,string>,photo_replaced:bool}
     */
    public function updateMember(int $id, array $input, ?array $photo, int $adminId, string $ip, string $userAgent, bool $requireHttpUpload = true): array
    {
        $existing = $this->team->find($id);
        if ($existing === null) {
            return ['status' => self::RESULT_NOT_FOUND, 'id' => $id, 'values' => [], 'errors' => [], 'photo_replaced' => false];
        }

        $checked = TeamValidator::validate($input);
        $values = $checked['values'];
        $errors = $checked['errors'];
        if ($photo !== null) {
            $photoError = $this->photoError($photo, $adminId, $ip, $userAgent, $requireHttpUpload);
            if ($photoError !== null) {
                $errors['photo'] = $photoError;
            }
        }
        if ($errors !== []) {
            return ['status' => self::RESULT_INVALID, 'id' => $id, 'values' => $values, 'errors' => $errors, 'photo_replaced' => false];
        }

        $changed = [];
        foreach (['name', 'position', 'category', 'biography', 'display_order', 'status'] as $field) {
            if ((string) ($existing[$field] ?? '') !== (string) ($values[$field] ?? '')) {
                $changed[] = $field;
            }
        }
        if ($changed === [] && $photo === null) {
            return ['status' => self::RESULT_UNCHANGED, 'id' => $id, 'values' => $values, 'errors' => [], 'photo_replaced' => false];
        }

        // Step 1: process and store the new photo. The old one is not touched yet.
        $stored = null;
        if ($photo !== null) {
            $stored = $this->storePhoto($photo, $errors);
            if ($stored === null) {
                return ['status' => self::RESULT_INVALID, 'id' => $id, 'values' => $values, 'errors' => $errors, 'photo_replaced' => false];
            }
        }

        // Step 2: the database change (and its audit rows) in one transaction.
        $oldPhoto = null;
        try {
            $outcome = Transaction::run($this->pdo, function () use ($id, $values, $changed, $stored, $existing, $adminId, $ip, $userAgent, &$oldPhoto): string {
                $current = $this->team->find($id, true);
                if ($current === null) {
                    return self::RESULT_NOT_FOUND;                       // deleted by someone else in the meantime
                }
                $oldPhoto = $current['photo_path'];
                $this->team->update($id, $values, $stored !== null, $stored['path'] ?? null);
                $this->audit->record('TEAM_MEMBER_UPDATED', $adminId, 'team_member', $id,
                    'Team member updated: ' . $existing['name'] . ($changed === [] ? '' : ' (changed: ' . implode(', ', $changed) . ')'), $ip, $userAgent);
                if (in_array('status', $changed, true)) {
                    $this->audit->record($values['status'] === 'active' ? 'TEAM_MEMBER_ACTIVATED' : 'TEAM_MEMBER_DEACTIVATED', $adminId, 'team_member', $id,
                        'Team member ' . ($values['status'] === 'active' ? 'activated' : 'deactivated') . ': ' . $values['name'], $ip, $userAgent);
                }
                if ($stored !== null) {
                    $this->audit->record($oldPhoto !== null ? 'TEAM_MEMBER_PHOTO_REPLACED' : 'TEAM_MEMBER_PHOTO_UPLOADED', $adminId, 'team_member', $id,
                        ($oldPhoto !== null ? 'Photo replaced' : 'Photo added') . ' for team member ' . $id, $ip, $userAgent);
                }

                return self::RESULT_OK;
            });
        } catch (Throwable $e) {
            $this->discard($stored);                                     // the save failed: the new file must not be left behind
            throw $e;
        }
        if ($outcome !== self::RESULT_OK) {
            $this->discard($stored);

            return ['status' => $outcome, 'id' => $id, 'values' => $values, 'errors' => [], 'photo_replaced' => false];
        }

        // Step 3: only now, after the database change succeeded, remove the old photo.
        if ($stored !== null && is_string($oldPhoto) && $oldPhoto !== '') {
            $this->removeFiles($oldPhoto);
        }

        return ['status' => self::RESULT_OK, 'id' => $id, 'values' => $values, 'errors' => [], 'photo_replaced' => $stored !== null];
    }

    /**
     * Removes a member's photo (the member stays, without a photo).
     *
     * @return array{status:string}
     */
    public function removePhoto(int $id, int $adminId, string $ip, string $userAgent): array
    {
        $old = null;
        $status = Transaction::run($this->pdo, function () use ($id, $adminId, $ip, $userAgent, &$old): string {
            $member = $this->team->find($id, true);
            if ($member === null) {
                return self::RESULT_NOT_FOUND;
            }
            if ($member['photo_path'] === null || $member['photo_path'] === '') {
                return self::RESULT_UNCHANGED;
            }
            $old = (string) $member['photo_path'];
            $this->team->setPhoto($id, null);
            $this->audit->record('TEAM_MEMBER_PHOTO_REMOVED', $adminId, 'team_member', $id, 'Photo removed from team member ' . $id, $ip, $userAgent);

            return self::RESULT_OK;
        });
        if ($status === self::RESULT_OK && $old !== null) {
            $this->removeFiles($old);                                    // after the record no longer points at it
        }

        return ['status' => $status];
    }

    /**
     * Sets the status explicitly (idempotent: repeating it does not flip it back).
     *
     * @return array{status:string,member:?array<string,mixed>}
     */
    public function setStatus(int $id, string $target, int $adminId, string $ip, string $userAgent): array
    {
        if (!in_array($target, TeamValidator::STATUSES, true)) {
            return ['status' => self::RESULT_INVALID, 'member' => null];
        }

        return Transaction::run($this->pdo, function () use ($id, $target, $adminId, $ip, $userAgent): array {
            $member = $this->team->find($id, true);
            if ($member === null) {
                return ['status' => self::RESULT_NOT_FOUND, 'member' => null];
            }
            if ($member['status'] === $target) {
                return ['status' => self::RESULT_UNCHANGED, 'member' => $member];
            }
            $this->team->setStatus($id, $target);
            $this->audit->record($target === 'active' ? 'TEAM_MEMBER_ACTIVATED' : 'TEAM_MEMBER_DEACTIVATED', $adminId, 'team_member', $id,
                'Team member ' . ($target === 'active' ? 'activated' : 'deactivated') . ': ' . $member['name'], $ip, $userAgent);

            return ['status' => self::RESULT_OK, 'member' => $member];
        });
    }

    /**
     * Saves a complete new order of ALL team members. The submitted list is not trusted: it must
     * contain exactly the ids that exist, each once, and nothing else.
     *
     * @param mixed $order the ids as submitted by the browser
     * @return array{status:string}
     */
    public function reorder(mixed $order, int $adminId, string $ip, string $userAgent): array
    {
        $ids = TeamValidator::parseIdList($order);
        if ($ids === null) {
            return ['status' => self::RESULT_INVALID];
        }

        return Transaction::run($this->pdo, function () use ($ids, $adminId, $ip, $userAgent): array {
            $current = $this->team->allIdsInOrder(true);
            $sortedSubmitted = $ids;
            sort($sortedSubmitted);
            $sortedCurrent = $current;
            sort($sortedCurrent);
            if ($sortedSubmitted !== $sortedCurrent) {
                return ['status' => self::RESULT_INVALID];              // missing, extra, nonexistent or repeated ids
            }
            if ($ids === $current) {
                return ['status' => self::RESULT_UNCHANGED];
            }
            foreach ($ids as $position => $id) {
                $this->team->setOrder($id, $position + 1);
            }
            $this->audit->record('TEAM_MEMBER_REORDERED', $adminId, 'team_member', null, 'Team order saved (' . count($ids) . ' members)', $ip, $userAgent);

            return ['status' => self::RESULT_OK];
        });
    }

    /**
     * Deletes a member permanently (record first, then the photo files). Nothing references
     * team members, so no relationship blocks it; deactivation is the gentler alternative.
     *
     * @return array{status:string,member:?array<string,mixed>}
     */
    public function deleteMember(int $id, int $adminId, string $ip, string $userAgent): array
    {
        $result = Transaction::run($this->pdo, function () use ($id, $adminId, $ip, $userAgent): array {
            $member = $this->team->find($id, true);
            if ($member === null) {
                return ['status' => self::RESULT_NOT_FOUND, 'member' => null];
            }
            $this->team->delete($id);
            $this->audit->record('TEAM_MEMBER_DELETED', $adminId, 'team_member', $id, 'Team member deleted: ' . $member['name'], $ip, $userAgent);

            return ['status' => self::RESULT_OK, 'member' => $member];
        });
        if ($result['status'] === self::RESULT_OK && is_string($result['member']['photo_path'] ?? null) && $result['member']['photo_path'] !== '') {
            $this->removeFiles($result['member']['photo_path']);
        }

        return $result;
    }

    /** "Photo of Jane Doe": alt text for a member's photo (stated facts only). */
    public static function photoAlt(string $name): string
    {
        return 'Photo of ' . $name;
    }

    /* ---------------------------------------------------------------- */

    /**
     * Judges the uploaded photo by its real content. Returns an error sentence, or null when it is acceptable.
     *
     * @param array{name:string,tmp_name:string,error:int,size:int} $photo
     */
    private function photoError(array $photo, int $adminId, string $ip, string $userAgent, bool $requireHttpUpload): ?string
    {
        $reject = function (string $reason, string $message) use ($adminId, $ip, $userAgent): string {
            try {
                $this->audit->record('TEAM_MEMBER_PHOTO_REJECTED', $adminId, 'team_member', null, 'Photo rejected: ' . $reason, $ip, $userAgent);
            } catch (Throwable $e) {
                error_log('Team photo: could not log a rejection: ' . get_class($e));
            }

            return $message;
        };

        $uploadError = ImageValidator::uploadErrorMessage((int) $photo['error'], ImageService::serverUploadLimit());
        if ($uploadError !== null) {
            return $reject('upload_error_' . (int) $photo['error'], $uploadError);
        }
        $missing = ImageService::missingRequirements();
        if ($missing !== []) {
            error_log('Team photo refused: missing ' . implode(', ', $missing));

            return 'Photo uploads are not available on this server yet: the PHP component(s) ' . implode(', ', $missing) . ' are not enabled.';
        }
        $tmp = (string) $photo['tmp_name'];
        if ($requireHttpUpload && !is_uploaded_file($tmp)) {
            return $reject('not_an_http_upload', ImageValidator::ERROR_UPLOAD);
        }
        try {
            $check = ImageValidator::inspect($tmp, TeamValidator::PHOTO_MIN_LONG, TeamValidator::PHOTO_MIN_SHORT);
        } catch (Throwable $e) {
            error_log('Team photo: inspecting the file failed: ' . get_class($e) . ': ' . $e->getMessage());

            return $reject('inspect_failed', self::ERROR_PHOTO_PROCESS);
        }
        if (!$check['ok']) {
            $reason = (string) $check['reason'];
            $unsupported = str_starts_with($reason, 'format_') || $reason === 'animated';

            return $reject($reason, $unsupported ? self::ERROR_PHOTO_FORMAT : (string) $check['error']);
        }

        return null;
    }

    /**
     * Processes and stores a validated photo.
     *
     * @param array{name:string,tmp_name:string,error:int,size:int} $photo
     * @param array<string,string> $errors set when processing fails
     * @return array{path:string,thumb:string}|null
     */
    private function storePhoto(array $photo, array &$errors): ?array
    {
        try {
            $stored = $this->images->process((string) $photo['tmp_name']);
        } catch (RuntimeException $e) {
            $errors['photo'] = self::ERROR_PHOTO_PROCESS;

            return null;
        }

        return ['path' => $stored['path'], 'thumb' => $stored['thumb']];
    }

    /** Removes files written for a save that did not complete (never throws). */
    private function discard(?array $stored): void
    {
        if ($stored === null) {
            return;
        }
        try {
            $this->images->delete($stored['path']);
        } catch (Throwable $e) {
            error_log('Team photo: cleanup of an unused file failed.');
        }
    }

    /** Removes a stored photo and thumbnail after the database no longer refers to them; problems are logged, not shown. */
    private function removeFiles(string $path): void
    {
        try {
            $result = $this->images->delete($path);
            if ($result !== 'deleted') {
                error_log('Team photo: the stored file was ' . $result . ' when its record was changed.');
            }
        } catch (Throwable $e) {
            error_log('Team photo: a file could not be deleted after its record was changed: ' . get_class($e));
        }
    }
}
