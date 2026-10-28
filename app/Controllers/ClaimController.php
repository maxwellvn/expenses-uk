<?php
declare(strict_types=1);

final class ClaimController
{
    public function home(): void
    {
        $user = Auth::require();
        view('claims/index', [
            'title' => 'My claims',
            'user' => $user,
            'claims' => Claim::forStaff($user['id']),
            'stats' => Claim::stats($user['id']),
        ]);
    }

    public function create(): void
    {
        $user = Auth::require();
        view('claims/form', ['title' => 'New claim', 'user' => $user, 'claim' => null, 'items' => [], 'files' => []]);
    }

    public function store(): void
    {
        $user = Auth::require();
        csrf_check();
        [$title, $notes, $items, $errors] = self::input();
        if ($errors) self::back('/claims/new', $errors);

        $files = self::storeLineReceipts($items, $user['id']);
        try {
            $id = Claim::create($user['id'], $title, $notes, $items, $files);
        } catch (Throwable $e) {
            Receipt::discard($files);
            throw $e;
        }
        flash('Sent for review. You will see the decision here.');
        redirect("/claims/$id");
    }

    /** Saves each line's new uploads, tagged with the line they prove. */
    private static function storeLineReceipts(array $items, string $uploaderId): array
    {
        $files = [];
        try {
            foreach ($items as $pos => $it) {
                foreach (Receipt::store($it['uploads'], $uploaderId) as $row) $files[] = $row + ['item_position' => $pos];
            }
        } catch (Throwable $e) {
            Receipt::discard($files);
            throw $e;
        }
        return $files;
    }

    public function show(string $id): void
    {
        $user = Auth::require();
        $claim = self::authorised((int) $id, $user);
        view('claims/show', ['title' => claim_ref($claim['id'])] + self::detail($claim, $user));
    }

    /** Everything the claim document needs. Shared with the review in-tray. */
    public static function detail(array $claim, array $user): array
    {
        $events = Claim::events($claim['id']);
        $mine = $claim['staff_id'] === $user['id'];
        return [
            'user' => $user,
            'claim' => $claim,
            'items' => Claim::items($claim['id']),
            'files' => Claim::files($claim['id']),
            'events' => $events,
            'people' => Staff::many([$claim['staff_id'], $claim['decided_by'], $claim['paid_by'], ...array_column($events, 'actor_id')]),
            'mine' => $mine,
            'canDecide' => !$mine && $claim['status'] === 'pending' && Auth::can('approve', $user),
            'canPay' => !$mine && $claim['status'] === 'approved' && Auth::can('pay', $user),
        ];
    }

    public function edit(string $id): void
    {
        $user = Auth::require();
        $claim = self::editable((int) $id, $user);
        view('claims/form', [
            'title' => 'Update ' . claim_ref($claim['id']),
            'user' => $user,
            'claim' => $claim,
            'items' => Claim::items($claim['id']),
            'files' => Claim::files($claim['id']),
            'request' => self::lastRequest($claim['id']),
        ]);
    }

    public function update(string $id): void
    {
        $user = Auth::require();
        csrf_check();
        $claim = self::editable((int) $id, $user);
        $existing = array_map('intval', array_column(Claim::files($claim['id']), 'id'));
        [$title, $message, $items, $errors] = self::input($existing);
        $notes = (string) ($claim['notes'] ?? '');
        if ($errors) self::back("/claims/{$claim['id']}/edit", $errors);

        $keepPositions = [];
        foreach ($items as $pos => $it) foreach ($it['keep'] as $fid) $keepPositions[$fid] = $pos;
        $remove = array_values(array_diff($existing, array_keys($keepPositions)));

        $files = self::storeLineReceipts($items, $user['id']);
        try {
            $removed = Claim::resubmit($claim['id'], $user['id'], $title, $notes, $items, $files, $remove, $keepPositions, $message);
        } catch (Throwable $e) {
            Receipt::discard($files);
            throw $e;
        }
        Receipt::discard($removed);
        flash('Changes sent. The claim is back in review.');
        redirect("/claims/{$claim['id']}");
    }

    public function comment(string $id): void
    {
        $user = Auth::require();
        csrf_check();
        $claim = self::authorised((int) $id, $user);
        $body = mb_substr(trim((string) ($_POST['body'] ?? '')), 0, 2000);
        if ($body === '') {
            flash('Write a message before sending.', 'error');
        } else {
            Claim::event($claim['id'], $user['id'], 'comment', $body);
            flash('Message added.');
        }
        redirect("/claims/{$claim['id']}#activity");
    }

    public function file(string $id): void
    {
        $user = Auth::require();
        $file = Claim::file((int) $id) ?? abort(404);
        self::authorised((int) $file['claim_id'], $user);
        $path = Receipt::dir() . '/' . basename($file['stored_name']);
        if (!is_file($path)) abort(404, 'This file is missing from storage.');
        $inline = in_array($file['mime'], ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true);
        header('Content-Type: ' . $file['mime']);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace(['"', "\r", "\n"], '', $file['original_name']) . '"');
        header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
    }

    /** The claimant, or anyone whose role can see all claims. */
    private static function authorised(int $id, array $user): array
    {
        $claim = Claim::find($id) ?? abort(404, 'That claim does not exist.');
        if ($claim['staff_id'] !== $user['id'] && !Auth::can('view_all', $user)) abort(404, 'That claim does not exist.');
        return $claim;
    }

    private static function editable(int $id, array $user): array
    {
        $claim = Claim::find($id) ?? abort(404, 'That claim does not exist.');
        if ($claim['staff_id'] !== $user['id']) abort(404, 'That claim does not exist.');
        if ($claim['status'] !== 'changes_requested') {
            flash('A claim can only be edited after the reviewer asks for changes.', 'error');
            redirect("/claims/$id");
        }
        return $claim;
    }

    private static function lastRequest(int $id): ?string
    {
        foreach (array_reverse(Claim::events($id)) as $e) {
            if ($e['type'] === 'changes_requested') return $e['body'];
        }
        return null;
    }

    /**
     * Reads the expense lines, each with its own receipts. Form keys are kept so errors land on the right line.
     * @param int[] $existingFileIds receipts already on the claim (when editing)
     * @return array{0:string,1:string,2:array,3:array}
     */
    private static function input(array $existingFileIds = []): array
    {
        $errors = [];
        $notes = mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 2000);
        $uploadsByLine = Receipt::byLine();
        $keepByLine = (array) ($_POST['keep'] ?? []);

        $items = [];
        $today = date('Y-m-d');
        $n = 0;
        $fileCount = 0;
        foreach ((array) ($_POST['items'] ?? []) as $key => $row) {
            if (!is_array($row)) continue;
            $date = (string) ($row['spent_on'] ?? '');
            $cat = (string) ($row['category'] ?? '');
            $other = $cat === 'Other' ? mb_substr(trim((string) ($row['category_other'] ?? '')), 0, 80) : '';
            $desc = mb_substr(trim((string) ($row['description'] ?? '')), 0, 255);
            $amount = parse_pence((string) ($row['amount'] ?? ''));
            $uploads = $uploadsByLine[$key] ?? [];
            $keep = array_values(array_intersect($existingFileIds, array_map('intval', (array) ($keepByLine[$key] ?? []))));
            if ($date === '' && $desc === '' && trim((string) ($row['amount'] ?? '')) === '' && !$uploads && !$keep) continue;
            $n++;
            $fileCount += count($uploads) + count($keep);
            $e = "item$key";
            $d = DateTime::createFromFormat('!Y-m-d', $date);
            if (!$d || $d->format('Y-m-d') !== $date) $errors[$e] = "Line $n: pick the date you paid.";
            elseif ($date > $today) $errors[$e] = "Line $n: the date is in the future.";
            elseif (!in_array($cat, CATEGORIES, true)) $errors[$e] = "Line $n: choose a category.";
            elseif ($cat === 'Other' && $other === '') $errors[$e] = "Line $n: say what kind of expense \"Other\" is, for example \"Parking\".";
            elseif ($desc === '') $errors[$e] = "Line $n: say what it was for.";
            elseif (!$amount) $errors[$e] = "Line $n: enter the amount in pounds, for example 12.50.";
            elseif (!$uploads && !$keep) $errors[$e] = "Line $n: attach the receipt for this expense.";
            elseif (count($uploads) + count($keep) > Receipt::MAX_PER_LINE) $errors[$e] = "Line $n: attach up to " . Receipt::MAX_PER_LINE . ' receipts.';
            elseif ($uploads && ($msg = Receipt::validate($uploads))) $errors[$e] = "Line $n: $msg";
            $items[] = ['spent_on' => $date, 'category' => $cat, 'category_other' => $other, 'description' => $desc,
                'amount_pence' => (int) $amount, 'amount' => (string) ($row['amount'] ?? ''), 'uploads' => $uploads, 'keep' => $keep];
        }
        if (!$items) $errors['items'] = 'Add at least one expense.';
        if (count($items) > 50) $errors['items'] = 'Split this into claims of 50 lines or fewer.';
        if ($fileCount > Receipt::MAX_FILES) $errors['items'] = 'Attach up to ' . Receipt::MAX_FILES . ' receipts per claim. Split it into two claims.';
        // No title field: the claim is named after its first line, e.g. "Train to Leeds and 2 more".
        $title = $items ? mb_substr($items[0]['description'] . (count($items) > 1 ? ' and ' . (count($items) - 1) . ' more' : ''), 0, 160) : '';
        return [$title, $notes, $items, $errors];
    }

    private static function back(string $path, array $errors): never
    {
        $_SESSION['errors'] = $errors;
        $_SESSION['old'] = ['notes' => $_POST['notes'] ?? '', 'items' => (array) ($_POST['items'] ?? []), 'keep' => (array) ($_POST['keep'] ?? [])];
        flash('Some details need fixing. New photos were not saved, so attach them again on the lines shown.', 'error');
        redirect($path);
    }
}
