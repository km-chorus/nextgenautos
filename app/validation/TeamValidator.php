<?php

declare(strict_types=1);

/**
 * Validation and normalisation for Team Management (spec 08_spec_team.md).
 *
 * Pure functions, no database. Limits come from the `team_members` table: name and
 * position VARCHAR(150), biography TEXT (limited here to 1500 characters: a short
 * professional introduction), display_order INT UNSIGNED (0 to 9999 here),
 * status ENUM('active','inactive'), category ENUM (migration 002).
 *
 * The photo is not validated here: it is a file, judged by ImageValidator.
 * HTML attributes in the forms are a convenience only; this is the real check.
 */
final class TeamValidator
{
    public const NAME_MAX = 150;
    public const POSITION_MAX = 150;
    public const BIO_MAX = 1500;
    public const ORDER_MAX = 9999;
    public const SEARCH_MAX = 100;
    public const STATUSES = ['active', 'inactive'];

    /** Category key (stored in the database) => name shown to administrators. */
    public const CATEGORIES = [
        'leadership'       => 'CEO / Leadership',
        'investor_partner' => 'Investor / Partner',
        'management'       => 'Management',
        'sales'            => 'Sales',
        'operations'       => 'Operations',
        'other'            => 'Other',
    ];

    /** Smallest team photo accepted (longer side x shorter side). Portraits and squares both pass at 400 x 400. */
    public const PHOTO_MIN_LONG = 400;
    public const PHOTO_MIN_SHORT = 400;

    public static function categoryLabel(string $category): string
    {
        return self::CATEGORIES[$category] ?? 'Other';
    }

    /**
     * Normalises and validates a create/update submission (everything except the photo file).
     *
     * @param array<string,mixed> $input raw browser data (untrusted)
     * @return array{values:array{name:string,position:string,category:string,biography:?string,display_order:int|string,status:string},errors:array<string,string>}
     *         `values` holds the cleaned data (display_order keeps the typed text when it is invalid, so the form can show it again)
     */
    public static function validate(array $input): array
    {
        $errors = [];

        $name = self::line($input['name'] ?? null, self::NAME_MAX, 'name', 'Enter the full name.', $errors);
        if (!isset($errors['name']) && preg_match('/[\p{L}\p{N}]/u', $name) !== 1) {
            $errors['name'] = 'The name must contain at least one letter or number.';
        }
        $position = self::line($input['position'] ?? null, self::POSITION_MAX, 'position', 'Enter the position or job title.', $errors);

        $category = is_string($input['category'] ?? null) ? trim($input['category']) : '';
        if (!array_key_exists($category, self::CATEGORIES)) {
            $errors['category'] = 'Choose a category from the list.';
            $category = '';
        }

        // Biography: optional, plain text, line breaks allowed.
        $biography = null;
        $bioRaw = $input['biography'] ?? null;
        if ($bioRaw !== null && (!is_string($bioRaw) || strlen($bioRaw) > 20000 || !mb_check_encoding($bioRaw, 'UTF-8'))) {
            $errors['biography'] = 'The introduction contains characters that are not allowed.';
        } else {
            $text = trim(str_replace(["\r\n", "\r"], "\n", (string) $bioRaw));
            if (mb_strlen($text, 'UTF-8') > self::BIO_MAX) {
                $errors['biography'] = 'The introduction must be ' . self::BIO_MAX . ' characters or fewer.';
            } elseif (preg_match('/[^\P{Cc}\n\t]|[\p{Cf}\p{Zl}\p{Zp}]/u', $text) === 1) {
                $errors['biography'] = 'The introduction contains characters that are not allowed.';
            }
            $biography = $text === '' ? null : $text;
        }

        $orderText = is_string($input['display_order'] ?? null) ? trim($input['display_order']) : '';
        $order = $orderText;
        if ($orderText === '') {
            $errors['display_order'] = 'Enter the display order.';
        } elseif (preg_match('/^[0-9]{1,5}$/', $orderText) !== 1 || (int) $orderText > self::ORDER_MAX) {
            $errors['display_order'] = 'Enter a whole number from 0 to ' . self::ORDER_MAX . '.';
        } else {
            $order = (int) $orderText;
        }

        $status = is_string($input['status'] ?? null) ? strtolower(trim($input['status'])) : '';
        if (!in_array($status, self::STATUSES, true)) {
            $errors['status'] = 'Choose Active or Inactive.';
            $status = '';
        }

        return [
            'values' => ['name' => $name, 'position' => $position, 'category' => $category, 'biography' => $biography, 'display_order' => $order, 'status' => $status],
            'errors' => $errors,
        ];
    }

    /** A record id from the browser: 1 to 4294967295 as plain digits, else null. */
    public static function id(mixed $value): ?int
    {
        if (!is_string($value) || preg_match('/^[1-9][0-9]{0,9}$/', $value) !== 1) {
            return null;
        }
        $id = (int) $value;

        return $id <= 4294967295 ? $id : null;
    }

    /**
     * List filters from the query string. Anything unknown falls back to "no filter".
     *
     * @param array<string,mixed> $query
     * @return array{q:string,category:string,status:string}
     */
    public static function filters(array $query): array
    {
        $q = is_string($query['q'] ?? null) && mb_check_encoding($query['q'], 'UTF-8') && strlen($query['q']) <= 2000 ? $query['q'] : '';
        $q = trim(preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', $q) ?? '');
        $q = mb_substr($q, 0, self::SEARCH_MAX, 'UTF-8');

        $category = is_string($query['category'] ?? null) && array_key_exists($query['category'], self::CATEGORIES) ? $query['category'] : '';
        $status = is_string($query['status'] ?? null) ? strtolower($query['status']) : 'all';
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'all';
        }

        return ['q' => $q, 'category' => $category, 'status' => $status];
    }

    /** Target status of a status change: exactly "active" or "inactive", else null. */
    public static function targetStatus(mixed $value): ?string
    {
        return is_string($value) && in_array($value, self::STATUSES, true) ? $value : null;
    }

    /**
     * A submitted list of ids (for reordering): a plain array of digit strings, at most 500, or null when malformed.
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
            $id = self::id($item);
            if ($id === null) {
                return null;
            }
            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * One trimmed line of text (required).
     *
     * @param array<string,string> $errors
     */
    private static function line(mixed $value, int $max, string $field, string $requiredMessage, array &$errors): string
    {
        if ($value !== null && (!is_string($value) || strlen($value) > 2000 || !mb_check_encoding($value, 'UTF-8'))) {
            $errors[$field] = 'Enter a valid value.';

            return '';
        }
        $text = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
        if ($text === '') {
            $errors[$field] = $requiredMessage;
        } elseif (mb_strlen($text, 'UTF-8') > $max) {
            $errors[$field] = 'This must be ' . $max . ' characters or fewer.';
        } elseif (preg_match('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u', $text) === 1) {
            $errors[$field] = 'This cannot contain control or hidden characters.';
        }

        return $text;
    }
}
