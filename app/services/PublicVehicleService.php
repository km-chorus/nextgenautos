<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/repositories/VehicleRepository.php';
require_once dirname(__DIR__) . '/repositories/VehicleImageRepository.php';
require_once dirname(__DIR__) . '/repositories/BrandRepository.php';
require_once dirname(__DIR__) . '/validation/PublicVehicleSearchValidator.php';
require_once dirname(__DIR__) . '/validation/VehicleValidator.php';
require_once __DIR__ . '/AccidentVehicleSettings.php';

/**
 * Vehicle data for the PUBLIC website (spec 10B card data; homepage search and the Vehicles page): turns database rows into
 * customer-facing view models. Reuses the Vehicle Management repositories (no second data layer).
 *
 * Public rules:
 *   - only published, not archived vehicles that are available or reserved are offered
 *     (the visibility rule lives in VehicleRepository::publicLatest);
 *   - only customer-facing fields leave this class: no database id, no admin or shipping data;
 *   - the link to a vehicle is built from the stored slug only after it matches the slug format
 *     (a row with a malformed slug is skipped, never linked);
 *   - the picture is the vehicle's PRIMARY image only (its small stored thumbnail), and only when
 *     the file really exists as a WebP on disk; otherwise the card shows a plain placeholder;
 *   - two queries in total (vehicles with brands, then the primary images of those vehicles in
 *     one query), never one per vehicle.
 *
 * Returns plain arrays: no markup, no HTTP.
 */
class PublicVehicleService
{
    public const PER_PAGE = 20;   // vehicles per page of the public lists (owner request 2026-10-22); only these rows are read (LIMIT/OFFSET)
    private const PHOTOS = 5;   // photographs offered on one card (the first plus four)
    private const PRICE_STEPS = [5000, 10000, 20000, 30000, 50000, 75000, 100000, 150000, 200000, 300000, 500000, 750000, 1000000, 2000000, 5000000];

    private string $root;

    public function __construct(private VehicleRepository $vehicles, private VehicleImageRepository $images, ?string $projectRoot = null, private ?BrandRepository $brands = null)
    {
        $this->root = $projectRoot ?? dirname(__DIR__, 2);
    }

    public static function create(): self
    {
        $pdo = Database::connection();

        return new self(new VehicleRepository($pdo), new VehicleImageRepository($pdo), null, new BrandRepository($pdo));
    }

    /**
     * One page of the public inventory with the given (validated) filters.
     *
     * @param array<string,mixed> $filters validated by PublicVehicleSearchValidator
     * @return array{cards:list<array<string,mixed>>,total:int,page:int,pages:int}
     */
    public function search(array $filters, int $perPage = self::PER_PAGE): array
    {
        $perPage = max(1, min($perPage, 48));
        $filters['section'] = VehicleRepository::SECTION_NORMAL;      // spec 23: accident vehicles have their own page
        $total = $this->vehicles->publicCount($filters);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min((int) ($filters['page'] ?? 1), $pages));
        $rows = $total === 0 ? [] : $this->vehicles->publicSearch($filters, $perPage, ($page - 1) * $perPage);

        return ['cards' => $this->cards($rows), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /**
     * Spec 23: one page of the ACCIDENT vehicles (own page; only while the administrator has switched it on).
     * Null when the page is off. Same card shape, plus the damage summary of each vehicle.
     *
     * @param array<string,mixed> $filters validated by PublicVehicleSearchValidator
     * @return array{cards:list<array<string,mixed>>,total:int,page:int,pages:int}|null
     */
    public function accidentSearch(array $filters, bool $enabled, int $perPage = self::PER_PAGE): ?array
    {
        if (!$enabled) {
            return null;
        }
        $perPage = max(1, min($perPage, 48));
        $filters['section'] = VehicleRepository::SECTION_ACCIDENT;
        $total = $this->vehicles->publicCount($filters);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min((int) ($filters['page'] ?? 1), $pages));
        $rows = $total === 0 ? [] : $this->vehicles->publicSearch($filters, $perPage, ($page - 1) * $perPage);

        return ['cards' => $this->cards($rows), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /**
     * The newest public vehicles for the homepage (spec 10B): the first page of the unfiltered inventory, newest first.
     * Same visibility rule, same two queries and same card shape as the Vehicles page.
     *
     * @return list<array<string,mixed>>
     */
    public function latest(int $limit = 4): array
    {
        return $this->search([], max(1, min($limit, 12)))['cards'];
    }

    /**
     * One public vehicle for its own page (spec 14): the card fields plus the full gallery (large photo and thumbnail
     * of every image, primary first, in the administrator's order), description, features and shipping information.
     * Null when no PUBLIC vehicle has this slug (sold, unavailable, unpublished, archived or unknown).
     * 'id' is for the enquiry service only; the view never prints it. 'fit' (spec 28) is the make / model / year used to
     * find compatible parts.
     *
     * @return array{id:int,fit:array{brand_id:int,brand_slug:string,model:string,year:int},vehicle:array<string,mixed>}|null
     */
    /**
     * "2015 Toyota Corolla LE" for a PUBLIC vehicle's slug (spec 28: the contact message when a part is requested from a
     * vehicle page), or null when no public vehicle has it. The slug is untrusted and checked first.
     */
    public function titleBySlug(mixed $slug, ?bool $accidentEnabled = null): ?string
    {
        if (!is_string($slug) || strlen($slug) > 160 || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            return null;
        }
        $row = $this->vehicles->publicBySlug($slug, $accidentEnabled ?? AccidentVehicleSettings::enabled());

        return $row === null ? null : trim($row['year'] . ' ' . $row['brand_name'] . ' ' . trim($row['model'] . ' ' . (string) $this->clean($row['trim'])));
    }

    public function detail(string $slug, ?bool $accidentEnabled = null): ?array
    {
        $accidentEnabled ??= AccidentVehicleSettings::enabled();
        if (strlen($slug) > 160 || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            return null;
        }
        $row = $this->vehicles->publicBySlug($slug, $accidentEnabled);
        $accident = (int) ($row['is_accident'] ?? 0) === 1;
        if ($row === null) {
            return null;
        }
        $id = (int) $row['id'];
        $name = trim($row['model'] . ' ' . (string) $this->clean($row['trim']));
        $title = trim($row['year'] . ' ' . $row['brand_name'] . ' ' . $name);

        $photos = [];
        foreach ($this->images->galleryFor([$id], 40)[$id] ?? [] as $photo) {
            $large = $this->storedImage($photo['path'], '');
            $thumb = $this->storedImage($photo['path'], '-t');
            if ($large !== null) {
                $alt = trim((string) ($photo['alt'] ?? ''));
                $photos[] = ['large' => $large, 'thumb' => $thumb ?? $large, 'alt' => $alt !== '' ? $alt : $title, 'type' => self::photoType((string) ($photo['type'] ?? 'exterior'), $accident)];
            }
        }

        $features = [];
        $decoded = json_decode((string) ($row['key_features'] ?? ''), true);
        foreach (is_array($decoded) ? $decoded : [] as $feature) {
            if (is_string($feature) && trim($feature) !== '') {
                $features[] = trim($feature);
            }
        }

        $specs = $this->specs($row);
        $byKey = array_column($specs, 'value', 'key');
        // The four tiles beside the gallery: only what exists (the year always does). Nothing is invented or shown as N/A.
        $tiles = [];
        foreach (['engine' => ['Engine', 'engine'], 'transmission' => ['Transmission', 'gearbox'], 'mileage' => ['Mileage', 'gauge']] as $key => [$label, $icon]) {
            if (isset($byKey[$key])) {
                $tiles[] = ['label' => $label, 'value' => $byKey[$key], 'icon' => $icon];
            }
        }
        $tiles[] = ['label' => 'Year', 'value' => (string) $row['year'], 'icon' => 'calendar'];
        // The Specifications section: grouped rows of real values only; an empty group is left out.
        $groups = [];
        $drivetrain = array_values(array_filter([
            isset($byKey['engine']) ? ['Engine', $byKey['engine']] : null,
            isset($byKey['transmission']) ? ['Transmission', $byKey['transmission']] : null,
            isset($byKey['drive']) ? ['Drive', $byKey['drive']] : null,
            isset($byKey['fuel']) ? ['Fuel', $byKey['fuel']] : null,
        ]));
        if ($drivetrain !== []) {
            $groups[] = ['title' => 'Engine & drivetrain', 'icon' => 'engine', 'rows' => $drivetrain];
        }
        $groups[] = ['title' => 'Vehicle details', 'icon' => 'car', 'rows' => array_values(array_filter([
            ['Make', (string) $row['brand_name']],
            ['Model', (string) $row['model']],
            $this->clean($row['trim']) !== null ? ['Trim', (string) $this->clean($row['trim'])] : null,
            ['Year', (string) $row['year']],
            isset($byKey['mileage']) ? ['Mileage', $byKey['mileage']] : null,
            ['Availability', $row['availability_status'] === 'reserved' ? 'Reserved' : 'Available'],
        ]))];

        return ['id' => $id, 'fit' => ['brand_id' => (int) $row['brand_id'], 'brand_slug' => (string) $row['brand_slug'], 'model' => (string) $row['model'], 'year' => (int) $row['year']],
            // SEO 2026-10-27: the raw values behind the visible price and specifications, for structured data only (never printed)
            'schema' => ['price' => (string) $row['price'], 'currency' => (string) $row['currency'], 'mileage' => $row['mileage'] === null ? null : (int) $row['mileage'],
                'mileage_unit' => $row['mileage_unit'] === null ? null : (string) $row['mileage_unit'], 'fuel' => $this->clean($row['fuel_type']),
                'transmission' => $this->clean($row['transmission']), 'model' => (string) $row['model']],
            'vehicle' => [
            'title'       => $title,
            'headline'    => trim($row['brand_name'] . ' ' . $row['model']),
            'trim'        => $this->clean($row['trim']),
            'eyebrow'     => $row['year'] . ' · ' . ($byKey['fuel'] ?? $row['brand_name']),
            'tiles'       => $tiles,
            'groups'      => $groups,
            'year'        => (string) $row['year'],
            'brand'       => (string) $row['brand_name'],
            'name'        => $name,
            'slug'        => (string) $row['slug'],
            'url'         => '/vehicle/' . $row['slug'] . '/',
            'price'       => $this->price((string) $row['price'], (string) $row['currency']),
            'duty'        => (int) $row['duty_included'] === 1,
            'status'      => $row['availability_status'] === 'reserved' ? 'Reserved' : 'Available',
            'reserved'    => $row['availability_status'] === 'reserved',
            'specs'       => $specs,
            'photos'      => $photos,
            'description' => $this->clean($row['description']),
            'features'    => $features,
            'shipping'    => $this->clean($row['shipping_info']),
            'accident'    => $accident ? $this->damage($row) : null,   // spec 23: full disclosure
        ]];
    }

    /**
     * Everything the search form offers, from real data: active brands (existing Brand repository),
     * the models of vehicles really on offer (grouped by brand), their years, and "up to" prices built
     * from the highest real GHS price.
     *
     * @return array{brands:list<array{slug:string,name:string}>,models:array<string,array{name:string,models:list<string>}>,years:list<int>,prices:list<int>}
     */
    public function searchOptions(string $section = VehicleRepository::SECTION_NORMAL): array
    {
        $brands = [];
        foreach ($this->brands?->search('', 'active') ?? [] as $brand) {
            $brands[] = ['slug' => (string) $brand['slug'], 'name' => (string) $brand['name']];
        }
        $values = $this->vehicles->publicFilterValues($section);
        $models = [];
        foreach ($values['models'] as $row) {
            $slug = (string) $row['brand_slug'];
            $models[$slug]['name'] = (string) $row['brand_name'];
            $models[$slug]['models'][] = (string) $row['model'];
        }

        return ['brands' => $brands, 'models' => $models, 'years' => $values['years'], 'prices' => $this->priceSteps($values['max_ghs_price'])];
    }

    /** "Up to" amounts: the round steps below the highest real GHS price, plus the first step that covers it. */
    private function priceSteps(?string $max): array
    {
        if ($max === null || (float) $max <= 0) {
            return [];
        }
        $steps = [];
        foreach (self::PRICE_STEPS as $step) {
            $steps[] = $step;
            if ($step >= (float) $max) {
                break;
            }
        }

        return $steps;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{title:string,year:string,brand:string,name:string,spec:?string,price:string,status:string,reserved:bool,url:string,
     *                    image:?array{url:string,width:int,height:int},alt:string}>
     */
    private function cards(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $galleries = $this->images->galleryFor(array_map(static fn (array $r): int => (int) $r['id'], $rows), self::PHOTOS);

        $cards = [];
        foreach ($rows as $row) {
            $slug = (string) $row['slug'];
            if (strlen($slug) > 160 || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
                continue;
            }
            $name = trim($row['model'] . ' ' . (string) $this->clean($row['trim']));
            $title = trim($row['year'] . ' ' . $row['brand_name'] . ' ' . $name);
            // The first photograph must be the primary one (otherwise the card shows the placeholder); the rest are shown in order.
            $photos = $galleries[(int) $row['id']] ?? [];
            $primary = ($photos !== [] && $photos[0]['primary']) ? $photos[0] : null;
            $image = $primary !== null ? $this->thumbnail($primary['path']) : null;
            $alt = trim((string) ($primary['alt'] ?? ''));
            $gallery = [];
            if ($image !== null) {
                foreach (array_slice($photos, 1) as $photo) {
                    $thumb = $this->thumbnail($photo['path']);
                    if ($thumb !== null) {
                        $gallery[] = $thumb;
                    }
                }
            }

            $cards[] = [
                'title'    => $title,                       // full name, for screen readers and alt text
                'year'     => (string) $row['year'],
                'brand'    => (string) $row['brand_name'],
                'name'     => $name,                        // model and trim ("RAV4 XLE")
                'spec'     => $this->specLine($row),
                'specs'    => $this->specs($row),
                'gallery'  => $gallery,                     // the other photographs (thumbnails), after the first
                'price'    => $this->price((string) $row['price'], (string) $row['currency']),
                'duty'     => (int) $row['duty_included'] === 1,   // only when the stored field says so
                'status'   => $row['availability_status'] === 'reserved' ? 'Reserved' : 'Available',
                'reserved' => $row['availability_status'] === 'reserved',
                'url'      => '/vehicle/' . $slug . '/',
                'slug'     => $slug,
                'image'    => $image,
                'alt'      => $alt !== '' ? $alt : $title,
                'accident' => (int) ($row['is_accident'] ?? 0) === 1,  // spec 23: labelled "Accident-damaged"
            ];
        }

        return $cards;
    }

    /** Gallery tab of a photo: damage photos form their own tab on an accident vehicle; elsewhere they count as exterior. */
    private static function photoType(string $type, bool $accident): string
    {
        return match (true) {
            $type === 'interior'            => 'interior',
            $type === 'damage' && $accident => 'damage',
            default                         => 'exterior',
        };
    }

    /**
     * Spec 23: the damage disclosure of an accident vehicle, as the visitor reads it (labels, never raw keys).
     *
     * @param array<string,mixed> $row
     * @return array{description:string,areas:list<string>,facts:list<array{0:string,1:string}>}
     */
    private function damage(array $row): array
    {
        $areas = [];
        foreach (array_filter(explode(',', (string) ($row['damage_areas'] ?? ''))) as $key) {
            if (isset(VehicleValidator::DAMAGE_AREAS[$key])) {
                $areas[] = VehicleValidator::DAMAGE_AREAS[$key];
            }
        }
        $facts = [];
        foreach ([['Runs and drives', VehicleValidator::RUNS_DRIVES, 'runs_drives'], ['Airbags deployed', VehicleValidator::AIRBAGS, 'airbags_deployed'],
            ['Title status', VehicleValidator::TITLE_STATUSES, 'title_status']] as [$label, $choices, $key]) {
            if (isset($choices[(string) ($row[$key] ?? '')])) {
                $facts[] = [$label, $choices[(string) $row[$key]]];
            }
        }

        return ['description' => (string) $this->clean($row['damage_description'] ?? null), 'areas' => $areas, 'facts' => $facts];
    }

    private function clean(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * The labelled details that exist (mileage, engine, transmission, drive, fuel): nothing invented, nothing converted.
     * @return list<array{key:string,label:string,value:string}> */
    private function specs(array $row): array
    {
        $specs = [];
        $mileage = $row['mileage'] ?? null;
        if ($mileage !== null && $mileage !== '' && in_array($row['mileage_unit'] ?? null, ['km', 'mi'], true)) {
            $specs[] = ['key' => 'mileage', 'label' => 'Mileage', 'value' => number_format((int) $mileage) . ' ' . $row['mileage_unit']];
        }
        foreach (['engine' => ['Engine', 'engine_size'], 'transmission' => ['Transmission', 'transmission'], 'drive' => ['Drive', 'drive_type'], 'fuel' => ['Fuel', 'fuel_type']] as $key => [$label, $column]) {
            $value = $this->clean($row[$column] ?? null);
            if ($value !== null) {
                $specs[] = ['key' => $key, 'label' => $label, 'value' => $value];
            }
        }

        return $specs;
    }

    /** "2.5L · Automatic · AWD": the first three details that exist (engine, transmission, drive, fuel), nothing invented. */
    private function specLine(array $row): ?string
    {
        $parts = array_values(array_filter([
            $this->clean($row['engine_size']), $this->clean($row['transmission']), $this->clean($row['drive_type']), $this->clean($row['fuel_type']),
        ]));

        return $parts === [] ? null : implode(' · ', array_slice($parts, 0, 3));
    }

    /** The stored price and currency as saved ("GHS 12,388", cents only when there are any): no conversion, no calculation. */
    private function price(string $price, string $currency): string
    {
        $whole = preg_match('/^\d+(\.0+)?$/', $price) === 1;

        return $currency . ' ' . number_format((float) $price, $whole ? 0 : 2, '.', ',');
    }

    /**
     * The stored thumbnail of the primary image, if its path has the exact shape the image service
     * writes and the file is a real WebP; null otherwise (the card then shows the placeholder).
     *
     * @return array{url:string,width:int,height:int}|null
     */
    private function thumbnail(string $path): ?array
    {
        return $this->storedImage($path, '-t');
    }

    /**
     * A stored vehicle image: the large file ($suffix '') or its thumbnail ('-t'), under the same checks.
     *
     * @return array{url:string,width:int,height:int}|null
     */
    private function storedImage(string $path, string $suffix): ?array
    {
        if (!in_array($suffix, ['', '-t'], true) || preg_match('#^uploads/vehicles/([a-f0-9]{32})\.webp$#', $path, $m) !== 1) {
            return null;
        }
        $relative = 'uploads/vehicles/' . $m[1] . $suffix . '.webp';
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
