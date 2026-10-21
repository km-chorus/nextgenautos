<?php

declare(strict_types=1);

/**
 * All SQL for Team Management (spec 08_spec_team.md). Every query is a PDO prepared
 * statement; values are always bound. Lists come from ONE query (no per-row lookups).
 *
 * Order is always display_order ASC, name ASC, id ASC: deterministic, never random.
 */
final class TeamRepository
{
    private const COLUMNS = 'id, name, position, category, biography, photo_path, display_order, status, created_at, updated_at';

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array{q:string,category:string,status:string} $filters
     * @return list<array<string,mixed>>
     */
    public function search(array $filters): array
    {
        $where = [];
        $params = [];
        if ($filters['q'] !== '') {
            $like = '%' . addcslashes($filters['q'], '\\%_') . '%';
            $where[] = '(name LIKE :q_name ESCAPE \'\\\\\' OR position LIKE :q_position ESCAPE \'\\\\\')';
            $params['q_name'] = $like;
            $params['q_position'] = $like;
        }
        if ($filters['category'] !== '') {
            $where[] = 'category = :category';
            $params['category'] = $filters['category'];
        }
        if ($filters['status'] === 'active' || $filters['status'] === 'inactive') {
            $where[] = 'status = :status';
            $params['status'] = $filters['status'];
        }

        $stmt = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM team_members' . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
            . ' ORDER BY display_order ASC, name ASC, id ASC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM team_members')->fetchColumn();
    }

    /**
     * Every member's id in display order (the set a reorder must match exactly).
     *
     * @return list<int>
     */
    public function allIdsInOrder(bool $forUpdate = false): array
    {
        $rows = $this->pdo->query('SELECT id FROM team_members ORDER BY display_order ASC, name ASC, id ASC' . ($forUpdate ? ' FOR UPDATE' : ''))->fetchAll(PDO::FETCH_COLUMN);

        return array_map('intval', $rows);
    }

    /** @return array<string,mixed>|null */
    public function find(int $id, bool $forUpdate = false): ?array
    {
        $stmt = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM team_members WHERE id = :id' . ($forUpdate ? ' FOR UPDATE' : ''));
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function nextDisplayOrder(): int
    {
        return (int) $this->pdo->query('SELECT COALESCE(MAX(display_order), 0) + 1 FROM team_members')->fetchColumn();
    }

    /** @param array{name:string,position:string,category:string,biography:?string,display_order:int,status:string} $v */
    public function insert(array $v, ?string $photoPath): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO team_members (name, position, category, biography, photo_path, display_order, status)
             VALUES (:name, :position, :category, :biography, :photo, :ord, :status)'
        );
        $this->bind($stmt, $v);
        $stmt->bindValue('photo', $photoPath, $photoPath === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Updates the text fields. The photo reference changes only when $changePhoto is true
     * (an edit without a new photo must keep the existing one).
     *
     * @param array{name:string,position:string,category:string,biography:?string,display_order:int,status:string} $v
     */
    public function update(int $id, array $v, bool $changePhoto, ?string $photoPath): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE team_members SET name = :name, position = :position, category = :category, biography = :biography,
                display_order = :ord, status = :status' . ($changePhoto ? ', photo_path = :photo' : '') . ' WHERE id = :id'
        );
        $this->bind($stmt, $v);
        if ($changePhoto) {
            $stmt->bindValue('photo', $photoPath, $photoPath === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        }
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function setPhoto(int $id, ?string $photoPath): void
    {
        $stmt = $this->pdo->prepare('UPDATE team_members SET photo_path = :photo WHERE id = :id');
        $stmt->bindValue('photo', $photoPath, $photoPath === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function setStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE team_members SET status = :status WHERE id = :id');
        $stmt->bindValue('status', $status);
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function setOrder(int $id, int $order): void
    {
        $stmt = $this->pdo->prepare('UPDATE team_members SET display_order = :ord WHERE id = :id');
        $stmt->bindValue('ord', $order, PDO::PARAM_INT);
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM team_members WHERE id = :id');
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() === 1;
    }

    /**
     * What the future public Team page needs: active members in display order. Not used by any
     * page yet (the public site is a later specification), but defined and tested here so the data
     * is ready: id, name, position, category, biography, photo, display order, status.
     *
     * @return list<array<string,mixed>>
     */
    public function activeInDisplayOrder(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, position, category, biography, photo_path, display_order, status FROM team_members
             WHERE status = :status ORDER BY display_order ASC, name ASC, id ASC'
        );
        $stmt->execute(['status' => 'active']);

        return $stmt->fetchAll();
    }

    /**
     * The first active team members for the public homepage (spec 11C): the administrator's order
     * (display_order, name, id), a bound LIMIT, and only the customer-facing columns (no id, status
     * or timestamps).
     *
     * @return list<array{name:string,position:string,category:string,biography:?string,photo_path:?string}>
     */
    public function publicActive(int $limit): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT name, position, category, biography, photo_path FROM team_members WHERE status = 'active'
             ORDER BY display_order ASC, name ASC, id ASC LIMIT :limit"
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @param array<string,mixed> $v */
    private function bind(PDOStatement $stmt, array $v): void
    {
        $stmt->bindValue('name', $v['name']);
        $stmt->bindValue('position', $v['position']);
        $stmt->bindValue('category', $v['category']);
        $stmt->bindValue('biography', $v['biography'], $v['biography'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue('ord', (int) $v['display_order'], PDO::PARAM_INT);
        $stmt->bindValue('status', $v['status']);
    }
}
