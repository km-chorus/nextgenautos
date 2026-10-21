<?php

declare(strict_types=1);

/**
 * Validates the public vehicle search (spec: homepage hero search and the Vehicles page). The query
 * string is untrusted input: every value is checked here, anything invalid is DROPPED (the search then
 * simply ignores that filter) and nothing from the query is ever echoed unchecked.
 *
 * Returns: q (search words), brand (slug), model (text), year (int), min_price and max_price (whole GHS amounts),
 * availability (whitelist), sort (whitelist, default newest), page (>= 1).
 */
final class PublicVehicleSearchValidator
{
    public const MAX_PAGE = 500;
    public const SORTS = ['newest', 'price_asc', 'price_desc'];
    public const AVAILABILITY = ['available', 'reserved'];

    /**
     * @param array<string,mixed> $query typically the parsed query string
     * @return array{q:?string,brand:?string,model:?string,year:?int,min_price:?int,max_price:?int,availability:?string,sort:string,page:int}
     */
    public static function fromQuery(array $query): array
    {
        $brand = self::text($query['brand'] ?? null, 100);
        if ($brand !== null && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $brand) !== 1) {
            $brand = null;
        }

        $year = self::digits($query['year'] ?? null, 4);
        $year = ($year !== null && $year >= 1900 && $year <= 2100) ? $year : null;

        $price = self::digits($query['max_price'] ?? null, 10);
        $price = ($price !== null && $price >= 1 && $price <= 2000000000) ? $price : null;

        $min = self::digits($query['min_price'] ?? null, 10);
        $min = ($min !== null && $min >= 1 && $min <= 2000000000) ? $min : null;

        $availability = self::text($query['availability'] ?? null, 20);
        $availability = in_array($availability, self::AVAILABILITY, true) ? $availability : null;

        $sort = self::text($query['sort'] ?? null, 20);
        $sort = in_array($sort, self::SORTS, true) ? $sort : 'newest';

        $q = self::text($query['q'] ?? null, 80);
        $q = $q === null ? null : trim((string) preg_replace('/\s+/u', ' ', $q));

        $page = self::digits($query['page'] ?? null, 4) ?? 1;

        return [
            'q'            => $q === '' ? null : $q,
            'brand'        => $brand,
            'model'        => self::text($query['model'] ?? null, 100),
            'year'         => $year,
            'min_price'    => $min,
            'max_price'    => $price,
            'availability' => $availability,
            'sort'         => $sort,
            'page'         => max(1, min($page, self::MAX_PAGE)),
        ];
    }

    /** True when at least one filter (not the sort or the page number) is set. */
    public static function hasFilters(array $filters): bool
    {
        foreach (['q', 'brand', 'model', 'year', 'min_price', 'max_price', 'availability'] as $key) {
            if (($filters[$key] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    /** The query-string parts that describe this search (no page; sort only when not the default). */
    public static function toQuery(array $filters): array
    {
        $q = [];
        foreach (['q', 'brand', 'model', 'year', 'min_price', 'max_price', 'availability'] as $key) {
            if (($filters[$key] ?? null) !== null) {
                $q[$key] = $filters[$key];
            }
        }
        if (($filters['sort'] ?? 'newest') !== 'newest') {
            $q['sort'] = $filters['sort'];
        }

        return $q;
    }

    private static function text(mixed $value, int $max): ?string
    {
        if (!is_string($value)) {
            return null;   // arrays (brand[]=x) and anything else are ignored
        }
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $max || preg_match('/[\x00-\x1F\x7F]/', $value) === 1 || !mb_check_encoding($value, 'UTF-8')) {
            return null;
        }

        return $value;
    }

    private static function digits(mixed $value, int $maxLength): ?int
    {
        if (!is_string($value) || preg_match('/^[0-9]{1,' . $maxLength . '}$/', $value) !== 1) {
            return null;
        }

        return (int) $value;
    }
}
