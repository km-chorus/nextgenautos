<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/validation/PartValidator.php';

/**
 * Data access for the parts catalogue (spec 16, phase 1): part_categories, parts, part_fitments, part_images, and the
 * existing brands (the makes a part fits). Prepared statements only; LIKE wildcards in searches are escaped.
 * Archived parts (deleted_at set) are invisible to every method except the ones that say otherwise.
 *
 * Spec 27: part numbers live in part_numbers (several per part; the first by sort_order is the main one, returned as
 * `part_number` by the list queries). Price, condition and stock may be NULL (not set).
 */
final class PartRepository
{
    /** The main part number of p (alias for the list queries). */
    private const MAIN_NUMBER = '(SELECT n.number FROM part_numbers n WHERE n.part_id = p.id ORDER BY n.sort_order, n.id LIMIT 1)';
    /** A part number with spaces, hyphens, dots, slashes and underscores removed, upper case (PartValidator::numberKey). */
    private const NUMBER_KEY = "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(n.number, ' ', ''), '-', ''), '.', ''), '/', ''), '_', ''))";
    public function __construct(private PDO $pdo)
    {
    }

    // ---- categories -------------------------------------------------------------------------------------------------

    /** @return list<array{id:int,name:string,slug:string,display_order:int,status:string,parts:int}> with the number of non-archived parts */
    public function categories(): array
    {
        $rows = $this->pdo->query(
            'SELECT c.id, c.name, c.slug, c.display_order, c.status,
                    (SELECT COUNT(*) FROM parts p WHERE p.category_id = c.id AND p.deleted_at IS NULL) AS parts
             FROM part_categories c ORDER BY c.display_order, c.name'
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'name' => (string) $r['name'], 'slug' => (string) $r['slug'],
            'display_order' => (int) $r['display_order'], 'status' => (string) $r['status'], 'parts' => (int) $r['parts']], $rows);
    }

    /** @return array<string,mixed>|null */
    public function findCategory(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, slug, display_order, status FROM part_categories WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function categoryTaken(string $name, string $slug, ?int $exceptId = null): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM part_categories WHERE (name = :name OR slug = :slug) AND id <> :except');
        $stmt->execute(['name' => $name, 'slug' => $slug, 'except' => $exceptId ?? 0]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function insertCategory(string $name, string $slug, int $order, string $status): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO part_categories (name, slug, display_order, status) VALUES (:name, :slug, :ord, :status)');
        $stmt->execute(['name' => $name, 'slug' => $slug, 'ord' => $order, 'status' => $status]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateCategory(int $id, string $name, string $slug, int $order, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE part_categories SET name = :name, slug = :slug, display_order = :ord, status = :status WHERE id = :id');
        $stmt->execute(['name' => $name, 'slug' => $slug, 'ord' => $order, 'status' => $status, 'id' => $id]);
    }

    /** @return list<array{id:int,name:string,slug:string,status:string}> every make (brands table), in the brands' own order */
    public function brands(): array
    {
        $rows = $this->pdo->query('SELECT id, name, slug, status FROM brands ORDER BY display_order, name')->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'name' => (string) $r['name'], 'slug' => (string) $r['slug'], 'status' => (string) $r['status']], $rows);
    }

    // ---- parts (admin) ----------------------------------------------------------------------------------------------

    /** @param array{q:string,category:?int,brand:?int,published:string,stock:string} $filters */
    public function countMatching(array $filters): int
    {
        [$where, $params] = $this->where($filters);
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM parts p WHERE ' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM parts WHERE deleted_at IS NULL')->fetchColumn();
    }

    /**
     * @param array{q:string,category:?int,brand:?int,published:string,stock:string} $filters
     * @return list<array<string,mixed>> newest first, with category_name
     */
    public function search(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->where($filters);
        $stmt = $this->pdo->prepare(
            'SELECT p.id, p.name, ' . self::MAIN_NUMBER . ' AS part_number, p.`condition`, p.price, p.currency, p.stock_status, p.is_published, p.slug, p.created_at,
                    c.name AS category_name, (SELECT COUNT(*) FROM part_numbers n2 WHERE n2.part_id = p.id) AS number_count
             FROM parts p JOIN part_categories c ON c.id = p.category_id
             WHERE ' . $where . ' ORDER BY p.created_at DESC, p.id DESC LIMIT :lim OFFSET :off'
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue('off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private function where(array $filters): array
    {
        $where = ['p.deleted_at IS NULL'];
        $params = [];
        if ($filters['q'] !== '') {
            [$clause, $more] = $this->textMatch($filters['q']);
            $where[] = $clause;
            $params += $more;
        }
        if ($filters['category'] !== null) {
            $where[] = 'p.category_id = :category';
            $params['category'] = $filters['category'];
        }
        if ($filters['brand'] !== null) {
            $where[] = 'EXISTS (SELECT 1 FROM part_fitments f WHERE f.part_id = p.id AND f.brand_id = :brand)';
            $params['brand'] = $filters['brand'];
        }
        if ($filters['published'] !== 'all') {
            $where[] = 'p.is_published = ' . ($filters['published'] === 'yes' ? '1' : '0');
        }
        if ($filters['stock'] === 'none') {
            $where[] = 'p.stock_status IS NULL';
        } elseif ($filters['stock'] !== 'all') {
            $where[] = 'p.stock_status = :stock';
            $params['stock'] = $filters['stock'];
        }
        if (($filters['price'] ?? 'all') !== 'all') {
            $where[] = 'p.price IS ' . ($filters['price'] === 'set' ? 'NOT ' : '') . 'NULL';
        }
        if (($filters['fits'] ?? 'all') !== 'all') {
            $where[] = ($filters['fits'] === 'set' ? '' : 'NOT ') . 'EXISTS (SELECT 1 FROM part_fitments f2 WHERE f2.part_id = p.id)';
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * Name contains the words, or any part number matches (ignoring spaces, hyphens, dots, slashes and case, so
     * "0446502220" finds "04465-02220"). LIKE wildcards in the input are escaped.
     *
     * @return array{0:string,1:array<string,string>}
     */
    private function textMatch(string $q): array
    {
        $params = ['tq1' => '%' . addcslashes($q, '%_\\') . '%'];
        $key = PartValidator::numberKey($q);
        if ($key === '') {
            return ['p.name LIKE :tq1', $params];
        }
        $params['tq2'] = '%' . addcslashes($key, '%_\\') . '%';

        return ['(p.name LIKE :tq1 OR EXISTS (SELECT 1 FROM part_numbers n WHERE n.part_id = p.id AND ' . self::NUMBER_KEY . ' LIKE :tq2))', $params];
    }

    /** @return array<string,mixed>|null a non-archived part with its category name; $lock = SELECT ... FOR UPDATE (inside a transaction) */
    public function find(int $id, bool $lock = false): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, c.name AS category_name FROM parts p JOIN part_categories c ON c.id = p.category_id
             WHERE p.id = :id AND p.deleted_at IS NULL' . ($lock ? ' FOR UPDATE' : '')
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** Archived parts count too: a slug is never reused. */
    public function slugTaken(string $slug): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM parts WHERE slug = :slug');
        $stmt->execute(['slug' => $slug]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** @param array<string,mixed> $v validated values */
    public function insert(array $v, string $slug): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO parts (category_id, name, `condition`, price, currency, stock_status, description, is_published, slug)
             VALUES (:category_id, :name, :cond, :price, :currency, :stock_status, :description, :is_published, :slug)'
        );
        $stmt->execute($this->params($v) + ['slug' => $slug]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $v validated values */
    public function update(int $id, array $v): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE parts SET category_id = :category_id, name = :name, `condition` = :cond, price = :price,
                    currency = :currency, stock_status = :stock_status, description = :description, is_published = :is_published
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute($this->params($v) + ['id' => $id]);
    }

    /** @return array<string,mixed> */
    private function params(array $v): array
    {
        return [
            'category_id' => $v['category_id'], 'name' => $v['name'], 'cond' => $v['condition'],
            'price' => $v['price'], 'currency' => $v['currency'], 'stock_status' => $v['stock_status'], 'description' => $v['description'],
            'is_published' => $v['is_published'],
        ];
    }

    public function setPublished(int $id, bool $published): void
    {
        $this->pdo->prepare('UPDATE parts SET is_published = :p WHERE id = :id AND deleted_at IS NULL')->execute(['p' => $published ? 1 : 0, 'id' => $id]);
    }

    public function setStock(int $id, string $stock): void
    {
        $this->pdo->prepare('UPDATE parts SET stock_status = :s WHERE id = :id AND deleted_at IS NULL')->execute(['s' => $stock, 'id' => $id]);
    }

    /** Archive: hidden everywhere, never hard-deleted (it may be referenced by enquiries later), and unpublished. */
    public function archive(int $id): void
    {
        $this->pdo->prepare('UPDATE parts SET deleted_at = UTC_TIMESTAMP(), is_published = 0 WHERE id = :id AND deleted_at IS NULL')->execute(['id' => $id]);
    }

    // ---- part numbers (spec 27) -------------------------------------------------------------------------------------

    /** Replaces a part's numbers, in the given order (the first becomes the main number). @param list<string> $numbers */
    public function replacePartNumbers(int $partId, array $numbers): void
    {
        $this->pdo->prepare('DELETE FROM part_numbers WHERE part_id = :id')->execute(['id' => $partId]);
        $stmt = $this->pdo->prepare('INSERT INTO part_numbers (part_id, number, sort_order) VALUES (:part, :number, :ord)');
        foreach (array_values($numbers) as $i => $number) {
            $stmt->execute(['part' => $partId, 'number' => $number, 'ord' => $i]);
        }
    }

    /**
     * @param list<int> $partIds
     * @return array<int,list<string>> each part's numbers, main first
     */
    public function partNumbersFor(array $partIds): array
    {
        if ($partIds === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($partIds), '?'));
        $stmt = $this->pdo->prepare("SELECT part_id, number FROM part_numbers WHERE part_id IN ($marks) ORDER BY part_id, sort_order, id");
        $stmt->execute(array_map('intval', $partIds));
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['part_id']][] = (string) $r['number'];
        }

        return $out;
    }

    /**
     * Parts whose compatible vehicles can be copied ("Copy from another part"): not archived, with at least one fitment,
     * except $exceptId.
     *
     * @return list<array{id:int,name:string,part_number:?string,fitments:int}>
     */
    public function copySources(?int $exceptId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.id, p.name, ' . self::MAIN_NUMBER . ' AS part_number, COUNT(f.id) AS fitments
             FROM parts p JOIN part_fitments f ON f.part_id = p.id
             WHERE p.deleted_at IS NULL AND p.id <> :except GROUP BY p.id, p.name ORDER BY p.name, p.id'
        );
        $stmt->execute(['except' => $exceptId ?? 0]);

        return array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'name' => (string) $r['name'],
            'part_number' => $r['part_number'] === null ? null : (string) $r['part_number'], 'fitments' => (int) $r['fitments']], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // ---- fitments ---------------------------------------------------------------------------------------------------

    /** @param list<array{brand_id:int,model:?string,year_from:?int,year_to:?int}> $rows */
    public function replaceFitments(int $partId, array $rows): void
    {
        $this->pdo->prepare('DELETE FROM part_fitments WHERE part_id = :id')->execute(['id' => $partId]);
        $stmt = $this->pdo->prepare('INSERT INTO part_fitments (part_id, brand_id, model, year_from, year_to) VALUES (:part, :brand, :model, :yf, :yt)');
        foreach ($rows as $r) {
            $stmt->bindValue('part', $partId, PDO::PARAM_INT);
            $stmt->bindValue('brand', $r['brand_id'], PDO::PARAM_INT);
            $stmt->bindValue('model', $r['model'], $r['model'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue('yf', $r['year_from'], $r['year_from'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue('yt', $r['year_to'], $r['year_to'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->execute();
        }
    }

    /**
     * @param list<int> $partIds
     * @return array<int,list<array{brand_id:int,brand_name:string,brand_slug:string,model:?string,year_from:?int,year_to:?int}>> in entry order
     */
    public function fitmentsFor(array $partIds): array
    {
        if ($partIds === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($partIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT f.part_id, f.brand_id, b.name AS brand_name, b.slug AS brand_slug, f.model, f.year_from, f.year_to
             FROM part_fitments f JOIN brands b ON b.id = f.brand_id WHERE f.part_id IN ($marks) ORDER BY f.part_id, f.id"
        );
        $stmt->execute(array_map('intval', $partIds));
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['part_id']][] = [
                'brand_id' => (int) $r['brand_id'], 'brand_name' => (string) $r['brand_name'], 'brand_slug' => (string) $r['brand_slug'],
                'model' => $r['model'] === null ? null : (string) $r['model'],
                'year_from' => $r['year_from'] === null ? null : (int) $r['year_from'], 'year_to' => $r['year_to'] === null ? null : (int) $r['year_to'],
            ];
        }

        return $out;
    }

    // ---- images -----------------------------------------------------------------------------------------------------

    /** @return list<array{id:int,file_path:string,alt_text:?string,display_order:int,is_primary:int}> primary first, then in order */
    public function images(int $partId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, file_path, alt_text, display_order, is_primary FROM part_images WHERE part_id = :id ORDER BY is_primary DESC, display_order, id');
        $stmt->execute(['id' => $partId]);

        return array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'file_path' => (string) $r['file_path'], 'alt_text' => $r['alt_text'],
            'display_order' => (int) $r['display_order'], 'is_primary' => (int) $r['is_primary']], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function insertImage(int $partId, string $path, ?string $originalName, string $alt, bool $primary): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO part_images (part_id, file_path, original_filename, alt_text, display_order, is_primary)
             SELECT :part, :path, :orig, :alt, COALESCE(MAX(display_order) + 1, 0), :primary FROM part_images WHERE part_id = :part2'
        );
        $stmt->execute(['part' => $partId, 'path' => $path, 'orig' => $originalName, 'alt' => $alt, 'primary' => $primary ? 1 : 0, 'part2' => $partId]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array{id:int,file_path:string,is_primary:int}|null */
    public function findImage(int $partId, int $imageId, bool $lock = false): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, file_path, is_primary FROM part_images WHERE id = :img AND part_id = :part' . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute(['img' => $imageId, 'part' => $partId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : ['id' => (int) $row['id'], 'file_path' => (string) $row['file_path'], 'is_primary' => (int) $row['is_primary']];
    }

    public function deleteImage(int $partId, int $imageId): void
    {
        $this->pdo->prepare('DELETE FROM part_images WHERE id = :img AND part_id = :part')->execute(['img' => $imageId, 'part' => $partId]);
    }

    /** Makes one image the primary (clears the old one first: the unique key allows one primary per part). */
    public function setPrimaryImage(int $partId, int $imageId): void
    {
        $this->pdo->prepare('UPDATE part_images SET is_primary = 0 WHERE part_id = :part AND is_primary = 1')->execute(['part' => $partId]);
        $this->pdo->prepare('UPDATE part_images SET is_primary = 1 WHERE id = :img AND part_id = :part')->execute(['img' => $imageId, 'part' => $partId]);
    }

    /** After the primary was removed: the first remaining image (by order) becomes primary. */
    public function promoteFirstImage(int $partId): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM part_images WHERE part_id = :part ORDER BY display_order, id LIMIT 1');
        $stmt->execute(['part' => $partId]);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            $this->setPrimaryImage($partId, (int) $id);
        }
    }

    // ---- public -----------------------------------------------------------------------------------------------------

    /**
     * Newest public parts (published, not archived), optionally only one category or one make (by slug, matched exactly).
     *
     * @return list<array<string,mixed>> with category_name, category_slug and primary image path (or null)
     */
    public function latestPublic(int $limit, ?string $categorySlug = null, ?string $brandSlug = null, bool $pricedOnly = false): array
    {
        $where = ['p.is_published = 1', 'p.deleted_at IS NULL'];
        if ($pricedOnly) {
            $where[] = 'p.price IS NOT NULL';   // homepage: only parts with a price (owner decision 2026-10-26)
        }
        $params = [];
        if ($categorySlug !== null) {
            $where[] = 'c.slug = :cat';
            $params['cat'] = $categorySlug;
        }
        if ($brandSlug !== null) {
            $where[] = 'EXISTS (SELECT 1 FROM part_fitments f JOIN brands b ON b.id = f.brand_id WHERE f.part_id = p.id AND b.slug = :brand)';
            $params['brand'] = $brandSlug;
        }
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::PUBLIC_COLUMNS . '
             FROM parts p JOIN part_categories c ON c.id = p.category_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY p.created_at DESC, p.id DESC LIMIT :lim'
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array{slug:string,name:string}> categories that have at least one public part ($pricedOnly: one with a price) */
    public function publicCategories(bool $pricedOnly = false): array
    {
        return $this->pdo->query(
            'SELECT c.slug, c.name FROM part_categories c
             WHERE EXISTS (SELECT 1 FROM parts p WHERE p.category_id = c.id AND p.is_published = 1 AND p.deleted_at IS NULL' . ($pricedOnly ? ' AND p.price IS NOT NULL' : '') . ')
             ORDER BY c.display_order, c.name'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array{id:int,slug:string,name:string}> makes that at least one public part fits ($pricedOnly: one with a price) */
    public function publicMakes(bool $pricedOnly = false): array
    {
        $rows = $this->pdo->query(
            'SELECT b.id, b.slug, b.name FROM brands b
             WHERE EXISTS (SELECT 1 FROM part_fitments f JOIN parts p ON p.id = f.part_id
                           WHERE f.brand_id = b.id AND p.is_published = 1 AND p.deleted_at IS NULL' . ($pricedOnly ? ' AND p.price IS NOT NULL' : '') . ')
             ORDER BY b.display_order, b.name'
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'slug' => (string) $r['slug'], 'name' => (string) $r['name']], $rows);
    }

    // ---- public: the /parts/ page (spec 27) -------------------------------------------------------------------------

    private const PUBLIC_COLUMNS = 'p.id, p.name, (SELECT n.number FROM part_numbers n WHERE n.part_id = p.id ORDER BY n.sort_order, n.id LIMIT 1) AS part_number,
                    p.`condition`, p.price, p.currency, p.stock_status, p.slug, p.created_at,
                    c.name AS category_name, c.slug AS category_slug,
                    (SELECT i.file_path FROM part_images i WHERE i.part_id = p.id AND i.is_primary = 1 LIMIT 1) AS image_path';

    /**
     * The models entered for a make in public parts' compatibility, each once (ignoring case), alphabetical.
     *
     * @return list<string>
     */
    public function publicModels(int $brandId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT MIN(TRIM(f.model)) AS model FROM part_fitments f JOIN parts p ON p.id = f.part_id
             WHERE f.brand_id = :brand AND f.model IS NOT NULL AND p.is_published = 1 AND p.deleted_at IS NULL
             GROUP BY LOWER(TRIM(f.model)) ORDER BY model'
        );
        $stmt->execute(['brand' => $brandId]);

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return array<int,list<string>> every make with public fitments => its models (for the model list in the browser) */
    public function publicModelsByMake(): array
    {
        $rows = $this->pdo->query(
            'SELECT f.brand_id, MIN(TRIM(f.model)) AS model FROM part_fitments f JOIN parts p ON p.id = f.part_id
             WHERE f.model IS NOT NULL AND p.is_published = 1 AND p.deleted_at IS NULL
             GROUP BY f.brand_id, LOWER(TRIM(f.model)) ORDER BY f.brand_id, model'
        )->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['brand_id']][] = (string) $r['model'];
        }

        return $out;
    }

    /** The oldest year mentioned in public parts' compatibility, or null when no year is entered anywhere. */
    public function publicOldestYear(): ?int
    {
        $year = $this->pdo->query(
            'SELECT MIN(LEAST(COALESCE(f.year_from, 9999), COALESCE(f.year_to, 9999))) FROM part_fitments f JOIN parts p ON p.id = f.part_id
             WHERE p.is_published = 1 AND p.deleted_at IS NULL'
        )->fetchColumn();

        return $year === false || $year === null || (int) $year === 9999 ? null : (int) $year;
    }

    /**
     * Public parts for /parts/: published, not archived, optionally one category, a search text, and "fits my car".
     * A part fits when a fitment has the make, its model is empty or the chosen one (ignoring case), and the year is in
     * its range (open ends unlimited); parts with no fitments at all are included too (listed after the parts that fit).
     * Ordered by category order, then name; with a vehicle chosen, the parts that fit it come first.
     *
     * @param array{category:?string,q:string,brand_id:?int,model:?string,year:?int} $f validated values only
     * @return array{0:int,1:list<array<string,mixed>>,2:?int} total, rows, and with a vehicle chosen how many list it (the rest are "confirm fit")
     */
    public function publicBrowse(array $f, int $limit, int $offset): array
    {
        $where = ['p.is_published = 1', 'p.deleted_at IS NULL'];
        $params = [];
        if ($f['category'] !== null) {
            $where[] = 'c.slug = :cat';
            $params['cat'] = $f['category'];
        }
        if ($f['q'] !== '') {
            [$clause, $more] = $this->textMatch($f['q']);
            $where[] = $clause;
            $params += $more;
        }
        if ($f['brand_id'] !== null) {
            $fit = ['f.part_id = p.id', 'f.brand_id = :brand'];
            $params['brand'] = $f['brand_id'];
            if ($f['model'] !== null) {
                $fit[] = '(f.model IS NULL OR LOWER(TRIM(f.model)) = LOWER(:model))';
                $params['model'] = $f['model'];
            }
            if ($f['year'] !== null) {
                $fit[] = '(f.year_from IS NULL OR f.year_from <= :y1) AND (f.year_to IS NULL OR f.year_to >= :y2)';
                $params['y1'] = $f['year'];
                $params['y2'] = $f['year'];
            }
            $where[] = '(EXISTS (SELECT 1 FROM part_fitments f WHERE ' . implode(' AND ', $fit) . ')
                         OR NOT EXISTS (SELECT 1 FROM part_fitments f0 WHERE f0.part_id = p.id))';
        }
        $from = ' FROM parts p JOIN part_categories c ON c.id = p.category_id WHERE ' . implode(' AND ', $where);

        // total, and (with a vehicle chosen) how many of them really list it: the rest are "confirm fit" parts
        $count = $this->pdo->prepare('SELECT COUNT(*), SUM(EXISTS (SELECT 1 FROM part_fitments f8 WHERE f8.part_id = p.id))' . $from);
        $count->execute($params);
        [$total, $listed] = $count->fetch(PDO::FETCH_NUM);
        $total = (int) $total;
        $fitting = $f['brand_id'] === null ? null : (int) $listed;
        if ($total === 0) {
            return [0, [], $fitting];
        }
        // With a vehicle chosen, the parts that fit it come first; "confirm fit" parts (no compatibility entered) follow.
        // In this result a part either matches or has no fitments at all, so "has fitments" means "fits".
        $first = $f['brand_id'] !== null ? 'EXISTS (SELECT 1 FROM part_fitments f9 WHERE f9.part_id = p.id) DESC, ' : '';
        $stmt = $this->pdo->prepare('SELECT ' . self::PUBLIC_COLUMNS . $from . ' ORDER BY ' . $first . 'c.display_order, c.name, p.name, p.id LIMIT :lim OFFSET :off');
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue('off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [$total, $stmt->fetchAll(PDO::FETCH_ASSOC), $fitting];
    }

    /**
     * Parts for one vehicle's page (spec 28, owner decisions 2026-10-26): published, not archived, marked IN STOCK, and
     * with a compatible-vehicle row that names this make, with an empty model ("all models") or this model (ignoring case),
     * and a year range that includes this year (open ends unlimited). Parts without compatible vehicles are never shown.
     *
     * @return array{0:int,1:list<array<string,mixed>>} total, the first $limit rows (category order, then name)
     */
    public function publicForVehicle(int $brandId, string $model, int $year, int $limit): array
    {
        $from = " FROM parts p JOIN part_categories c ON c.id = p.category_id
             WHERE p.is_published = 1 AND p.deleted_at IS NULL AND p.stock_status = 'in_stock'
               AND EXISTS (SELECT 1 FROM part_fitments f WHERE f.part_id = p.id AND f.brand_id = :brand
                           AND (f.model IS NULL OR LOWER(TRIM(f.model)) = LOWER(TRIM(:model)))
                           AND (f.year_from IS NULL OR f.year_from <= :y1) AND (f.year_to IS NULL OR f.year_to >= :y2))";
        $params = ['brand' => $brandId, 'model' => $model, 'y1' => $year, 'y2' => $year];
        $count = $this->pdo->prepare('SELECT COUNT(*)' . $from);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        if ($total === 0) {
            return [0, []];
        }
        $stmt = $this->pdo->prepare('SELECT ' . self::PUBLIC_COLUMNS . $from . ' ORDER BY c.display_order, c.name, p.name, p.id LIMIT :lim');
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return [$total, $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    /** @return array{name:string,part_number:?string}|null a public part by slug (for the contact form's pre-filled message) */
    public function publicNameBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT p.name, ' . self::MAIN_NUMBER . ' AS part_number FROM parts p WHERE p.slug = :slug AND p.is_published = 1 AND p.deleted_at IS NULL');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : ['name' => (string) $row['name'], 'part_number' => $row['part_number'] === null ? null : (string) $row['part_number']];
    }
}
