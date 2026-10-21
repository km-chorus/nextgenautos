<?php

declare(strict_types=1);

/**
 * Data access for the parts catalogue (spec 16, phase 1): part_categories, parts, part_fitments, part_images, and the
 * existing brands (the makes a part fits). Prepared statements only; LIKE wildcards in searches are escaped.
 * Archived parts (deleted_at set) are invisible to every method except the ones that say otherwise.
 */
final class PartRepository
{
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
            'SELECT p.id, p.name, p.part_number, p.`condition`, p.price, p.currency, p.stock_status, p.is_published, p.slug, p.created_at,
                    c.name AS category_name
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
            $where[] = '(p.name LIKE :q1 OR p.part_number LIKE :q2)';
            $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
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
        if ($filters['stock'] !== 'all') {
            $where[] = 'p.stock_status = :stock';
            $params['stock'] = $filters['stock'];
        }

        return [implode(' AND ', $where), $params];
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
            'INSERT INTO parts (category_id, name, part_number, `condition`, price, currency, stock_status, description, is_published, slug)
             VALUES (:category_id, :name, :part_number, :cond, :price, :currency, :stock_status, :description, :is_published, :slug)'
        );
        $stmt->execute($this->params($v) + ['slug' => $slug]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $v validated values */
    public function update(int $id, array $v): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE parts SET category_id = :category_id, name = :name, part_number = :part_number, `condition` = :cond, price = :price,
                    currency = :currency, stock_status = :stock_status, description = :description, is_published = :is_published
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute($this->params($v) + ['id' => $id]);
    }

    /** @return array<string,mixed> */
    private function params(array $v): array
    {
        return [
            'category_id' => $v['category_id'], 'name' => $v['name'], 'part_number' => $v['part_number'], 'cond' => $v['condition'],
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
    public function latestPublic(int $limit, ?string $categorySlug = null, ?string $brandSlug = null): array
    {
        $where = ['p.is_published = 1', 'p.deleted_at IS NULL'];
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
            'SELECT p.id, p.name, p.part_number, p.`condition`, p.price, p.currency, p.stock_status, p.slug,
                    c.name AS category_name, c.slug AS category_slug,
                    (SELECT i.file_path FROM part_images i WHERE i.part_id = p.id AND i.is_primary = 1 LIMIT 1) AS image_path
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

    /** @return list<array{slug:string,name:string}> categories that have at least one public part */
    public function publicCategories(): array
    {
        return $this->pdo->query(
            'SELECT c.slug, c.name FROM part_categories c
             WHERE EXISTS (SELECT 1 FROM parts p WHERE p.category_id = c.id AND p.is_published = 1 AND p.deleted_at IS NULL)
             ORDER BY c.display_order, c.name'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array{slug:string,name:string}> makes that at least one public part fits */
    public function publicMakes(): array
    {
        return $this->pdo->query(
            'SELECT b.slug, b.name FROM brands b
             WHERE EXISTS (SELECT 1 FROM part_fitments f JOIN parts p ON p.id = f.part_id
                           WHERE f.brand_id = b.id AND p.is_published = 1 AND p.deleted_at IS NULL)
             ORDER BY b.display_order, b.name'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{name:string,part_number:?string}|null a public part by slug (for the contact form's pre-filled message) */
    public function publicNameBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT name, part_number FROM parts WHERE slug = :slug AND is_published = 1 AND deleted_at IS NULL');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : ['name' => (string) $row['name'], 'part_number' => $row['part_number'] === null ? null : (string) $row['part_number']];
    }
}
