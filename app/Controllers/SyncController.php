<?php
declare(strict_types=1);

/**
 * Receives the staff list from the workCentral UK server (see deploy/push-staff.sh).
 * The full list is sent each time: people missing from it are switched off, never deleted.
 */
final class SyncController
{
    private const MAX_BYTES = 2 * 1024 * 1024;

    public function staff(): void
    {
        header('Content-Type: application/json');
        $secret = env('SYNC_SECRET');
        $given = preg_replace('/^Bearer\s+/i', '', (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
        if (strlen($secret) < 32 || !hash_equals($secret, $given)) $this->fail(401, 'unauthorised');
        if (env('STAFF_DB') !== '') $this->fail(409, 'STAFF_DB is set; this install reads workCentral directly');
        if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > self::MAX_BYTES) $this->fail(413, 'too large');

        $rows = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($rows) || !$rows || !array_is_list($rows)) $this->fail(400, 'expected a non-empty JSON array');

        $clean = [];
        foreach ($rows as $r) {
            $id = is_array($r) ? (string) ($r['id'] ?? '') : '';
            if (!preg_match('/^[\w.-]{1,64}$/', $id) || trim((string) ($r['name'] ?? '')) === '') $this->fail(400, 'bad row');
            $clean[$id] = [
                $id,
                mb_substr(trim((string) $r['name']), 0, 160),
                mb_substr((string) ($r['role'] ?? 'staff'), 0, 32),
                mb_substr((string) ($r['title'] ?? ''), 0, 160),
                ($r['department'] ?? null) !== null ? mb_substr((string) $r['department'], 0, 160) : null,
                ($r['avatar_url'] ?? null) !== null ? (string) $r['avatar_url'] : null,
                ($r['kc_handle'] ?? null) !== null ? mb_substr(strtolower(trim((string) $r['kc_handle'])), 0, 160) : null,
                ($r['email'] ?? null) !== null ? mb_substr((string) $r['email'], 0, 255) : null,
                !empty($r['active']) ? 1 : 0,
            ];
        }

        $now = date('Y-m-d H:i:s');
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'INSERT INTO staff (id, name, role, title, department, avatar_url, kc_handle, email, active, synced_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), role = VALUES(role), title = VALUES(title), department = VALUES(department),
               avatar_url = VALUES(avatar_url), kc_handle = VALUES(kc_handle), email = VALUES(email), active = VALUES(active), synced_at = VALUES(synced_at)'
        );
        foreach ($clean as $row) $stmt->execute([...$row, $now]);
        $off = $pdo->prepare('UPDATE staff SET active = 0 WHERE synced_at < ?');
        $off->execute([$now]);
        $pdo->commit();

        echo json_encode(['ok' => true, 'received' => count($clean), 'deactivated' => $off->rowCount()]);
        exit;
    }

    private function fail(int $code, string $msg): never
    {
        http_response_code($code);
        echo json_encode(['ok' => false, 'error' => $msg]);
        exit;
    }
}
