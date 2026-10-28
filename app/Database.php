<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        return self::$pdo ??= new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', env('DB_HOST'), env('DB_PORT', '3306'), env('DB_NAME')),
            env('DB_USER'),
            env('DB_PASS'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false],
        );
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Staff table: workCentral's users table when STAFF_DB is set (same MySQL server), else the local synced copy. */
    public static function staffTable(): string
    {
        $db = env('STAFF_DB');
        if ($db === '') return '`staff`';
        if (!preg_match('/^[A-Za-z0-9_]+$/', $db)) throw new RuntimeException('Invalid STAFF_DB');
        return "`$db`.`users`";
    }
}
