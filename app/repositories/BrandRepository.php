<?php

declare(strict_types=1);

/**
 * All SQL for Brand Management (spec 05_spec_brand_crud.md) lives here.
 * Every query is a PDO prepared statement; values are always bound.
 *
 * `vehicle_count` counts every vehicle row that references the brand, including
 * archived (soft-deleted) ones, because the foreign key (RESTRICT) does too.
 */
class BrandRepository
{
    private const LIST_COLUMNS = 'b.id, b.name, b.slug, b.status, b.display_order, b.created_at,
        (SELECT COUNT(*) FROM vehicles v WHERE v.brand_id = b.id) AS vehicle_count';

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * One query for the whole list (vehicle counts included, no per-row queries).
     * Order is deterministic: display_order, then name, then id.
     *
     * @return list<array<string,mixed>>
     */
    public function search(string $term, string $status): array
    {
        $where = [];
        $params = [];
        if ($term !== '') {
            $like = '%' . addcslashes($term, '\\%_') . '%';
            $where[] = '(b.name LIKE :term_name ESCAPE \'\\\\\' OR b.slug LIKE :term_slug ESCAPE \'\\\\\')';
            $params['term_name'] = $like;
            $params['term_slug'] = $like;
        }
        if ($status === 'active' || $status === 'inactive') {
            $where[] = 'b.status = :status';
            $params['status'] = $status;
        }

        $stmt = $this->pdo->prepare(
            'SELECT ' . self::LIST_COLUMNS . ' FROM brands b'
            . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
            . ' ORDER BY b.display_order ASC, b.name ASC, b.id ASC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * The brands the public website offers (spec 10C): ACTIVE only, in the administrator's order
     * (display_order, then name, then id). One query, only the columns the public needs.
     *
     * @return list<array{name:string,slug:string,logo_path:?string}>
     */
    public function publicActive(): array
    {
        return $this->pdo->query(
            "SELECT b.name, b.slug, b.logo_path FROM brands b WHERE b.status = 'active' ORDER BY b.display_order ASC, b.name ASC, b.id ASC"
        )->fetchAll();
    }

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM brands')->fetchColumn();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id, bool $forUpdate = false): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT b.id, b.name, b.slug, b.description, b.status, b.display_order, b.created_at, b.updated_at,
                (SELECT COUNT(*) FROM vehicles v WHERE v.brand_id = b.id) AS vehicle_count
             FROM brands b WHERE b.id = :id' . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** True when another brand (not $exceptId) already has this name. Same collation as the UNIQUE key. */
    public function nameTaken(string $name, ?int $exceptId = null): bool
    {
        return $this->exists('name', $name, $exceptId);
    }

    public function slugTaken(string $slug, ?int $exceptId = null): bool
    {
        return $this->exists('slug', $slug, $exceptId);
    }

    public function nextDisplayOrder(): int
    {
        return (int) $this->pdo->query('SELECT COALESCE(MAX(display_order), 0) + 1 FROM brands')->fetchColumn();
    }

    /** @param array{name:string,slug:string,description:?string,display_order:int,status:string} $values */
    public function insert(array $values): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO brands (name, slug, description, display_order, status)
             VALUES (:name, :slug, :description, :display_order, :status)'
        );
        $this->bindValues($stmt, $values);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Updates only the editable fields (never id, logo_path or created_at).
     *
     * @param array{name:string,slug:string,description:?string,display_order:int,status:string} $values
     */
    public function update(int $id, array $values): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE brands SET name = :name, slug = :slug, description = :description,
                display_order = :display_order, status = :status
             WHERE id = :id'
        );
        $this->bindValues($stmt, $values);
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function setStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE brands SET status = :status WHERE id = :id');
        $stmt->bindValue('status', $status);
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM brands WHERE id = :id');
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() === 1;
    }

    private function exists(string $column, string $value, ?int $exceptId): bool
    {
        // $column is one of two fixed names chosen by this class, never user input.
        $sql = 'SELECT 1 FROM brands WHERE ' . ($column === 'slug' ? 'slug' : 'name') . ' = :value'
            . ($exceptId !== null ? ' AND id <> :except' : '') . ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('value', $value);
        if ($exceptId !== null) {
            $stmt->bindValue('except', $exceptId, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }

    private function bindValues(PDOStatement $stmt, array $values): void
    {
        $stmt->bindValue('name', $values['name']);
        $stmt->bindValue('slug', $values['slug']);
        $stmt->bindValue('description', $values['description'], $values['description'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue('display_order', $values['display_order'], PDO::PARAM_INT);
        $stmt->bindValue('status', $values['status']);
    }
}
