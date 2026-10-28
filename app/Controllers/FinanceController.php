<?php
declare(strict_types=1);

final class FinanceController
{
    private const STATUS_FILTER = ['all' => 'All', 'pending' => 'In review', 'changes_requested' => 'Needs changes', 'approved' => 'To pay', 'paid' => 'Paid', 'rejected' => 'Declined'];

    /** Approved claims waiting for a bank transfer, oldest approval first. */
    public function payments(): void
    {
        $user = Auth::requireRight('pay');
        $tab = ($_GET['status'] ?? '') === 'paid' ? 'paid' : 'approved';
        $stats = Claim::stats();
        view('finance/payments', [
            'title' => 'Payments',
            'user' => $user,
            'tab' => $tab,
            'claims' => Claim::queue($tab),
            'toPay' => $stats['by']['approved'],
            'paidYear' => $stats['paidYear'],
            'paidCount' => $stats['by']['paid']['n'],
        ]);
    }

    public function pay(string $id): void
    {
        $user = Auth::requireRight('pay');
        csrf_check();
        $id = (int) $id;
        $ref = mb_substr(trim((string) ($_POST['payment_ref'] ?? '')), 0, 80);
        $date = (string) ($_POST['paid_on'] ?? '');
        $d = DateTime::createFromFormat('!Y-m-d', $date);
        $back = (string) ($_POST['return'] ?? '') === 'payments' ? '/payments' : "/claims/$id";
        if ($ref === '') {
            flash('Enter the bank transfer reference so the claimant can match the payment.', 'error');
            redirect($back);
        }
        if (!$d || $d->format('Y-m-d') !== $date || $date > date('Y-m-d')) {
            flash('Enter the date the money was sent, not a future date.', 'error');
            redirect($back);
        }
        $claim = Claim::find($id) ?? abort(404);
        if ($claim['staff_id'] === $user['id']) abort(403, 'You cannot pay your own claim. Another finance user or the admin pays it.');
        flash(Claim::pay($id, $user['id'], $ref, $date) ? claim_ref($id) . ' marked as paid.' : 'This claim is not waiting for payment.', Claim::find($id)['status'] === 'paid' ? 'ok' : 'error');
        redirect($back);
    }

    public function all(): void
    {
        $user = Auth::requireRight('view_all');
        $status = isset(self::STATUS_FILTER[$_GET['status'] ?? '']) ? $_GET['status'] : 'all';
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
        view('finance/all', [
            'title' => 'All claims',
            'user' => $user,
            'status' => $status,
            'filters' => self::STATUS_FILTER,
            'q' => $q,
            'claims' => Claim::queue($status === 'all' ? null : $status, $q),
        ]);
    }

    /** One row per expense line, so it pastes straight into a spreadsheet. */
    public function export(): void
    {
        Auth::requireRight('view_all');
        $status = isset(self::STATUS_FILTER[$_GET['status'] ?? '']) && $_GET['status'] !== 'all' ? $_GET['status'] : null;
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="expense-claims-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Claim', 'Claimant', 'Claim title', 'Status', 'Claim total (GBP)', 'Sent', 'Decided', 'Paid', 'Payment ref', 'Item date', 'Category', 'Item', 'Item amount (GBP)']);
        foreach (Claim::export($status) as $r) {
            $row = [claim_ref((int) $r['id']), $r['name'], $r['title'], STATUS[$r['status']][0], number_format($r['total_pence'] / 100, 2, '.', ''),
                $r['submitted_at'], $r['decided_at'], $r['paid_at'], $r['payment_ref'], $r['spent_on'], $r['category'] === 'Other' && $r['category_other'] ? 'Other: ' . $r['category_other'] : $r['category'], $r['description'], number_format($r['amount_pence'] / 100, 2, '.', '')];
            // Stop spreadsheet formula injection from free-text fields.
            fputcsv($out, array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v, $row));
        }
        exit;
    }
}
