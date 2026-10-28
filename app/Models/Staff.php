<?php
declare(strict_types=1);

/** Read-only view of staff in workCentral UK. */
final class Staff
{
    private const COLS = 'id, name, role, title, department, avatar_url, kc_handle, email, active';

    public static function find(string $id): ?array
    {
        return Database::query('SELECT ' . self::COLS . ' FROM ' . Database::staffTable() . ' WHERE id = ?', [$id])->fetch() ?: null;
    }

    public static function findByHandle(string $handle): ?array
    {
        $h = strtolower(trim($handle));
        if ($h === '') return null;
        return Database::query('SELECT ' . self::COLS . ' FROM ' . Database::staffTable() . ' WHERE LOWER(kc_handle) = ? LIMIT 1', [$h])->fetch() ?: null;
    }

    /** @return array<string, array> keyed by id */
    public static function many(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if (!$ids) return [];
        $in = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::query('SELECT ' . self::COLS . ' FROM ' . Database::staffTable() . " WHERE id IN ($in)", $ids)->fetchAll();
        return array_column($rows, null, 'id');
    }

    public static function active(): array
    {
        return Database::query('SELECT ' . self::COLS . ' FROM ' . Database::staffTable() . ' WHERE active = 1 ORDER BY name')->fetchAll();
    }
}
