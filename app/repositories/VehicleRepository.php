<?php

declare(strict_types=1);

/**
 * All SQL for Vehicle Management (spec 06_spec_vehicle_managt) lives here.
 * Every query is a PDO prepared statement; values are always bound, including
 * LIMIT/OFFSET. Archived rows (deleted_at set) are never shown or changed by this module.
 *
 * Lists load vehicle and brand in one JOIN (no per-row brand query).
 */
class VehicleRepository
{
    private const LIST_COLUMNS = 'v.id, v.brand_id, b.name AS brand_name, v.year, v.model, v.trim, v.price, v.currency,
        v.availability_status, v.is_published, v.slug, v.created_at';

    private const DETAIL_COLUMNS = 'v.id, v.brand_id, b.name AS brand_name, b.status AS brand_status, v.year, v.model, v.trim,
        v.engine_size, v.mileage, v.mileage_unit, v.transmission, v.fuel_type, v.drive_type, v.price, v.currency,
        v.description, v.key_features, v.shipping_info, v.duty_included, v.availability_status, v.is_published,
        v.slug, v.created_at, v.updated_at,
        (SELECT COUNT(*) FROM shipments s WHERE s.vehicle_id = v.id) AS shipment_count,
        (SELECT COUNT(*) FROM enquiries e WHERE e.vehicle_id = v.id) AS enquiry_count,
        (SELECT COUNT(*) FROM vehicle_images i WHERE i.vehicle_id = v.id) AS image_count';

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * What the public website may show: published, not archived, and available or reserved (sold and
     * unavailable vehicles are not offered). One rule for the whole public site (spec 10B/11).
     */
    private const PUBLIC_WHERE = "v.is_published = 1 AND v.deleted_at IS NULL AND v.availability_status IN ('available', 'reserved')";

    /** The only ORDER BY clauses the public inventory can use: the URL picks a KEY, never SQL. */
    private const PUBLIC_SORTS = [
        'newest'     => 'v.created_at DESC, v.id DESC',
        'price_asc'  => 'v.price ASC, v.id DESC',
        'price_desc' => 'v.price DESC, v.id DESC',
    ];

    private const PUBLIC_COLUMNS = 'v.id, b.name AS brand_name, v.year, v.model, v.trim, v.engine_size, v.transmission, v.drive_type, v.fuel_type, v.mileage, v.mileage_unit,
        v.price, v.currency, v.duty_included, v.availability_status, v.slug';

    /**
     * Public inventory search: the newest first, one page. Filters (all optional, already validated by
     * PublicVehicleSearchValidator): brand (slug), model (exact, case-insensitive), year, max_price (GHS only:
     * the price filter is a Ghana Cedi filter, so vehicles stored in another currency never match it).
     * Prepared statement, every value bound, only the columns the public card needs.
     *
     * @param array{brand:?string,model:?string,year:?int,max_price:?int} $filters
     * @return list<array<string,mixed>>
     */
    public function publicSearch(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->publicConditions($filters);
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::PUBLIC_COLUMNS . ' FROM vehicles v JOIN brands b ON b.id = v.brand_id WHERE ' . $where
            . ' ORDER BY ' . self::PUBLIC_SORTS[$filters['sort'] ?? 'newest'] . ' LIMIT :limit OFFSET :offset'
        );
        $this->bind($stmt, $params);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * One public vehicle by its slug (spec 14 detail page), under the same public rule as the listings. Only the
     * customer-facing columns; the id is returned for the image and enquiry lookups and never leaves the service layer.
     *
     * @return array<string,mixed>|null
     */
    public function publicBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::PUBLIC_COLUMNS . ', v.description, v.key_features, v.shipping_info
             FROM vehicles v JOIN brands b ON b.id = v.brand_id WHERE ' . self::PUBLIC_WHERE . ' AND v.slug = :slug LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @param array{brand:?string,model:?string,year:?int,max_price:?int} $filters */
    public function publicCount(array $filters): int
    {
        [$where, $params] = $this->publicConditions($filters);
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM vehicles v JOIN brands b ON b.id = v.brand_id WHERE ' . $where);
        $this->bind($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * The values the public search form can offer, taken from the vehicles that are really on offer.
     * Three small DISTINCT/MAX queries; nothing is hardcoded.
     *
     * @return array{models:list<array{brand_slug:string,brand_name:string,model:string}>,years:list<int>,max_ghs_price:?string}
     */
    public function publicFilterValues(): array
    {
        $base = ' FROM vehicles v JOIN brands b ON b.id = v.brand_id WHERE ' . self::PUBLIC_WHERE;
        $models = $this->pdo->query('SELECT DISTINCT b.slug AS brand_slug, b.name AS brand_name, v.model' . $base . ' ORDER BY b.name, v.model')->fetchAll();
        $years = $this->pdo->query('SELECT DISTINCT v.year' . $base . ' ORDER BY v.year DESC')->fetchAll(PDO::FETCH_COLUMN);
        $max = $this->pdo->query("SELECT MAX(v.price)" . $base . " AND v.currency = 'GHS'")->fetchColumn();

        return ['models' => $models, 'years' => array_map('intval', $years), 'max_ghs_price' => $max === false || $max === null ? null : (string) $max];
    }

    /** @param array{brand:?string,model:?string,year:?int,max_price:?int} $filters @return array{0:string,1:array<string,mixed>} */
    private function publicConditions(array $filters): array
    {
        $where = [self::PUBLIC_WHERE];
        $params = [];
        if (($filters['brand'] ?? null) !== null) {
            $where[] = 'b.slug = :brand';
            $params['brand'] = [$filters['brand'], PDO::PARAM_STR];
        }
        if (($filters['model'] ?? null) !== null) {
            $where[] = 'v.model = :model';           // the column collation is case-insensitive
            $params['model'] = [$filters['model'], PDO::PARAM_STR];
        }
        if (($filters['q'] ?? null) !== null) {
            // Every word must appear in "year make model trim" (so "toyota rav4" works); wildcards typed by the visitor are escaped.
            foreach (array_slice(explode(' ', (string) $filters['q']), 0, 5) as $i => $word) {
                $where[] = "CONCAT_WS(' ', v.year, b.name, v.model, v.trim) LIKE :q$i ESCAPE '|'";
                $params["q$i"] = ['%' . str_replace(['|', '%', '_'], ['||', '|%', '|_'], $word) . '%', PDO::PARAM_STR];
            }
        }
        if (($filters['availability'] ?? null) !== null) {
            $where[] = 'v.availability_status = :availability';
            $params['availability'] = [$filters['availability'], PDO::PARAM_STR];
        }
        if (($filters['min_price'] ?? null) !== null) {
            $where[] = "v.currency = 'GHS' AND v.price >= :min_price";
            $params['min_price'] = [$filters['min_price'], PDO::PARAM_INT];
        }
        if (($filters['year'] ?? null) !== null) {
            $where[] = 'v.year = :year';
            $params['year'] = [$filters['year'], PDO::PARAM_INT];
        }
        if (($filters['max_price'] ?? null) !== null) {
            $where[] = "v.currency = 'GHS' AND v.price <= :max_price";
            $params['max_price'] = [$filters['max_price'], PDO::PARAM_INT];
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * One page of vehicles, newest first (created_at DESC, id DESC: deterministic).
     *
     * @param array{q:string,brand:?int,status:string,year:?int} $filters
     * @return list<array<string,mixed>>
     */
    public function search(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->conditions($filters);
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::LIST_COLUMNS . ' FROM vehicles v JOIN brands b ON b.id = v.brand_id WHERE ' . $where
            . ' ORDER BY v.created_at DESC, v.id DESC LIMIT :limit OFFSET :offset'
        );
        $this->bind($stmt, $params);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @param array{q:string,brand:?int,status:string,year:?int} $filters */
    public function countMatching(array $filters): int
    {
        [$where, $params] = $this->conditions($filters);
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM vehicles v JOIN brands b ON b.id = v.brand_id WHERE ' . $where);
        $this->bind($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM vehicles WHERE deleted_at IS NULL')->fetchColumn();
    }

    /** @return list<int> distinct model years in stock, newest first (for the year filter) */
    public function years(): array
    {
        return array_map('intval', $this->pdo->query('SELECT DISTINCT year FROM vehicles WHERE deleted_at IS NULL ORDER BY year DESC')->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return list<array{id:int|string,name:string,status:string}> every brand (the list filter must reach inactive brands too) */
    public function allBrands(): array
    {
        return $this->pdo->query('SELECT id, name, status FROM brands ORDER BY display_order ASC, name ASC, id ASC')->fetchAll();
    }

    /** @return array{id:int|string,name:string,status:string}|null */
    public function findBrand(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, status FROM brands WHERE id = :id');
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public function find(int $id, bool $forUpdate = false): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::DETAIL_COLUMNS . ' FROM vehicles v JOIN brands b ON b.id = v.brand_id
             WHERE v.id = :id AND v.deleted_at IS NULL' . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function slugTaken(string $slug, ?int $exceptId = null): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM vehicles WHERE slug = :slug' . ($exceptId !== null ? ' AND id <> :except' : '') . ' LIMIT 1');
        $stmt->bindValue('slug', $slug);
        if ($exceptId !== null) {
            $stmt->bindValue('except', $exceptId, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }

    /** @param array<string,mixed> $values */
    public function insert(array $values): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO vehicles (brand_id, year, model, trim, engine_size, mileage, mileage_unit, transmission, fuel_type, drive_type,
                price, currency, description, key_features, shipping_info, duty_included, availability_status, is_published, slug)
             VALUES (:brand_id, :year, :model, :trim, :engine_size, :mileage, :mileage_unit, :transmission, :fuel_type, :drive_type,
                :price, :currency, :description, :key_features, :shipping_info, :duty_included, :availability_status, :is_published, :slug)'
        );
        $this->bindValues($stmt, $values);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $values */
    public function update(int $id, array $values): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE vehicles SET brand_id = :brand_id, year = :year, model = :model, trim = :trim, engine_size = :engine_size,
                mileage = :mileage, mileage_unit = :mileage_unit, transmission = :transmission, fuel_type = :fuel_type,
                drive_type = :drive_type, price = :price, currency = :currency, description = :description,
                key_features = :key_features, shipping_info = :shipping_info, duty_included = :duty_included,
                availability_status = :availability_status, is_published = :is_published, slug = :slug
             WHERE id = :id AND deleted_at IS NULL'
        );
        $this->bindValues($stmt, $values);
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function setStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE vehicles SET availability_status = :status WHERE id = :id AND deleted_at IS NULL');
        $stmt->bindValue('status', $status);
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM vehicles WHERE id = :id AND deleted_at IS NULL');
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() === 1;
    }

    /**
     * Turns the stored JSON feature array into a list of strings (anything unexpected = empty list).
     *
     * @return list<string>
     */
    public static function decodeFeatures(mixed $json): array
    {
        if (!is_string($json) || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    /* ---------------------------------------------------------------- */

    /**
     * WHERE clause and its bound values. Each search word must match the brand
     * name, model, trim, slug or year (so "toyota camry 2024" narrows down).
     * LIKE wildcards typed by the user are escaped and matched literally.
     *
     * @param array{q:string,brand:?int,status:string,year:?int} $filters
     * @return array{0:string,1:array<string,array{0:mixed,1:int}>}
     */
    private function conditions(array $filters): array
    {
        $where = ['v.deleted_at IS NULL'];
        $params = [];

        $terms = preg_split('/\s+/u', $filters['q'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach (array_slice($terms, 0, 6) as $i => $term) {
            $name = 'term' . $i;
            $where[] = "(b.name LIKE :{$name}a ESCAPE '\\\\' OR v.model LIKE :{$name}b ESCAPE '\\\\' OR v.trim LIKE :{$name}c ESCAPE '\\\\'"
                . " OR v.slug LIKE :{$name}d ESCAPE '\\\\' OR CAST(v.year AS CHAR) LIKE :{$name}e ESCAPE '\\\\')";
            $like = '%' . addcslashes($term, '\\%_') . '%';
            foreach (['a', 'b', 'c', 'd', 'e'] as $suffix) {
                $params[$name . $suffix] = [$like, PDO::PARAM_STR];
            }
        }
        if ($filters['brand'] !== null) {
            $where[] = 'v.brand_id = :brand';
            $params['brand'] = [$filters['brand'], PDO::PARAM_INT];
        }
        if (in_array($filters['status'], ['available', 'reserved', 'sold', 'unavailable'], true)) {
            $where[] = 'v.availability_status = :status';
            $params['status'] = [$filters['status'], PDO::PARAM_STR];
        }
        if ($filters['year'] !== null) {
            $where[] = 'v.year = :year';
            $params['year'] = [$filters['year'], PDO::PARAM_INT];
        }

        return [implode(' AND ', $where), $params];
    }

    /** @param array<string,array{0:mixed,1:int}> $params */
    private function bind(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $name => [$value, $type]) {
            $stmt->bindValue($name, $value, $type);
        }
    }

    /** @param array<string,mixed> $v */
    private function bindValues(PDOStatement $stmt, array $v): void
    {
        $nullable = static fn (mixed $x): int => $x === null ? PDO::PARAM_NULL : PDO::PARAM_STR;
        $stmt->bindValue('brand_id', (int) $v['brand_id'], PDO::PARAM_INT);
        $stmt->bindValue('year', (int) $v['year'], PDO::PARAM_INT);
        $stmt->bindValue('model', $v['model']);
        $stmt->bindValue('trim', $v['trim'], $nullable($v['trim']));
        $stmt->bindValue('engine_size', $v['engine_size'], $nullable($v['engine_size']));
        $stmt->bindValue('mileage', $v['mileage'], $v['mileage'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue('mileage_unit', $v['mileage_unit'], $nullable($v['mileage_unit']));
        $stmt->bindValue('transmission', $v['transmission'], $nullable($v['transmission']));
        $stmt->bindValue('fuel_type', $v['fuel_type'], $nullable($v['fuel_type']));
        $stmt->bindValue('drive_type', $v['drive_type'], $nullable($v['drive_type']));
        $stmt->bindValue('price', $v['price']);   // exact decimal text, e.g. "12500.00"
        $stmt->bindValue('currency', $v['currency']);
        $stmt->bindValue('description', $v['description'], $nullable($v['description']));
        $features = $v['key_features'] === [] ? null : json_encode(array_values($v['key_features']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $stmt->bindValue('key_features', $features, $nullable($features));
        $stmt->bindValue('shipping_info', $v['shipping_info'], $nullable($v['shipping_info']));
        $stmt->bindValue('duty_included', (int) $v['duty_included'], PDO::PARAM_INT);
        $stmt->bindValue('availability_status', $v['availability_status']);
        $stmt->bindValue('is_published', (int) $v['is_published'], PDO::PARAM_INT);
        $stmt->bindValue('slug', $v['slug']);
    }
}
