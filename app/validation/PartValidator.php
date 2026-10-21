<?php

declare(strict_types=1);

/**
 * Input rules for the parts catalogue (spec 16_spec_parts_catalogue.md, phase 1). Pure functions: no database, no HTML.
 * Everything from the browser is untrusted; the form's own attributes are only a convenience.
 *
 * validate() cleans the part fields and the fitment rows. Whether a category or make actually exists (and is active) is
 * checked by PartService against the database.
 */
final class PartValidator
{
    public const NAME_MAX = 150;
    public const PART_NUMBER_MAX = 100;
    public const DESCRIPTION_MAX = 5000;
    public const MODEL_MAX = 100;
    public const CATEGORY_NAME_MAX = 100;
    public const SLUG_MAX = 160;
    public const PRICE_MAX = 9999999999.99;
    public const YEAR_MIN = 1900;
    public const YEAR_MAX = 2100;
    public const FITMENTS_MAX = 20;
    public const PHOTOS_MAX = 8;
    public const PAGE_SIZE = 20;
    public const SEARCH_MAX = 100;
    public const DEFAULT_CURRENCY = 'GHS';

    public const CONDITIONS = ['new' => 'New', 'used' => 'Used'];
    public const STOCK = ['in_stock' => 'In stock', 'out_of_stock' => 'Out of stock'];

    /**
     * @param array<string,mixed> $post
     * @return array{values:array<string,mixed>,errors:array<string,string>,fitments:list<array{brand_id:string,model:string,year_from:string,year_to:string}>}
     *   values: category_id (?int), name, part_number (?string), condition, price (?string, 2 decimals), currency,
     *           stock_status, description (?string), is_published (0|1);
     *   fitments: the rows as typed (blank rows dropped), for showing the form again;
     *   errors: field => message ('fitments' for the fitment rows as a whole, 'fitment_N' per row).
     */
    public static function validate(array $post): array
    {
        $errors = [];
        $text = static fn (string $key): string => is_string($post[$key] ?? null) ? trim($post[$key]) : '';

        $categoryId = self::id($post['category_id'] ?? null);
        if ($categoryId === null) {
            $errors['category_id'] = 'Choose the type of part.';
        }

        $name = preg_replace('/\s+/u', ' ', $text('name')) ?? '';
        if ($name === '') {
            $errors['name'] = 'Enter the part name.';
        } elseif (mb_strlen($name) > self::NAME_MAX || self::hasControl($name) || preg_match('/[<>]/', $name) === 1) {
            $errors['name'] = 'Use up to ' . self::NAME_MAX . ' characters, without < or >.';
        }

        $partNumber = $text('part_number');
        if ($partNumber !== '' && (strlen($partNumber) > self::PART_NUMBER_MAX || preg_match('/^[A-Za-z0-9][A-Za-z0-9 .\/_-]*$/', $partNumber) !== 1)) {
            $errors['part_number'] = 'Use letters, digits, spaces, dots, slashes, hyphens or underscores (up to ' . self::PART_NUMBER_MAX . ').';
        }

        $condition = $text('condition');
        if (!isset(self::CONDITIONS[$condition])) {
            $errors['condition'] = 'Choose New or Used.';
            $condition = 'new';
        }

        $price = self::price($text('price'));
        if ($price === null) {
            $errors['price'] = 'Enter the price as a number, for example 450 or 450.50.';
        }

        $currency = $text('currency') === '' ? self::DEFAULT_CURRENCY : $text('currency');
        if ($currency !== self::DEFAULT_CURRENCY) {
            $errors['currency'] = 'Choose a currency from the list.';
            $currency = self::DEFAULT_CURRENCY;
        }

        $stock = $text('stock_status');
        if (!isset(self::STOCK[$stock])) {
            $errors['stock_status'] = 'Choose In stock or Out of stock.';
            $stock = 'in_stock';
        }

        $description = str_replace(["\r\n", "\r"], "\n", is_string($post['description'] ?? null) ? trim($post['description']) : '');
        if (mb_strlen($description) > self::DESCRIPTION_MAX || preg_match('/[^\P{Cc}\n\t]/u', $description) === 1) {
            $errors['description'] = 'Use plain text, up to ' . self::DESCRIPTION_MAX . ' characters.';
        }

        $published = ($post['is_published'] ?? '0') === '1' ? 1 : 0;

        [$fitments, $fitmentErrors] = self::fitments($post['fitments'] ?? []);
        $errors += $fitmentErrors;

        return [
            'values' => [
                'category_id'  => $categoryId,
                'name'         => $name,
                'part_number'  => $partNumber === '' ? null : $partNumber,
                'condition'    => $condition,
                'price'        => $price,
                'currency'     => $currency,
                'stock_status' => $stock,
                'description'  => $description === '' ? null : $description,
                'is_published' => $published,
            ],
            'errors'   => $errors,
            'fitments' => $fitments,
        ];
    }

    /**
     * The fitment rows from fitments[N][brand_id|model|year_from|year_to]. Completely blank rows are ignored.
     *
     * @return array{0:list<array{brand_id:string,model:string,year_from:string,year_to:string}>,1:array<string,string>}
     */
    public static function fitments(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [[], ['fitments' => 'The compatible vehicles could not be read. Please enter them again.']];
        }
        $clean = [];
        $errors = [];
        foreach (array_values($rows) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $field = static fn (string $k): string => is_string($row[$k] ?? null) ? trim($row[$k]) : '';
            $entry = ['brand_id' => $field('brand_id'), 'model' => preg_replace('/\s+/u', ' ', $field('model')) ?? '',
                'year_from' => $field('year_from'), 'year_to' => $field('year_to')];
            if (implode('', $entry) === '') {
                continue;
            }
            $clean[] = $entry;
        }
        if (count($clean) > self::FITMENTS_MAX) {
            $errors['fitments'] = 'Add at most ' . self::FITMENTS_MAX . ' compatible vehicles.';
        }
        foreach ($clean as $n => $entry) {
            $problem = null;
            if (self::id($entry['brand_id']) === null) {
                $problem = 'Choose the make.';
            } elseif (mb_strlen($entry['model']) > self::MODEL_MAX || self::hasControl($entry['model']) || preg_match('/[<>]/', $entry['model']) === 1) {
                $problem = 'Use up to ' . self::MODEL_MAX . ' characters for the model, without < or >.';
            } else {
                $from = self::year($entry['year_from']);
                $to = self::year($entry['year_to']);
                if (($entry['year_from'] !== '' && $from === null) || ($entry['year_to'] !== '' && $to === null)) {
                    $problem = 'Years must be between ' . self::YEAR_MIN . ' and ' . self::YEAR_MAX . '.';
                } elseif ($from !== null && $to !== null && $from > $to) {
                    $problem = 'The first year must not be after the last year.';
                }
            }
            if ($problem !== null) {
                $errors['fitment_' . $n] = $problem;
            }
        }

        return [$clean, $errors];
    }

    /**
     * Fitment rows ready for the database (only call when fitments() reported no errors).
     *
     * @param list<array{brand_id:string,model:string,year_from:string,year_to:string}> $rows
     * @return list<array{brand_id:int,model:?string,year_from:?int,year_to:?int}>
     */
    public static function fitmentRecords(array $rows): array
    {
        return array_map(static fn (array $r): array => [
            'brand_id'  => (int) $r['brand_id'],
            'model'     => $r['model'] === '' ? null : $r['model'],
            'year_from' => self::year($r['year_from']),
            'year_to'   => self::year($r['year_to']),
        ], $rows);
    }

    /** @return array{values:array{name:string,display_order:int,status:string},errors:array<string,string>} */
    public static function category(array $post): array
    {
        $errors = [];
        $name = preg_replace('/\s+/u', ' ', is_string($post['name'] ?? null) ? trim($post['name']) : '') ?? '';
        if ($name === '') {
            $errors['name'] = 'Enter the category name.';
        } elseif (mb_strlen($name) > self::CATEGORY_NAME_MAX || self::hasControl($name) || preg_match('/[<>]/', $name) === 1) {
            $errors['name'] = 'Use up to ' . self::CATEGORY_NAME_MAX . ' characters, without < or >.';
        } elseif (self::slugify($name) === '') {
            $errors['name'] = 'Use at least one letter or digit.';
        }
        $order = is_string($post['display_order'] ?? null) ? trim($post['display_order']) : '';
        if ($order !== '' && preg_match('/^[0-9]{1,4}$/', $order) !== 1) {
            $errors['display_order'] = 'Enter a whole number from 0 to 9999.';
        }
        $status = ($post['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        return ['values' => ['name' => $name, 'display_order' => (int) $order, 'status' => $status], 'errors' => $errors];
    }

    /**
     * List filters from the query string. Anything invalid falls back to "no filter" / page 1.
     *
     * @return array{q:string,category:?int,brand:?int,published:string,stock:string,page:int}
     */
    public static function filters(array $query): array
    {
        $q = is_string($query['q'] ?? null) ? trim($query['q']) : '';
        if (mb_strlen($q) > self::SEARCH_MAX || self::hasControl($q)) {
            $q = '';
        }
        $published = in_array($query['published'] ?? null, ['yes', 'no'], true) ? (string) $query['published'] : 'all';
        $stock = isset(self::STOCK[$query['stock'] ?? '']) ? (string) $query['stock'] : 'all';
        $page = is_string($query['page'] ?? null) && preg_match('/^[1-9][0-9]{0,5}$/', $query['page']) === 1 ? (int) $query['page'] : 1;

        return ['q' => $q, 'category' => self::id($query['category'] ?? null), 'brand' => self::id($query['brand'] ?? null),
            'published' => $published, 'stock' => $stock, 'page' => $page];
    }

    /** A record id from the browser: 1 to 4294967295 as plain digits, else null. */
    public static function id(mixed $value): ?int
    {
        if (is_int($value)) {
            $value = (string) $value;
        }
        if (!is_string($value) || preg_match('/^[1-9][0-9]{0,9}$/', $value) !== 1 || (int) $value > 4294967295) {
            return null;
        }

        return (int) $value;
    }

    public static function slugify(string $text): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($text)) ?? '';

        return rtrim(substr(trim($slug, '-'), 0, self::SLUG_MAX), '-');
    }

    /** "450", "450.5", "1,250.00" => "450.00" ... ; null when not a valid non-negative amount. */
    private static function price(string $raw): ?string
    {
        $raw = str_replace([',', ' '], '', $raw);
        if (preg_match('/^[0-9]{1,10}(\.[0-9]{1,2})?$/', $raw) !== 1 || (float) $raw > self::PRICE_MAX) {
            return null;
        }

        return number_format((float) $raw, 2, '.', '');
    }

    private static function year(string $raw): ?int
    {
        if (preg_match('/^[0-9]{4}$/', $raw) !== 1) {
            return null;
        }
        $year = (int) $raw;

        return $year >= self::YEAR_MIN && $year <= self::YEAR_MAX ? $year : null;
    }

    private static function hasControl(string $value): bool
    {
        return preg_match('/\p{Cc}/u', $value) === 1;
    }
}
