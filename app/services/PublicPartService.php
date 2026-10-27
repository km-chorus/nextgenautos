<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/repositories/PartRepository.php';
require_once __DIR__ . '/PartService.php';

/**
 * Public data for "Latest Parts & Accessories" on the homepage (spec 16, phase 1). Only published, non-archived parts;
 * every value is plain text for the view to escape. Nothing is invented: a part without compatible vehicles says
 * "confirm compatibility with us" rather than claiming to fit everything.
 *
 * The section offers two ways to narrow the cards: by type (category) and by make. The page holds up to CARD_POOL cards
 * so the browser can filter instantly (parts.js); without JavaScript a choice is a link (?parts_type= / ?parts_make=)
 * that the server answers with the matching parts.
 *
 * Spec 27 (2026-10-26): the homepage shows only parts WITH a price (owner decision); browse() serves /parts/ (every
 * published part, "fit my car" by make / model / year, category, search by name or any part number, 20 per page).
 * Price, condition and stock may be unset: the card then says "Price on request" and leaves the other two out.
 */
final class PublicPartService
{
    public const SHOWN = 8;
    public const CARD_POOL = 24;
    public const PAGE_SIZE = 20;
    public const ASSISTANT_POOL = 300;
    public const SEARCH_MAX = 100;
    /** Parts shown on a vehicle's page (spec 28); "See all" links to /parts/ for the rest. */
    public const VEHICLE_SHOWN = 8;

    private string $root;

    public function __construct(private PartRepository $parts, ?string $root = null)
    {
        $this->root = $root ?? dirname(__DIR__, 2);
    }

    public static function create(): self
    {
        return new self(new PartRepository(Database::connection()));
    }

    /**
     * @param mixed $type the ?parts_type= value (a category slug) from the URL, untrusted
     * @param mixed $make the ?parts_make= value (a brand slug) from the URL, untrusted
     * @return array{cards:list<array<string,mixed>>,types:list<array{slug:string,name:string}>,makes:list<array{slug:string,name:string}>,type:?string,make:?string}
     */
    public function homepage(mixed $type = null, mixed $make = null): array
    {
        $types = $this->parts->publicCategories(true);
        $makes = array_map(static fn (array $m): array => ['slug' => $m['slug'], 'name' => $m['name']], $this->parts->publicMakes(true));
        // A filter counts only when it names a type / make that really has public parts; anything else is ignored.
        $type = self::pick($type, $types);
        $make = $type === null ? self::pick($make, $makes) : null;   // one filter at a time, like the two tabs

        $rows = $this->parts->latestPublic($type === null && $make === null ? self::CARD_POOL : self::SHOWN, $type, $make, true);

        return ['cards' => $this->cards($rows), 'types' => $types, 'makes' => $makes, 'type' => $type, 'make' => $make];
    }

    /** Every published part (newest first, up to ASSISTANT_POOL) for the site assistant, with all part numbers. */
    public function forAssistant(): array
    {
        $rows = $this->parts->latestPublic(self::ASSISTANT_POOL);
        $numbers = $this->parts->partNumbersFor(array_map(static fn (array $r): int => (int) $r['id'], $rows));
        $cards = $this->cards($rows);
        foreach ($rows as $i => $row) {
            $cards[$i]['numbers'] = $numbers[(int) $row['id']] ?? [];
        }

        return $cards;
    }

    /**
     * The /parts/ page (spec 27). Every value from the URL is untrusted and only counts when it names a real option:
     * make = a make in public parts' compatibility; model = one of that make's entered models (ignoring case); year = from
     * the oldest year entered to next year; category = a category with public parts; q = search text (name or any part
     * number). Anything else is ignored, never echoed.
     *
     * @param array<string,mixed> $query
     * @return array<string,mixed> cards, total, page, pages, filters {make,model,year,category,q}, vehicle (?string),
     *         options {makes, models (for the chosen make), modelsByMake (make slug => models), years, categories}
     */
    public function browse(array $query): array
    {
        $makes = $this->parts->publicMakes();
        $categories = $this->parts->publicCategories();
        $modelsById = $this->parts->publicModelsByMake();
        $thisYear = (int) gmdate('Y');
        $oldest = min($this->parts->publicOldestYear() ?? 1990, $thisYear);
        $years = range($thisYear + 1, $oldest);

        $make = null;
        foreach ($makes as $m) {
            if (($query['make'] ?? null) === $m['slug']) {
                $make = $m;
            }
        }
        $models = $make === null ? [] : ($modelsById[$make['id']] ?? []);
        $model = null;
        if ($make !== null && is_string($query['model'] ?? null)) {
            foreach ($models as $candidate) {
                if (mb_strtolower($candidate) === mb_strtolower(trim($query['model']))) {
                    $model = $candidate;
                }
            }
        }
        $year = null;
        if ($make !== null && is_string($query['year'] ?? null) && preg_match('/^[0-9]{4}$/', $query['year']) === 1 && in_array((int) $query['year'], $years, true)) {
            $year = (int) $query['year'];
        }
        $category = self::pick($query['category'] ?? null, $categories);
        $q = is_string($query['q'] ?? null) ? trim(preg_replace('/\s+/u', ' ', $query['q']) ?? '') : '';
        if (mb_strlen($q) > self::SEARCH_MAX || preg_match('/\p{Cc}/u', $q) === 1) {
            $q = '';
        }
        $page = is_string($query['page'] ?? null) && preg_match('/^[1-9][0-9]{0,4}$/', $query['page']) === 1 ? (int) $query['page'] : 1;

        $filters = ['category' => $category, 'q' => $q, 'brand_id' => $make['id'] ?? null, 'model' => $model, 'year' => $year];
        [$total, $rows, $fitting] = $this->parts->publicBrowse($filters, self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        if ($page > $pages) {   // a page past the end shows the last one
            $page = $pages;
            [$total, $rows, $fitting] = $this->parts->publicBrowse($filters, self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE);
        }

        $bySlug = [];
        foreach ($makes as $m) {
            $bySlug[$m['slug']] = $modelsById[$m['id']] ?? [];
        }

        return [
            'cards' => $this->cards($rows),
            'total' => $total, 'fitting' => $fitting, 'page' => $page, 'pages' => $pages,
            'filters' => ['make' => $make['slug'] ?? null, 'model' => $model, 'year' => $year, 'category' => $category, 'q' => $q],
            'vehicle' => $make === null ? null : trim($make['name'] . ' ' . ($model ?? '') . ' ' . ($year ?? '')),
            'options' => [
                'makes' => array_map(static fn (array $m): array => ['slug' => $m['slug'], 'name' => $m['name']], $makes),
                'models' => $models, 'modelsByMake' => $bySlug, 'years' => $years, 'categories' => $categories,
            ],
        ];
    }

    /**
     * Compatible, in-stock parts for a vehicle's page (spec 28). Empty cards = the page leaves the section out.
     *
     * @param array{brand_id:int,brand_slug:string,model:string,year:int} $fit from PublicVehicleService::detail()
     * @return array{cards:list<array<string,mixed>>,total:int,all:string} all = the /parts/ address for this vehicle
     */
    public function forVehicle(array $fit): array
    {
        [$total, $rows] = $this->parts->publicForVehicle($fit['brand_id'], $fit['model'], $fit['year'], self::VEHICLE_SHOWN);

        return ['cards' => $this->cards($rows), 'total' => $total,
            'all' => '/parts/?' . http_build_query(['make' => $fit['brand_slug'], 'model' => $fit['model'], 'year' => (string) $fit['year']]) . '#results'];
    }

    /**
     * "My vehicle" for the contact form when a part is requested from a "fit my car" result (?make=&model=&year=): only
     * a real make (with public parts' compatibility), one of its entered models and a valid year count. Null otherwise.
     */
    public function vehicleLine(array $query): ?string
    {
        if (!is_string($query['make'] ?? null)) {
            return null;
        }
        foreach ($this->parts->publicMakes() as $m) {
            if (($query['make'] ?? null) !== $m['slug']) {
                continue;
            }
            $model = null;
            foreach ($this->parts->publicModels($m['id']) as $candidate) {
                if (is_string($query['model'] ?? null) && mb_strtolower($candidate) === mb_strtolower(trim($query['model']))) {
                    $model = $candidate;
                }
            }
            $thisYear = (int) gmdate('Y');
            $year = is_string($query['year'] ?? null) && preg_match('/^[0-9]{4}$/', $query['year']) === 1
                && (int) $query['year'] >= PartValidator::YEAR_MIN && (int) $query['year'] <= $thisYear + 1 ? (int) $query['year'] : null;

            return trim($m['name'] . ' ' . ($model ?? '') . ' ' . ($year ?? ''));
        }

        return null;
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private function cards(array $rows): array
    {
        $fitments = $this->parts->fitmentsFor(array_map(static fn (array $r): int => (int) $r['id'], $rows));
        $cards = [];
        foreach ($rows as $row) {
            $cards[] = $this->card($row, $fitments[(int) $row['id']] ?? []);
        }

        return $cards;
    }

    /** "Brake pads (04465-02220)" for the contact form's pre-filled message, or null when no public part has this slug. */
    public function enquiryLabel(mixed $slug): ?string
    {
        if (!is_string($slug) || strlen($slug) > PartValidator::SLUG_MAX || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) !== 1) {
            return null;
        }
        $part = $this->parts->publicNameBySlug($slug);

        return $part === null ? null : $part['name'] . ($part['part_number'] !== null ? ' (part number ' . $part['part_number'] . ')' : '');
    }

    /** @param list<array{slug:string,name:string}> $options */
    private static function pick(mixed $value, array $options): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        foreach ($options as $o) {
            if ($o['slug'] === $value) {
                return $o['slug'];
            }
        }

        return null;
    }

    /** @return array<string,mixed> */
    private function card(array $row, array $fitments): array
    {
        $makes = [];
        foreach ($fitments as $f) {
            $makes[$f['brand_slug']] = true;
        }
        $labels = array_map([PartService::class, 'fitmentLabel'], $fitments);

        return [
            'name'        => (string) $row['name'],
            'slug'        => (string) $row['slug'],
            'part_number' => $row['part_number'] === null ? null : (string) $row['part_number'],
            'type'        => (string) $row['category_name'],
            'type_slug'   => (string) $row['category_slug'],
            'condition'   => PartValidator::CONDITIONS[$row['condition'] ?? ''] ?? null,          // null = not stated
            'price'       => $row['price'] === null ? null : self::price((string) $row['price'], (string) $row['currency']),   // null = on request
            'in_stock'    => $row['stock_status'] === null ? null : $row['stock_status'] === 'in_stock',   // null = not stated
            'fits'        => array_slice($labels, 0, 3),
            'fits_more'   => max(0, count($labels) - 3),
            'makes'       => array_keys($makes),
            'image'       => $row['image_path'] === null ? null : $this->thumbnail((string) $row['image_path']),
        ];
    }

    private static function price(string $price, string $currency): string
    {
        $whole = preg_match('/^\d+(\.0+)?$/', $price) === 1;

        return $currency . ' ' . number_format((float) $price, $whole ? 0 : 2, '.', ',');
    }

    /**
     * The stored thumbnail, if the path has the exact shape the image service writes and the file is a real WebP.
     *
     * @return array{url:string,width:int,height:int}|null
     */
    private function thumbnail(string $path): ?array
    {
        if (preg_match('#^uploads/parts/([a-f0-9]{32})\.webp$#D', $path, $m) !== 1) {
            return null;
        }
        $relative = 'uploads/parts/' . $m[1] . '-t.webp';
        $info = is_file($this->root . '/' . $relative) ? @getimagesize($this->root . '/' . $relative) : false;
        if ($info === false || $info[2] !== IMAGETYPE_WEBP || $info[0] < 1 || $info[1] < 1) {
            return null;
        }

        return ['url' => '/' . $relative, 'width' => $info[0], 'height' => $info[1]];
    }
}
