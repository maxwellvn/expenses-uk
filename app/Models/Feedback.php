<?php
declare(strict_types=1);

final class Feedback
{
    public const TOPICS = [
        'access' => 'I cannot sign in',
        'claim' => 'Problem with a claim',
        'idea' => 'Suggestion',
        'other' => 'Something else',
    ];
    private const PER_HOUR = 5;

    public static function ipHash(): string
    {
        return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . env('KC_CLIENT_ID'));
    }

    public static function tooMany(): bool
    {
        return (int) Database::query(
            'SELECT COUNT(*) FROM feedback WHERE ip_hash = ? AND created_at > NOW() - INTERVAL 1 HOUR',
            [self::ipHash()],
        )->fetchColumn() >= self::PER_HOUR;
    }

    public static function create(?string $staffId, string $name, string $handle, string $topic, string $message, string $page): void
    {
        Database::query(
            'INSERT INTO feedback (staff_id, name, kc_handle, topic, message, page, ip_hash) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$staffId, $name, $handle ?: null, $topic, $message, $page ?: null, self::ipHash()],
        );
    }

    public static function list(string $status): array
    {
        return Database::query('SELECT * FROM feedback WHERE status = ? ORDER BY created_at DESC LIMIT 200', [$status])->fetchAll();
    }

    public static function countNew(): int
    {
        return (int) Database::query('SELECT COUNT(*) FROM feedback WHERE status = "new"')->fetchColumn();
    }

    public static function resolve(int $id, string $by, bool $resolved): void
    {
        Database::query(
            'UPDATE feedback SET status = ?, resolved_by = ?, resolved_at = ? WHERE id = ?',
            $resolved ? ['resolved', $by, date('Y-m-d H:i:s'), $id] : ['new', null, null, $id],
        );
    }
}
