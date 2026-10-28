<?php
declare(strict_types=1);

/**
 * Who hears about what, on KingsChat. Messages are queued in the same request as the action
 * and delivered after the response is sent (see bootstrap), with retries from later requests and the cron.
 */
final class Notify
{
    private const MAX_ATTEMPTS = 6;
    public static bool $queued = false;

    /** Called for every claim event (Claim::event). */
    public static function claimEvent(int $claimId, int $eventId, string $actorId, string $type, ?string $body): void
    {
        $claim = Claim::find($claimId);
        if (!$claim) return;
        $people = Staff::many([$claim['staff_id'], $actorId]);
        $owner = $people[$claim['staff_id']]['name'] ?? 'A member of staff';
        $actor = $people[$actorId]['name'] ?? 'Someone';
        $link = env('APP_URL') . '/claims/' . $claimId;
        $sum = $claim['title'] . ' · ' . money((int) $claim['total_pence']);
        $note = $body ? "\n$actor: “" . self::clip($body) . '”' : '';
        $notOwner = [$claim['staff_id']];

        $to = [];
        $text = '';
        switch ($type) {
            case 'submitted':
            case 'resubmitted':
                $to = self::withRole($notOwner, 'approver', 'admin');
                $text = ($type === 'submitted' ? "New expense claim to review\n" : "Expense claim updated and sent back for review\n")
                    . "$owner · " . money((int) $claim['total_pence']) . "\n" . $claim['title'] . $note . "\n$link";
                break;
            case 'approved':
                self::queue([$claim['staff_id']], "Your expense claim was approved\n$sum\nApproved by $actor. Finance will pay it next.$note\n$link", "e$eventId");
                $to = self::withRole($notOwner, 'finance', 'admin');
                $text = "Expense claim approved and ready to pay\n$owner · " . money((int) $claim['total_pence']) . "\n" . $claim['title'] . "\n$link";
                break;
            case 'rejected':
                $to = [$claim['staff_id']];
                $text = "Your expense claim was declined\n$sum$note\n$link";
                break;
            case 'changes_requested':
                $to = [$claim['staff_id']];
                $text = "Changes needed on your expense claim\n$sum$note\nUpdate it here: $link/edit";
                break;
            case 'paid':
                $to = [$claim['staff_id']];
                $text = 'Your expense claim has been paid' . "\n" . money((int) $claim['total_pence']) . ' · ' . ($body ?: '') . "\n" . $claim['title'] . "\n$link";
                break;
            case 'comment':
                if ($actorId === $claim['staff_id']) {
                    // Claimant wrote: tell whoever has handled the claim, or the approvers if nobody has yet.
                    $handlers = Database::query('SELECT DISTINCT actor_id FROM claim_events WHERE claim_id = ? AND actor_id <> ?', [$claimId, $actorId])->fetchAll(PDO::FETCH_COLUMN);
                    $to = $handlers ?: self::withRole($notOwner, 'approver', 'admin');
                    $text = "New message on $owner's expense claim\n$sum$note\n$link";
                } else {
                    $to = [$claim['staff_id']];
                    $text = "New message on your expense claim\n$sum$note\n$link";
                }
                break;
        }
        $to = array_values(array_diff(array_unique($to), [$actorId]));
        if ($to && $text !== '') self::queue($to, $text, "e$eventId" . ($type === 'approved' ? 'f' : ''));
    }

    public static function helpRequest(int $feedbackId, string $name, string $handle, string $topic, string $message): void
    {
        $who = $name . ($handle !== '' ? " (@$handle)" : '');
        self::queue(self::withRole([], 'admin'), "New help request on Expense claims\n$who · $topic\n“" . self::clip($message) . "”\n" . env('APP_URL') . '/admin/feedback', "h$feedbackId");
    }

    /**
     * Staff ids holding a role, minus $exclude (the claimant: nobody acts on their own claim).
     * Falls back to the next role when nobody is left. Fixed admins count as admin.
     */
    public static function withRole(array $exclude, string ...$roles): array
    {
        foreach ($roles as $role) {
            $ids = Database::query('SELECT staff_id FROM user_roles WHERE role = ?', [$role])->fetchAll(PDO::FETCH_COLUMN);
            if ($role === 'admin') {
                foreach (array_filter(array_map('trim', explode(',', env('ADMIN_KC_HANDLES')))) as $h) {
                    $s = Staff::findByHandle($h);
                    if ($s) $ids[] = $s['id'];
                }
            }
            $ids = array_values(array_diff(array_unique($ids), $exclude));
            if ($ids) return $ids;
        }
        return [];
    }

    public static function queue(array $staffIds, string $text, string $key): void
    {
        foreach (Staff::many($staffIds) as $s) {
            $h = strtolower(trim((string) $s['kc_handle']));
            if (!$s['active'] || !preg_match('/^[a-z0-9._-]{2,64}$/', $h)) continue; // no usable handle, e.g. "awaiting kc handle"
            Database::query(
                'INSERT IGNORE INTO notifications (dedupe_key, staff_id, kc_handle, body) VALUES (?, ?, ?, ?)',
                [mb_substr("$key:{$s['id']}", 0, 120), $s['id'], $h, $text],
            );
            self::$queued = true;
        }
    }

    /** Delivers due messages. One runner at a time, so token refreshes never race. */
    public static function drain(int $limit = 20): array
    {
        $got = (int) Database::query('SELECT GET_LOCK(?, 0)', [env('DB_NAME') . ':kc_notify'])->fetchColumn();
        if (!$got) return ['busy' => true];
        $done = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        try {
            if (!KingsChat::connected()) return $done + ['waiting' => 'sender not connected'];
            $rows = Database::query('SELECT * FROM notifications WHERE status = "pending" AND next_try_at <= NOW() ORDER BY id LIMIT ' . max(1, min(50, $limit)))->fetchAll();
            foreach ($rows as $n) {
                $r = KingsChat::send($n['kc_handle'], $n['body']);
                $tries = (int) $n['attempts'] + 1;
                if ($r['ok']) {
                    Database::query('UPDATE notifications SET status = "sent", attempts = ?, sent_at = NOW(), last_error = NULL WHERE id = ?', [$tries, $n['id']]);
                    $done['sent']++;
                } elseif (!empty($r['skip']) || empty($r['retry']) || $tries >= self::MAX_ATTEMPTS) {
                    Database::query('UPDATE notifications SET status = ?, attempts = ?, last_error = ? WHERE id = ?', [!empty($r['skip']) ? 'skipped' : 'failed', $tries, mb_substr($r['error'] ?? '', 0, 255), $n['id']]);
                    $done[!empty($r['skip']) ? 'skipped' : 'failed']++;
                } else {
                    // Back off: 1, 4, 9, 16, 25 minutes.
                    Database::query('UPDATE notifications SET attempts = ?, last_error = ?, next_try_at = NOW() + INTERVAL ? MINUTE WHERE id = ?', [$tries, mb_substr($r['error'] ?? '', 0, 255), $tries * $tries, $n['id']]);
                    $done['failed']++;
                }
            }
        } finally {
            Database::query('SELECT RELEASE_LOCK(?)', [env('DB_NAME') . ':kc_notify']);
        }
        return $done;
    }

    private static function clip(string $s): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));
        return mb_strlen($s) > 400 ? mb_substr($s, 0, 397) . '…' : $s;
    }
}
