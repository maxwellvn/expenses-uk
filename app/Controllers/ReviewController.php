<?php
declare(strict_types=1);

/** Approver in-tray: claim list on the left, the open claim on the right. */
final class ReviewController
{
    private const TABS = ['pending' => 'To review', 'changes_requested' => 'Sent back', 'rejected' => 'Declined', 'all' => 'All'];

    public function index(): void
    {
        $user = Auth::requireRight('approve');
        $tab = isset(self::TABS[$_GET['status'] ?? '']) ? $_GET['status'] : 'pending';
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
        $claims = Claim::queue($tab === 'all' ? null : $tab, $q);

        $openId = (int) ($_GET['claim'] ?? 0);
        $explicit = $openId > 0;
        $open = $explicit ? Claim::find($openId) : (($claims[0] ?? null) ? Claim::find((int) $claims[0]['id']) : null);

        $stats = Claim::stats();
        view('review/index', [
            'title' => 'Review',
            'user' => $user,
            'tab' => $tab,
            'tabs' => self::TABS,
            'counts' => array_map(fn($s) => $s['n'], $stats['by']) + ['all' => array_sum(array_column($stats['by'], 'n'))],
            'waitingTotal' => $stats['by']['pending']['t'],
            'q' => $q,
            'claims' => $claims,
            'explicit' => $explicit,
            'open' => $open ? ClaimController::detail($open, $user) : null,
        ]);
    }

    public function decide(string $id): void
    {
        $user = Auth::requireRight('approve');
        csrf_check();
        $id = (int) $id;
        $decision = (string) ($_POST['decision'] ?? '');
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 2000);
        $back = (string) ($_POST['return'] ?? '') === 'review' ? "/review?claim=$id" : "/claims/$id";
        if (in_array($decision, ['reject', 'changes'], true) && $note === '') {
            flash($decision === 'reject' ? 'Say why the claim is declined so the claimant understands.' : 'Say what needs changing.', 'error');
            redirect($back);
        }
        $claim = Claim::find($id) ?? abort(404);
        if ($claim['staff_id'] === $user['id']) abort(403, 'You cannot decide your own claim. Another approver or the admin decides it.');
        if (!Claim::decide($id, $user['id'], $decision, $note)) {
            flash('This claim was already decided, or is waiting on the claimant.', 'error');
            redirect($back);
        }
        $next = null;
        foreach (Claim::queue('pending') as $c) {
            if ($c['staff_id'] !== $user['id']) { $next = (int) $c['id']; break; }
        }
        flash(['approve' => 'Approved', 'reject' => 'Declined', 'changes' => 'Sent back for changes'][$decision] . ': ' . claim_ref($id) . '.' . ($next ? ' Next claim is open.' : ' Nothing else to review.'));
        redirect($next ? "/review?claim=$next" : '/review');
    }
}
