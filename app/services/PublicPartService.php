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
 */
final class PublicPartService
{
    public const SHOWN = 8;
    public const CARD_POOL = 24;

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
        $types = $this->parts->publicCategories();
        $makes = $this->parts->publicMakes();
        // A filter counts only when it names a type / make that really has public parts; anything else is ignored.
        $type = self::pick($type, $types);
        $make = $type === null ? self::pick($make, $makes) : null;   // one filter at a time, like the two tabs

        $rows = $this->parts->latestPublic($type === null && $make === null ? self::CARD_POOL : self::SHOWN, $type, $make);
        $fitments = $this->parts->fitmentsFor(array_map(static fn (array $r): int => (int) $r['id'], $rows));
        $cards = [];
        foreach ($rows as $row) {
            $cards[] = $this->card($row, $fitments[(int) $row['id']] ?? []);
        }

        return ['cards' => $cards, 'types' => $types, 'makes' => $makes, 'type' => $type, 'make' => $make];
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
            'condition'   => PartValidator::CONDITIONS[$row['condition']] ?? '',
            'price'       => self::price((string) $row['price'], (string) $row['currency']),
            'in_stock'    => $row['stock_status'] === 'in_stock',
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
