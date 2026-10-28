<?php
declare(strict_types=1);

final class Claim
{
    public static function find(int $id): ?array
    {
        return Database::query('SELECT * FROM claims WHERE id = ?', [$id])->fetch() ?: null;
    }

    public static function items(int $id): array
    {
        return Database::query('SELECT * FROM claim_items WHERE claim_id = ? ORDER BY position, id', [$id])->fetchAll();
    }

    public static function files(int $id): array
    {
        return Database::query('SELECT * FROM claim_files WHERE claim_id = ? ORDER BY COALESCE(item_position, 65535), id', [$id])->fetchAll();
    }

    public static function file(int $fileId): ?array
    {
        return Database::query('SELECT * FROM claim_files WHERE id = ?', [$fileId])->fetch() ?: null;
    }

    public static function events(int $id): array
    {
        return Database::query('SELECT * FROM claim_events WHERE claim_id = ? ORDER BY created_at, id', [$id])->fetchAll();
    }

    public static function forStaff(string $staffId): array
    {
        return Database::query(
            'SELECT c.*, (SELECT f.id FROM claim_files f WHERE f.claim_id = c.id AND f.mime IN ("image/jpeg","image/png","image/webp") ORDER BY f.id LIMIT 1) AS thumb_id,
                    (SELECT COUNT(*) FROM claim_items i WHERE i.claim_id = c.id) AS item_count,
                    (SELECT COUNT(*) FROM claim_files f WHERE f.claim_id = c.id) AS file_count
             FROM claims c WHERE c.staff_id = ? ORDER BY c.submitted_at DESC',
            [$staffId],
        )->fetchAll();
    }

    /** Review queue, optionally filtered by status and claimant name. */
    public static function queue(?string $status, string $search = ''): array
    {
        $sql = 'SELECT c.*, u.name, u.title AS staff_title, u.avatar_url,
                       (SELECT f.id FROM claim_files f WHERE f.claim_id = c.id AND f.mime IN ("image/jpeg","image/png","image/webp") ORDER BY f.id LIMIT 1) AS thumb_id,
                       (SELECT COUNT(*) FROM claim_items i WHERE i.claim_id = c.id) AS item_count,
                       (SELECT COUNT(*) FROM claim_files f WHERE f.claim_id = c.id) AS file_count
                FROM claims c JOIN ' . Database::staffTable() . ' u ON u.id COLLATE utf8mb4_unicode_ci = c.staff_id WHERE 1=1';
        $params = [];
        if ($status) {
            $sql .= ' AND c.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $sql .= ' AND (u.name LIKE ? OR c.title LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        // Oldest waiting first so nobody gets forgotten; finished claims newest first.
        $sql .= in_array($status, ['pending', 'approved'], true) ? ' ORDER BY COALESCE(c.decided_at, c.submitted_at) ASC' : ' ORDER BY COALESCE(c.paid_at, c.decided_at, c.submitted_at) DESC';
        return Database::query($sql . ' LIMIT 300', $params)->fetchAll();
    }

    /** Counts and totals per status, plus approved and paid totals for this calendar year. */
    public static function stats(?string $staffId = null): array
    {
        $where = $staffId ? 'WHERE staff_id = ?' : '';
        $p = $staffId ? [$staffId] : [];
        $by = array_fill_keys(array_keys(STATUS), ['n' => 0, 't' => 0]);
        foreach (Database::query("SELECT status, COUNT(*) n, SUM(total_pence) t FROM claims $where GROUP BY status", $p)->fetchAll() as $r) {
            $by[$r['status']] = ['n' => (int) $r['n'], 't' => (int) $r['t']];
        }
        $paidYear = (int) Database::query(
            'SELECT COALESCE(SUM(total_pence),0) FROM claims WHERE status = "paid" AND YEAR(paid_at) = YEAR(CURDATE())' . ($staffId ? ' AND staff_id = ?' : ''),
            $p,
        )->fetchColumn();
        return ['by' => $by, 'paidYear' => $paidYear];
    }

    /** Finance marks an approved claim as paid. Never their own. */
    public static function pay(int $id, string $payerId, string $ref, string $paidOn): bool
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        $ok = Database::query(
            'UPDATE claims SET status = "paid", paid_by = ?, paid_at = ?, payment_ref = ? WHERE id = ? AND status = "approved" AND staff_id <> ?',
            [$payerId, $paidOn . ' ' . date('H:i:s'), $ref, $id, $payerId],
        )->rowCount() > 0;
        if ($ok) self::event($id, $payerId, 'paid', 'Reference: ' . $ref);
        $pdo->commit();
        return $ok;
    }

    public static function export(?string $status): array
    {
        $sql = 'SELECT c.id, u.name, c.title, c.status, c.total_pence, c.submitted_at, c.decided_at, c.paid_at, c.payment_ref,
                       i.spent_on, i.category, i.category_other, i.description, i.amount_pence
                FROM claims c JOIN ' . Database::staffTable() . ' u ON u.id COLLATE utf8mb4_unicode_ci = c.staff_id
                JOIN claim_items i ON i.claim_id = c.id';
        $p = [];
        if ($status) {
            $sql .= ' WHERE c.status = ?';
            $p[] = $status;
        }
        return Database::query($sql . ' ORDER BY c.id, i.position', $p)->fetchAll();
    }

    public static function create(string $staffId, string $title, string $notes, array $items, array $files): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::query(
                'INSERT INTO claims (staff_id, title, notes, total_pence) VALUES (?, ?, ?, ?)',
                [$staffId, $title, $notes ?: null, array_sum(array_column($items, 'amount_pence'))],
            );
            $id = (int) $pdo->lastInsertId();
            self::writeItems($id, $items);
            self::writeFiles($id, $files);
            self::event($id, $staffId, 'submitted');
            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Claimant edits after the reviewer asks for changes. Puts the claim back in review. */
    /** @param array<int,int> $keepPositions kept file id => line position it now belongs to */
    public static function resubmit(int $id, string $staffId, string $title, string $notes, array $items, array $files, array $removeFileIds, array $keepPositions, string $message): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $ok = Database::query(
                'UPDATE claims SET title = ?, notes = ?, total_pence = ?, status = "pending", submitted_at = NOW(), decided_by = NULL, decided_at = NULL
                 WHERE id = ? AND staff_id = ? AND status = "changes_requested"',
                [$title, $notes ?: null, array_sum(array_column($items, 'amount_pence')), $id, $staffId],
            )->rowCount();
            if (!$ok) throw new RuntimeException('Claim is not editable');
            Database::query('DELETE FROM claim_items WHERE claim_id = ?', [$id]);
            self::writeItems($id, $items);
            $removed = [];
            if ($removeFileIds) {
                $in = implode(',', array_fill(0, count($removeFileIds), '?'));
                $removed = Database::query("SELECT stored_name FROM claim_files WHERE claim_id = ? AND id IN ($in)", [$id, ...$removeFileIds])->fetchAll();
                Database::query("DELETE FROM claim_files WHERE claim_id = ? AND id IN ($in)", [$id, ...$removeFileIds]);
            }
            foreach ($keepPositions as $fileId => $pos) {
                Database::query('UPDATE claim_files SET item_position = ? WHERE id = ? AND claim_id = ?', [$pos, $fileId, $id]);
            }
            self::writeFiles($id, $files);
            self::event($id, $staffId, 'resubmitted', $message);
            $pdo->commit();
            return $removed;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Only a claim still waiting can be decided, so two tabs cannot decide twice. */
    public static function decide(int $id, string $approverId, string $decision, string $note): bool
    {
        $status = ['approve' => 'approved', 'reject' => 'rejected', 'changes' => 'changes_requested'][$decision] ?? null;
        if (!$status) return false;
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        $ok = Database::query(
            'UPDATE claims SET status = ?, decided_by = ?, decided_at = NOW() WHERE id = ? AND status = "pending" AND staff_id <> ?',
            [$status, $approverId, $id, $approverId],
        )->rowCount() > 0;
        if ($ok) self::event($id, $approverId, $status, $note);
        $pdo->commit();
        return $ok;
    }

    /** Records what happened and queues the KingsChat messages for it, in the same transaction. */
    public static function event(int $claimId, string $actorId, string $type, ?string $body = null): void
    {
        $body = ($body !== null && trim($body) !== '') ? trim($body) : null;
        Database::query('INSERT INTO claim_events (claim_id, actor_id, type, body) VALUES (?, ?, ?, ?)', [$claimId, $actorId, $type, $body]);
        Notify::claimEvent($claimId, (int) Database::pdo()->lastInsertId(), $actorId, $type, $body);
    }

    private static function writeItems(int $id, array $items): void
    {
        foreach (array_values($items) as $pos => $it) {
            Database::query(
                'INSERT INTO claim_items (claim_id, position, spent_on, category, category_other, description, amount_pence) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$id, $pos, $it['spent_on'], $it['category'], ($it['category_other'] ?? '') ?: null, $it['description'], $it['amount_pence']],
            );
        }
    }

    private static function writeFiles(int $id, array $files): void
    {
        foreach ($files as $f) {
            Database::query(
                'INSERT INTO claim_files (claim_id, item_position, original_name, stored_name, mime, size_bytes, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$id, $f['item_position'] ?? null, $f['original_name'], $f['stored_name'], $f['mime'], $f['size_bytes'], $f['uploaded_by']],
            );
        }
    }
}
