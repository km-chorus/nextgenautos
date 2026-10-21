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

    /** The category's display name, or null for "other" (not worth showing a visitor). */
    private function category(string $key): ?string
    {
        return ($key === '' || $key === 'other' || !array_key_exists($key, TeamValidator::CATEGORIES)) ? null : TeamValidator::categoryLabel($key);
    }

    private function shorten(mixed $text): ?string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $text));
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text) <= self::BIO_LENGTH) {
            return $text;
        }
        $cut = mb_substr($text, 0, self::BIO_LENGTH);
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
