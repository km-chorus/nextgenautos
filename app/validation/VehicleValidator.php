<?php

declare(strict_types=1);

/**
 * Validation and normalisation for Vehicle Management (spec 06_spec_vehicle_managt).
 *
 * Pure functions, no database. Limits come from the `vehicles` table:
 * model/trim VARCHAR(100), engine_size/transmission/fuel_type/drive_type
 * VARCHAR(50), slug VARCHAR(160), year CHECK 1900..2100, price DECIMAL(12,2)
 * >= 0, currency CHAR(3), mileage INT UNSIGNED with a unit, key_features a JSON
 * array, availability_status ENUM('available','reserved','sold','unavailable').
 *
 * Whether the brand exists and is active, and whether the slug is unique, need
 * the database and are checked by VehicleService (and enforced again by the
 * foreign key and the UNIQUE key). HTML attributes in the form are a
 * convenience only; this is the real check.
 */
final class VehicleValidator
{
    public const MIN_YEAR = 1900;
    public const ABSOLUTE_MAX_YEAR = 2100;
    public const TEXT_MAX = 100;
    public const SHORT_MAX = 50;
    public const SLUG_MAX = 160;
    public const DESCRIPTION_MAX = 5000;
    public const SHIPPING_MAX = 2000;
    public const FEATURE_MAX = 100;
    public const FEATURES_LIMIT = 40;
    public const MILEAGE_MAX = 9999999;
    public const SEARCH_MAX = 100;
    public const PAGE_SIZE = 20;
    public const STATUSES = ['available', 'reserved', 'sold', 'unavailable'];
    public const MILEAGE_UNITS = ['km', 'mi'];
    public const DEFAULT_CURRENCY = 'GHS';
    public const SLUG_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    public const MESSAGE_BRAND = 'Please select a valid vehicle brand.';
    public const MESSAGE_PRICE = 'Please enter a valid vehicle price.';

    /**
     * Latest model year accepted: next calendar year (new models are sold before
     * their year starts), never beyond the database limit. Computed, not hardcoded,
     * so it does not become obsolete.
     */
    public static function maxYear(?DateTimeImmutable $now = null): int
    {
        $year = (int) ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y');

        return min($year + 1, self::ABSOLUTE_MAX_YEAR);
    }

    /**
     * @param array<string,mixed> $input raw browser data (untrusted)
     * @param list<string> $currencies currencies this record may use
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public static function validate(array $input, int $maxYear, array $currencies = [self::DEFAULT_CURRENCY]): array
    {
        $errors = [];

        // ---- brand (existence and "active" are checked by the service)
        $brandRaw = $input['brand_id'] ?? null;
        $brandId = self::id($brandRaw);
        if ($brandId === null) {
            $errors['brand_id'] = self::MESSAGE_BRAND;
        }

        // ---- year
        $yearRaw = is_string($input['year'] ?? null) ? trim($input['year']) : '';
        $year = $yearRaw;
        if ($yearRaw === '') {
            $errors['year'] = 'Enter the model year.';
        } elseif (preg_match('/^[0-9]{4}$/', $yearRaw) !== 1 || (int) $yearRaw < self::MIN_YEAR || (int) $yearRaw > $maxYear) {
            $errors['year'] = 'Enter a four-digit year from ' . self::MIN_YEAR . ' to ' . $maxYear . '.';
        } else {
            $year = (int) $yearRaw;
        }

        // ---- text fields
        $model = self::line($input['model'] ?? null, self::TEXT_MAX, true, 'model', 'Enter the model.', $errors);
        $trim = self::line($input['trim'] ?? null, self::TEXT_MAX, false, 'trim', '', $errors);
        $engine = self::line($input['engine_size'] ?? null, self::SHORT_MAX, false, 'engine_size', '', $errors);
        $transmission = self::line($input['transmission'] ?? null, self::SHORT_MAX, false, 'transmission', '', $errors);
        $fuel = self::line($input['fuel_type'] ?? null, self::SHORT_MAX, false, 'fuel_type', '', $errors);
        $drive = self::line($input['drive_type'] ?? null, self::SHORT_MAX, false, 'drive_type', '', $errors);

        // ---- mileage (optional; a figure needs its unit)
        $mileageRaw = is_string($input['mileage'] ?? null) ? trim($input['mileage']) : '';
        $mileage = null;
        $unitRaw = is_string($input['mileage_unit'] ?? null) ? strtolower(trim($input['mileage_unit'])) : '';
        $unit = in_array($unitRaw, self::MILEAGE_UNITS, true) ? $unitRaw : null;
        if ($unitRaw !== '' && $unit === null) {
            $errors['mileage_unit'] = 'Choose kilometres or miles.';
        }
        if ($mileageRaw !== '') {
            if (preg_match('/^[0-9]{1,7}$/', $mileageRaw) !== 1 || (int) $mileageRaw > self::MILEAGE_MAX) {
                $errors['mileage'] = 'Enter the mileage as a whole number, up to ' . number_format(self::MILEAGE_MAX) . '.';
            } else {
                $mileage = (int) $mileageRaw;
                if ($unit === null && !isset($errors['mileage_unit'])) {
                    $errors['mileage_unit'] = 'Choose kilometres or miles for the mileage.';
                }
            }
        }
        if ($mileage === null) {
            $unit = null; // a unit without a figure means nothing
        }

        // ---- price (text in, text out: money is never put through floating point)
        $priceRaw = is_string($input['price'] ?? null) ? trim($input['price']) : '';
        $price = $priceRaw;
        if (preg_match('/^[0-9]{1,10}(\.[0-9]{1,2})?$/', $priceRaw) !== 1) {
            $errors['price'] = self::MESSAGE_PRICE;
        } else {
            [$whole, $fraction] = array_pad(explode('.', $priceRaw, 2), 2, '');
            $price = (ltrim($whole, '0') === '' ? '0' : ltrim($whole, '0')) . '.' . str_pad($fraction, 2, '0');
        }

        // ---- currency
        $currency = is_string($input['currency'] ?? null) ? strtoupper(trim($input['currency'])) : '';
        if (!in_array($currency, $currencies, true)) {
            $errors['currency'] = 'Choose a currency from the list.';
        }

        // ---- description, shipping information
        $description = self::block($input['description'] ?? null, self::DESCRIPTION_MAX, 'description', $errors);
        $shipping = self::block($input['shipping_info'] ?? null, self::SHIPPING_MAX, 'shipping_info', $errors);

        // ---- key features: one per line
        $features = self::features($input['key_features'] ?? null, $errors);

        // ---- yes/no flags and status
        $duty = self::flag($input['duty_included'] ?? null, 'duty_included', $errors);
        $published = self::flag($input['is_published'] ?? null, 'is_published', $errors);
        $status = is_string($input['availability_status'] ?? null) ? strtolower(trim($input['availability_status'])) : '';
        if (!in_array($status, self::STATUSES, true)) {
            $errors['availability_status'] = 'Choose a status from the list.';
            $status = '';
        }

        // ---- slug (blank = generated by the service; a typed slug is never overwritten)
        $slugRaw = $input['slug'] ?? null;
        $slug = '';
        if ($slugRaw !== null && (!is_string($slugRaw) || !mb_check_encoding($slugRaw, 'UTF-8'))) {
            $errors['slug'] = 'Enter a valid vehicle URL.';
        } else {
            $slug = strtolower(trim((string) $slugRaw));
            if ($slug !== '' && strlen($slug) > self::SLUG_MAX) {
                $errors['slug'] = 'The vehicle URL must be ' . self::SLUG_MAX . ' characters or fewer.';
            } elseif ($slug !== '' && preg_match(self::SLUG_PATTERN, $slug) !== 1) {
                $errors['slug'] = 'Use only lowercase letters, numbers and single hyphens, for example 2024-toyota-camry-xse.';
            }
        }

        return [
            'values' => [
                'brand_id'            => $brandId ?? '',
                'year'                => $year,
                'model'               => $model,
                'trim'                => $trim,
                'engine_size'         => $engine,
                'mileage'             => isset($errors['mileage']) ? $mileageRaw : $mileage,
                'mileage_unit'        => isset($errors['mileage_unit']) ? ($unitRaw !== '' ? $unitRaw : null) : $unit,
                'transmission'        => $transmission,
                'fuel_type'           => $fuel,
                'drive_type'          => $drive,
                'price'               => $price,
                'currency'            => $currency,
                'description'         => $description,
                'key_features'        => $features,
                'shipping_info'       => $shipping,
                'duty_included'       => $duty,
                'availability_status' => $status,
                'is_published'        => $published,
                'slug'                => $slug,
            ],
            'errors' => $errors,
        ];
    }

    /** "2024 Toyota Camry XSE" => "2024-toyota-camry-xse" (a-z, 0-9 and single hyphens). */
    public static function slugify(string $text): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($text)) ?? '';

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

    /**
     * List filters, search and page from the query string. Anything invalid falls
     * back to "no filter" / page 1.
     *
     * @param array<string,mixed> $query
     * @return array{q:string,brand:?int,status:string,year:?int,page:int}
     */
    public static function filters(array $query, int $maxYear): array
    {
        $q = is_string($query['q'] ?? null) && mb_check_encoding($query['q'], 'UTF-8') && strlen($query['q']) <= 2000 ? $query['q'] : '';
        $q = trim(preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', $q) ?? '');
        $q = mb_substr($q, 0, self::SEARCH_MAX, 'UTF-8');

        $status = is_string($query['status'] ?? null) ? strtolower($query['status']) : 'all';
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'all';
        }

        $year = null;
        if (is_string($query['year'] ?? null) && preg_match('/^[0-9]{4}$/', $query['year']) === 1
            && (int) $query['year'] >= self::MIN_YEAR && (int) $query['year'] <= $maxYear) {
            $year = (int) $query['year'];
        }

        $page = 1;
        if (is_string($query['page'] ?? null) && preg_match('/^[1-9][0-9]{0,5}$/', $query['page']) === 1) {
            $page = (int) $query['page'];
        }

        return ['q' => $q, 'brand' => self::id($query['brand'] ?? null), 'status' => $status, 'year' => $year, 'page' => $page];
    }

    /** Target status of a status change: one of the four values, else null. */
    public static function targetStatus(mixed $value): ?string
    {
        return is_string($value) && in_array($value, self::STATUSES, true) ? $value : null;
    }

    /* ---------------------------------------------------------------- */

    /**
     * One trimmed line of text. Empty = null unless required.
     *
     * @param array<string,string> $errors
     */
    private static function line(mixed $value, int $max, bool $required, string $field, string $requiredMessage, array &$errors): ?string
    {
        if ($value !== null && (!is_string($value) || strlen($value) > 2000 || !mb_check_encoding($value, 'UTF-8'))) {
            $errors[$field] = 'Enter a valid value.';

            return null;
        }
        $text = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
        if ($text === '') {
            if ($required) {
                $errors[$field] = $requiredMessage;
            }

            return $required ? '' : null;
        }
        if (mb_strlen($text, 'UTF-8') > $max) {
            $errors[$field] = 'This must be ' . $max . ' characters or fewer.';
        } elseif (preg_match('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u', $text) === 1) {
            $errors[$field] = 'This cannot contain control or hidden characters.';
        }

        return $text;
    }

    /**
     * Multi-line text (newlines and tabs allowed). Empty = null.
     *
     * @param array<string,string> $errors
     */
    private static function block(mixed $value, int $max, string $field, array &$errors): ?string
    {
        if ($value !== null && (!is_string($value) || strlen($value) > 60000 || !mb_check_encoding($value, 'UTF-8'))) {
            $errors[$field] = 'This contains characters that are not allowed.';

            return null;
        }
        $text = trim(str_replace(["\r\n", "\r"], "\n", (string) $value));
        if (mb_strlen($text, 'UTF-8') > $max) {
            $errors[$field] = 'This must be ' . $max . ' characters or fewer.';
        } elseif (preg_match('/[^\P{Cc}\n\t]|[\p{Cf}\p{Zl}\p{Zp}]/u', $text) === 1) {
            $errors[$field] = 'This contains characters that are not allowed.';
        }

        return $text === '' ? null : $text;
    }

    /**
     * Key features: one per line, blank lines ignored, repeats removed (ignoring case).
     *
     * @param array<string,string> $errors
     * @return list<string>
     */
    private static function features(mixed $value, array &$errors): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (!is_string($value) || strlen($value) > 60000 || !mb_check_encoding($value, 'UTF-8')) {
            $errors['key_features'] = 'The features contain characters that are not allowed.';

            return [];
        }
        $features = [];
        $seen = [];
        foreach (preg_split('/\R/u', $value) ?: [] as $line) {
            $feature = trim(preg_replace('/\s+/u', ' ', $line) ?? '');
            if ($feature === '') {
                continue;
            }
            if (mb_strlen($feature, 'UTF-8') > self::FEATURE_MAX) {
                $errors['key_features'] = 'Each feature must be ' . self::FEATURE_MAX . ' characters or fewer.';
                continue;
            }
            if (preg_match('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u', $feature) === 1) {
                $errors['key_features'] = 'The features contain characters that are not allowed.';
                continue;
            }
            $key = mb_strtolower($feature, 'UTF-8');
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $features[] = $feature;
            }
        }
        if (count($features) > self::FEATURES_LIMIT) {
            $errors['key_features'] = 'Enter at most ' . self::FEATURES_LIMIT . ' features.';
        }

        return $features;
    }

    /**
     * A Yes/No control: "1" or "0". Anything else is an error (never guessed).
     *
     * @param array<string,string> $errors
     */
    private static function flag(mixed $value, string $field, array &$errors): int
    {
        if ($value === '1' || $value === '0') {
            return (int) $value;
        }
        $errors[$field] = 'Choose Yes or No.';

        return 0;
    }
}
