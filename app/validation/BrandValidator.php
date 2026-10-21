<?php

declare(strict_types=1);

/**
 * Validation and normalisation for Brand Management (spec 05_spec_brand_crud.md).
 *
 * Pure functions, no database: they only check shape and limits taken from the
 * `brands` table (name/slug VARCHAR(100), display_order INT UNSIGNED, status
 * ENUM('active','inactive'), CHECK slug REGEXP '^[a-z0-9]+(-[a-z0-9]+)*$').
 * Uniqueness needs the database and is checked by BrandService (and enforced
 * again by the table's UNIQUE keys).
 *
 * HTML attributes in the forms are a convenience only; this is the real check.
 */
final class BrandValidator
{
    public const NAME_MAX = 100;
    public const SLUG_MAX = 100;
    public const DESCRIPTION_MAX = 2000;
    public const ORDER_MAX = 9999;
    public const SEARCH_MAX = 100;
    public const STATUSES = ['active', 'inactive'];
    public const SLUG_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    /**
     * Normalises and validates a create/update submission.
     *
     * @param array<string,mixed> $input raw browser data (untrusted)
     * @return array{values:array{name:string,slug:string,description:?string,display_order:int|string,status:string},errors:array<string,string>}
     *         `values` holds the cleaned data (display_order stays the typed text when it is invalid, so the form can show it again)
     */
    public static function validate(array $input): array
    {
        $errors = [];

        // ---- name
        $name = self::text($input['name'] ?? null);
        if ($name === null) {
            $errors['name'] = 'Enter a valid brand name.';
            $name = '';
        } else {
            $name = self::collapseSpaces($name);
            if ($name === '') {
                $errors['name'] = 'Enter the brand name.';
            } elseif (mb_strlen($name, 'UTF-8') > self::NAME_MAX) {
                $errors['name'] = 'The brand name must be ' . self::NAME_MAX . ' characters or fewer.';
            } elseif (preg_match('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u', $name) === 1) {
                $errors['name'] = 'The brand name cannot contain control or hidden characters.';
            }
        }

        // ---- slug (blank = generate from the name; a typed slug is never overwritten)
        $slugInput = self::text($input['slug'] ?? null);
        $slug = '';
        if ($slugInput === null) {
            $errors['slug'] = 'Enter a valid slug.';
        } else {
            $slugInput = strtolower(trim($slugInput));
            if ($slugInput !== '') {
                $slug = $slugInput;
                if (strlen($slug) > self::SLUG_MAX) {
                    $errors['slug'] = 'The slug must be ' . self::SLUG_MAX . ' characters or fewer.';
                } elseif (preg_match(self::SLUG_PATTERN, $slug) !== 1) {
                    $errors['slug'] = 'Use only lowercase letters, numbers and single hyphens, for example mercedes-benz.';
                }
            } elseif (!isset($errors['name'])) {
                $slug = self::slugify($name);
                if ($slug === '') {
                    $errors['name'] = 'The brand name must contain at least one letter or number.';
                }
            }
        }

        // ---- description (optional)
        $description = null;
        $descriptionInput = self::text($input['description'] ?? null);
        if ($descriptionInput === null) {
            $errors['description'] = 'The description contains characters that are not allowed.';
        } else {
            $descriptionInput = trim(str_replace(["\r\n", "\r"], "\n", $descriptionInput));
            if (mb_strlen($descriptionInput, 'UTF-8') > self::DESCRIPTION_MAX) {
                $errors['description'] = 'The description must be ' . self::DESCRIPTION_MAX . ' characters or fewer.';
            } elseif (preg_match('/[^\P{Cc}\n\t]|[\p{Cf}\p{Zl}\p{Zp}]/u', $descriptionInput) === 1) {
                $errors['description'] = 'The description contains characters that are not allowed.';
            }
            $description = $descriptionInput === '' ? null : $descriptionInput;
        }

        // ---- display order
        $orderRaw = $input['display_order'] ?? null;
        $orderText = is_string($orderRaw) ? trim($orderRaw) : '';
        $order = $orderText;
        if ($orderText === '') {
            $errors['display_order'] = 'Enter the display order.';
        } elseif (preg_match('/^[0-9]{1,5}$/', $orderText) !== 1 || (int) $orderText > self::ORDER_MAX) {
            $errors['display_order'] = 'Enter a whole number from 0 to ' . self::ORDER_MAX . '.';
        } else {
            $order = (int) $orderText;
        }

        // ---- status
        $status = is_string($input['status'] ?? null) ? strtolower(trim($input['status'])) : '';
        if (!in_array($status, self::STATUSES, true)) {
            $errors['status'] = 'Choose Active or Inactive.';
            $status = '';
        }

        return [
            'values' => [
                'name'          => $name,
                'slug'          => $slug,
                'description'   => $description,
                'display_order' => $order,
                'status'        => $status,
            ],
            'errors' => $errors,
        ];
    }

    /** "Mercedes-Benz" => "mercedes-benz". Anything that is not a-z / 0-9 becomes a single hyphen. */
    public static function slugify(string $name): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($name)) ?? '';

        return rtrim(substr(trim($slug, '-'), 0, self::SLUG_MAX), '-');
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

    /** List filters from the query string. Unknown values fall back to "all" / "". */
    public static function filters(array $query): array
    {
        $q = self::text($query['q'] ?? null) ?? '';
        $q = trim(preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', $q) ?? '');
        $q = mb_substr($q, 0, self::SEARCH_MAX, 'UTF-8');

        $status = is_string($query['status'] ?? null) ? strtolower($query['status']) : 'all';
        if (!in_array($status, ['all', 'active', 'inactive'], true)) {
            $status = 'all';
        }

        return ['q' => $q, 'status' => $status];
    }

    /** Target status of a status change: exactly "active" or "inactive", else null. */
    public static function targetStatus(mixed $value): ?string
    {
        return is_string($value) && in_array($value, self::STATUSES, true) ? $value : null;
    }

    /**
     * A single string from the browser, or null when it is an array, not valid
     * UTF-8, or too long to be reasonable (so a huge value cannot be processed).
     * Missing values count as an empty string.
     */
    private static function text(mixed $value): ?string
    {
        if ($value === null) {
            return '';
        }
        if (!is_string($value) || strlen($value) > 20000 || !mb_check_encoding($value, 'UTF-8')) {
            return null;
        }

        return $value;
    }

    private static function collapseSpaces(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
