<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/repositories/VehicleRepository.php';
require_once __DIR__ . '/AccidentVehicleSettings.php';

/**
 * The signed-in customer's own data for the account dashboard (spec 22): favourite vehicles and reservation requests.
 * Every method takes the customer id from the caller, which gets it from the server-side session only; nothing here
 * accepts an id from the browser. Prepared statements only.
 *
 * A "public" vehicle is what the website shows: published, not archived, Available or Reserved (VehicleRepository::publicWhere).
 * Spec 23: an accident vehicle counts as public only while the Accident Vehicles page is switched on.
 */
class CustomerDashboardService
{
    public const MAX_FAVOURITES = 100;

    /** Reservation request status, as the customer reads it (enquiries.status). */
    public const REQUEST_STATUS = ['new' => 'Received', 'in_progress' => 'In progress', 'closed' => 'Closed'];

    private string $publicVehicle;

    public function __construct(private PDO $pdo, private ?string $root = null, ?bool $accidentEnabled = null)
    {
        $this->root ??= dirname(__DIR__, 2);
        $normal = VehicleRepository::publicWhere(VehicleRepository::SECTION_NORMAL);
        $this->publicVehicle = ($accidentEnabled ?? AccidentVehicleSettings::enabled())
            ? '((' . $normal . ') OR (' . VehicleRepository::publicWhere(VehicleRepository::SECTION_ACCIDENT) . '))'
            : '(' . $normal . ')';
    }

    public static function create(): self
    {
        return new self(Database::connection());
    }

    /** Saves (or removes) a favourite by vehicle slug. Only a public vehicle can be added. 'added' | 'removed' | 'not_found' | 'full'. */
    public function setFavourite(int $customerId, string $slug, bool $add): string
    {
        if (strlen($slug) > 160 || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) !== 1) {
            return 'not_found';
        }
        if (!$add) {
            $stmt = $this->pdo->prepare('DELETE f FROM customer_favourites f JOIN vehicles v ON v.id = f.vehicle_id WHERE f.customer_id = ? AND v.slug = ?');
            $stmt->execute([$customerId, $slug]);

            return 'removed';
        }
        $stmt = $this->pdo->prepare('SELECT v.id FROM vehicles v WHERE v.slug = ? AND ' . $this->publicVehicle);
        $stmt->execute([$slug]);
        $vehicleId = $stmt->fetchColumn();
        if ($vehicleId === false) {
            return 'not_found';
        }
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM customer_favourites WHERE customer_id = ?');
        $count->execute([$customerId]);
        if ((int) $count->fetchColumn() >= self::MAX_FAVOURITES) {
            return 'full';
        }
        $this->pdo->prepare('INSERT IGNORE INTO customer_favourites (customer_id, vehicle_id) VALUES (?, ?)')->execute([$customerId, (int) $vehicleId]);

        return 'added';
    }

    /** @return list<string> slugs of the customer's favourite vehicles that are public (to mark the hearts on cards) */
    public function favouriteSlugs(int $customerId): array
    {
        $stmt = $this->pdo->prepare('SELECT v.slug FROM customer_favourites f JOIN vehicles v ON v.id = f.vehicle_id WHERE f.customer_id = ? AND ' . $this->publicVehicle);
        $stmt->execute([$customerId]);

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * The customer's favourites, newest first. A vehicle that is no longer shown on the website (sold, unpublished,
     * archived) stays in the list as "no longer available", without a link or price.
     *
     * @return list<array{title:string,slug:string,url:?string,price:?string,status:string,available:bool,image:?array{url:string,width:int,height:int}}>
     */
    public function favourites(int $customerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT v.slug, v.year, v.model, v.trim, v.price, v.currency, v.availability_status, (' . $this->publicVehicle . ') AS is_public, b.name AS brand,
                    (SELECT i.file_path FROM vehicle_images i WHERE i.vehicle_id = v.id AND i.is_primary = 1 LIMIT 1) AS image_path
             FROM customer_favourites f JOIN vehicles v ON v.id = f.vehicle_id JOIN brands b ON b.id = v.brand_id
             WHERE f.customer_id = ? ORDER BY f.created_at DESC, v.id DESC'
        );
        $stmt->execute([$customerId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $public = (int) $r['is_public'] === 1;
            $out[] = [
                'title'     => trim($r['year'] . ' ' . $r['brand'] . ' ' . $r['model'] . ' ' . (string) $r['trim']),
                'slug'      => (string) $r['slug'],
                'url'       => $public ? '/vehicle/' . $r['slug'] . '/' : null,
                'price'     => $public ? self::price((string) $r['price'], (string) $r['currency']) : null,
                'status'    => $public ? ($r['availability_status'] === 'reserved' ? 'Reserved' : 'Available') : 'No longer available',
                'available' => $public,
                'image'     => $public && $r['image_path'] !== null ? $this->thumbnail((string) $r['image_path']) : null,
            ];
        }

        return $out;
    }

    /**
     * Reservation requests the customer sent while signed in (enquiry type "purchase"), newest first.
     *
     * @return list<array{title:string,url:?string,sent:string,status:string}>
     */
    public function reservationRequests(int $customerId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.status, e.created_at, v.slug, v.year, v.model, v.trim, b.name AS brand, (" . $this->publicVehicle . ") AS is_public
             FROM enquiries e JOIN vehicles v ON v.id = e.vehicle_id JOIN brands b ON b.id = v.brand_id
             WHERE e.customer_id = ? AND e.enquiry_type = 'purchase' ORDER BY e.created_at DESC, e.id DESC LIMIT 50"
        );
        $stmt->execute([$customerId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $sent = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $r['created_at'], new DateTimeZone('UTC'));
            $out[] = [
                'title'  => trim($r['year'] . ' ' . $r['brand'] . ' ' . $r['model'] . ' ' . (string) $r['trim']),
                'url'    => (int) $r['is_public'] === 1 ? '/vehicle/' . $r['slug'] . '/' : null,
                'sent'   => $sent === false ? '' : $sent->format('j M Y'),
                'status' => self::REQUEST_STATUS[$r['status']] ?? '',
            ];
        }

        return $out;
    }

    private static function price(string $price, string $currency): string
    {
        $whole = preg_match('/^\d+(\.0+)?$/', $price) === 1;

        return $currency . ' ' . number_format((float) $price, $whole ? 0 : 2, '.', ',');
    }

    /** The stored thumbnail, only for the exact path shape the image service writes and a real WebP file. */
    private function thumbnail(string $path): ?array
    {
        if (preg_match('#^uploads/vehicles/([a-f0-9]{32})\.webp$#D', $path, $m) !== 1) {
            return null;
        }
        $relative = 'uploads/vehicles/' . $m[1] . '-t.webp';
        $info = is_file($this->root . '/' . $relative) ? @getimagesize($this->root . '/' . $relative) : false;

        return $info === false || $info[2] !== IMAGETYPE_WEBP ? null : ['url' => '/' . $relative, 'width' => $info[0], 'height' => $info[1]];
    }
}
