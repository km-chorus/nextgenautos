<?php

declare(strict_types=1);

/**
 * Company settings (the existing `settings` table: key, category, value). All SQL is a prepared statement.
 * Writes are used only by the admin Settings page (spec 17), which validates every value first.
 */
final class SettingsRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** The stored value of one setting in one category, or null when it does not exist or is empty. */
    public function value(string $key, string $category): ?string
    {
        $stmt = $this->pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = :key AND category = :category');
        $stmt->bindValue('key', $key);
        $stmt->bindValue('category', $category);
        $stmt->execute();
        $value = $stmt->fetchColumn();

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * Every non-empty setting of these categories, in one query.
     *
     * @param list<string> $categories
     * @return array<string,string> key => value
     */
    public function valuesIn(array $categories): array
    {
        if ($categories === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($categories), '?'));
        $stmt = $this->pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE category IN ($marks)");
        $stmt->execute(array_values($categories));
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_string($row['setting_value']) && trim($row['setting_value']) !== '') {
                $out[(string) $row['setting_key']] = trim($row['setting_value']);
            }
        }

        return $out;
    }

    /** Creates or replaces one setting (the key is unique). */
    public function put(string $key, string $category, string $value): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO settings (setting_key, category, setting_value) VALUES (:key, :category, :value)
             ON DUPLICATE KEY UPDATE category = VALUES(category), setting_value = VALUES(setting_value)'
        );
        $stmt->execute(['key' => $key, 'category' => $category, 'value' => $value]);
    }

    /** Removes one setting of this category (an empty field in the admin form). */
    public function remove(string $key, string $category): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM settings WHERE setting_key = :key AND category = :category');
        $stmt->execute(['key' => $key, 'category' => $category]);
    }
}
