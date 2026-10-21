<?php

declare(strict_types=1);

/**
 * All SQL for vehicle images (spec 07_spec_vehicle_image.md). Uses the existing
 * `vehicle_images` table unchanged:
 *   vehicle_id -> vehicles.id (CASCADE), file_path, original_filename, alt_text,
 *   display_order, is_primary, and the generated column primary_vehicle_id with a
 *   UNIQUE key, so the database itself refuses a second primary image for a vehicle.
 *
 * Every query is a prepared statement. Anything that takes both a vehicle id and an
 * image id matches BOTH in the WHERE clause, so an image can only ever be reached
 * through the vehicle it belongs to (no IDOR).
 */
final class VehicleImageRepository
{
    private const COLUMNS = 'id, vehicle_id, file_path, original_filename, alt_text, photo_type, display_order, is_primary, created_at';

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Every image of one vehicle in gallery order (one query, no per-image lookups).
     *
     * @return list<array<string,mixed>>
     */
    public function forVehicle(int $vehicleId, bool $forUpdate = false): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM vehicle_images WHERE vehicle_id = :vehicle ORDER BY display_order ASC, id ASC'
            . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $stmt->bindValue('vehicle', $vehicleId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>|null the image, only if it belongs to this vehicle */
    public function findForVehicle(int $vehicleId, int $imageId, bool $forUpdate = false): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM vehicle_images WHERE id = :id AND vehicle_id = :vehicle' . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $stmt->bindValue('id', $imageId, PDO::PARAM_INT);
        $stmt->bindValue('vehicle', $vehicleId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** Next free gallery position (1 for the first image). */
    public function nextOrder(int $vehicleId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(display_order), 0) + 1 FROM vehicle_images WHERE vehicle_id = :vehicle');
        $stmt->bindValue('vehicle', $vehicleId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function insert(int $vehicleId, string $path, string $originalName, ?string $altText, int $order, bool $primary): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO vehicle_images (vehicle_id, file_path, original_filename, alt_text, display_order, is_primary)
             VALUES (:vehicle, :path, :name, :alt, :ord, :primary)'
        );
        $stmt->bindValue('vehicle', $vehicleId, PDO::PARAM_INT);
        $stmt->bindValue('path', $path);
        $stmt->bindValue('name', $originalName);
        $stmt->bindValue('alt', $altText, $altText === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue('ord', $order, PDO::PARAM_INT);
        $stmt->bindValue('primary', $primary ? 1 : 0, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    /** Clears the primary flag of every image of the vehicle (run before promoting another one). */
    public function clearPrimary(int $vehicleId): void
    {
        $stmt = $this->pdo->prepare('UPDATE vehicle_images SET is_primary = 0 WHERE vehicle_id = :vehicle AND is_primary = 1');
        $stmt->bindValue('vehicle', $vehicleId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function markPrimary(int $vehicleId, int $imageId): void
    {
        $stmt = $this->pdo->prepare('UPDATE vehicle_images SET is_primary = 1 WHERE id = :id AND vehicle_id = :vehicle');
        $stmt->bindValue('id', $imageId, PDO::PARAM_INT);
        $stmt->bindValue('vehicle', $vehicleId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function updateAlt(int $vehicleId, int $imageId, ?string $altText): void
    {
        $stmt = $this->pdo->prepare('UPDATE vehicle_images SET alt_text = :alt WHERE id = :id AND vehicle_id = :vehicle');
        $stmt->bindValue('alt', $altText, $altText === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue('id', $imageId, PDO::PARAM_INT);
        $stmt->bindValue('vehicle', $vehicleId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /** Exterior or interior (the gallery tab on the public vehicle page). */
    public function updateType(int $vehicleId, int $imageId, string $type): void
    {
        $stmt = $this->pdo->prepare('UPDATE vehicle_images SET photo_type = :type WHERE id = :id AND vehicle_id = :vehicle');
        $stmt->bindValue('type', $type);
        $stmt->bindValue('id', $imageId, PDO::PARAM_INT);
        $stmt->bindValue('vehicle', $vehicleId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function setOrder(int $vehicleId, int $imageId, int $order): void
    {
        $stmt = $this->pdo->prepare('UPDATE vehicle_images SET display_order = :ord WHERE id = :id AND vehicle_id = :vehicle');
        $stmt->bindValue('ord', $order, PDO::PARAM_INT);
        $stmt->bindValue('id', $imageId, PDO::PARAM_INT);
        $stmt->bindValue('vehicle', $vehicleId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function delete(int $vehicleId, int $imageId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM vehicle_images WHERE id = :id AND vehicle_id = :vehicle');
        $stmt->bindValue('id', $imageId, PDO::PARAM_INT);
        $stmt->bindValue('vehicle', $vehicleId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() === 1;
    }

    /**
     * The photographs of several vehicles in display order (the primary first): up to $perVehicle each, in ONE query
     * (public inventory cards; no N+1).
     *
     * @param list<int> $vehicleIds
     * @return array<int,list<array{path:string,alt:?string,primary:bool}>> keyed by vehicle id
     */
    public function galleryFor(array $vehicleIds, int $perVehicle = 5): array
    {
        if ($vehicleIds === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($vehicleIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT vehicle_id, file_path, alt_text, photo_type, is_primary FROM vehicle_images WHERE vehicle_id IN ($marks)
             ORDER BY vehicle_id, is_primary DESC, display_order, id"
        );
        foreach (array_values($vehicleIds) as $i => $id) {
            $stmt->bindValue($i + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $id = (int) $row['vehicle_id'];
            if (count($out[$id] ?? []) < $perVehicle) {
                $out[$id][] = ['path' => (string) $row['file_path'], 'alt' => $row['alt_text'], 'type' => (string) $row['photo_type'], 'primary' => (int) $row['is_primary'] === 1];
            }
        }

        return $out;
    }

    /**
     * Primary image and image count for several vehicles in ONE query (used by lists; no N+1).
     *
     * @param list<int> $vehicleIds
     * @return array<int,array{count:int,primary:?string,alt:?string}> keyed by vehicle id
     */
    public function summaryFor(array $vehicleIds): array
    {
        if ($vehicleIds === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($vehicleIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT vehicle_id, COUNT(*) AS total, MAX(CASE WHEN is_primary = 1 THEN file_path END) AS primary_path,
                    MAX(CASE WHEN is_primary = 1 THEN alt_text END) AS primary_alt
             FROM vehicle_images WHERE vehicle_id IN ($marks) GROUP BY vehicle_id"
        );
        foreach (array_values($vehicleIds) as $i => $id) {
            $stmt->bindValue($i + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int) $row['vehicle_id']] = ['count' => (int) $row['total'], 'primary' => $row['primary_path'], 'alt' => $row['primary_alt']];
        }

        return $out;
    }
}
