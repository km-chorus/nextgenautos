<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/repositories/TeamRepository.php';
require_once dirname(__DIR__) . '/validation/TeamValidator.php';

/**
 * Team members for the PUBLIC homepage (spec 11C). Reuses the Team Management repository; nothing is hardcoded and
 * nothing is invented: only ACTIVE members, in the administrator's display order, at most $limit of them.
 *
 * Only customer-facing fields leave this class (name, position, the team category's name, a short biography, a photo):
 * no id, status or timestamps. The category name comes from the Team Management list; "Other" is not shown. The biography is shortened for the homepage at a word boundary (the stored text is never
 * changed). The photo is the stored 400 px thumbnail, used only when the path has exactly the shape the image service
 * writes (uploads/team/<32 hex>.webp) and the thumbnail file is a real WebP; otherwise the card has no photo and the
 * view shows a plain neutral panel. One query in total. Returns plain data: no markup.
 */
final class PublicTeamService
{
    public const HOME_LIMIT = 4;   // four compact cards; the full team belongs on a future Team page
    public const BIO_LENGTH = 120;

    private string $root;

    public function __construct(private TeamRepository $team, ?string $projectRoot = null)
    {
        $this->root = $projectRoot ?? dirname(__DIR__, 2);
    }

    public static function create(): self
    {
        return new self(new TeamRepository(Database::connection()));
    }

    /**
     * @return list<array{name:string,position:string,category:?string,bio:?string,photo:?array{url:string,width:int,height:int},alt:string}>
     */
    public function forHomepage(int $limit = self::HOME_LIMIT): array
    {
        $members = [];
        foreach ($this->team->publicActive(max(1, min($limit, 12))) as $row) {
            $name = trim((string) $row['name']);
            $position = trim((string) $row['position']);
            if ($name === '') {
                continue;
            }
            $members[] = [
                'name'     => $name,
                'position' => $position,
                'category' => $this->category((string) ($row['category'] ?? '')),
                'bio'      => $this->shorten($row['biography'] ?? null),
                'photo'    => $this->thumbnail((string) ($row['photo_path'] ?? '')),
                'alt'      => $position !== '' ? $name . ' — ' . $position : $name,
            ];
        }

        return $members;
    }

    public const ABOUT_LIMIT = 48;      // About page: every active member (a sane upper bound)
    public const ABOUT_BIO_LENGTH = 600;

    /**
     * Every active team member WITH an uploaded photo for the About page (2026-10-26), in the administrator's order: name,
     * position, department, a longer biography, and the photo (thumbnail plus the large file for sharp screens). Only
     * what the administrator entered. No member with a photo = an empty list, and the page leaves the section out.
     *
     * @return list<array{name:string,position:string,category:?string,bio:?string,photo:?array{url:string,width:int,height:int},large:?string,alt:string}>
     */
    /**
     * True when the About page shows a team section (at least one active member with an uploaded photo). Read once per
     * request; a database problem counts as "no team" (logged), so the footer never links to a missing section.
     */
    public static function hasVisibleTeam(): bool
    {
        static $cached = null;
        if ($cached === null) {
            try {
                $cached = self::create()->forAboutPage() !== [];
            } catch (Throwable $e) {
                error_log('Team visibility could not be read: ' . get_class($e));
                $cached = false;
            }
        }

        return $cached;
    }

    public function forAboutPage(): array
    {
        $members = [];
        foreach ($this->team->publicActive(self::ABOUT_LIMIT) as $row) {
            $name = trim((string) $row['name']);
            if ($name === '') {
                continue;
            }
            $position = trim((string) $row['position']);
            $photo = $this->thumbnail((string) ($row['photo_path'] ?? ''));
            if ($photo === null) {
                continue;   // owner 2026-10-26: the card is the photo; a member without an uploaded photo is not shown
            }
            $members[] = [
                'name'     => $name,
                'position' => $position,
                'category' => $this->category((string) ($row['category'] ?? '')),
                'bio'      => $this->shorten($row['biography'] ?? null, self::ABOUT_BIO_LENGTH),
                'photo'    => $photo,
                'large'    => $photo === null ? null : $this->large((string) $row['photo_path']),
                'alt'      => $position !== '' ? $name . ', ' . $position : $name,
            ];
        }

        return $members;
    }

    /** The large stored photo (same checks as the thumbnail), as a root-relative URL, or null. */
    private function large(string $path): ?string
    {
        if (preg_match('#^uploads/team/([a-f0-9]{32})\.webp$#', $path) !== 1 || !is_file($this->root . '/' . $path)) {
            return null;
        }

        return '/' . $path;
    }

    /** The category's display name, or null for "other" (not worth showing a visitor). */
    private function category(string $key): ?string
    {
        return ($key === '' || $key === 'other' || !array_key_exists($key, TeamValidator::CATEGORIES)) ? null : TeamValidator::categoryLabel($key);
    }

    private function shorten(mixed $text, int $limit = self::BIO_LENGTH): ?string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $text));
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        $cut = mb_substr($text, 0, $limit);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space !== false && $space > 60 ? mb_substr($cut, 0, $space) : $cut, " ,;:.-") . '…';
    }

    /** @return array{url:string,width:int,height:int}|null */
    private function thumbnail(string $path): ?array
    {
        if (preg_match('#^uploads/team/([a-f0-9]{32})\.webp$#', $path, $m) !== 1) {
            return null;
        }
        $relative = 'uploads/team/' . $m[1] . '-t.webp';
        $file = $this->root . '/' . $relative;
        if (!is_file($file)) {
            return null;
        }
        $info = @getimagesize($file);
        if ($info === false || $info[2] !== IMAGETYPE_WEBP || $info[0] < 1 || $info[1] < 1) {
            return null;
        }

        return ['url' => '/' . $relative, 'width' => $info[0], 'height' => $info[1]];
    }
}
