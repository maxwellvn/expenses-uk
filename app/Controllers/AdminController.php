<?php
declare(strict_types=1);

final class AdminController
{
    public function people(): void
    {
        $user = Auth::requireRight('admin');
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
        $staff = array_values(array_filter(Staff::active(), fn($s) => $q === '' || stripos($s['name'] . ' ' . $s['title'] . ' ' . $s['kc_handle'], $q) !== false));
        $roles = [];
        foreach (Database::query('SELECT staff_id, role FROM user_roles')->fetchAll() as $r) $roles[$r['staff_id']][] = $r['role'];
        foreach ($staff as &$s) {
            $s['roles'] = $roles[$s['id']] ?? [];
            $s['fixed_admin'] = Auth::isFixedAdmin($s);
        }
        unset($s);
        usort($staff, fn($a, $b) => [count($b['roles']) + (int) $b['fixed_admin'], $a['name']] <=> [count($a['roles']) + (int) $a['fixed_admin'], $b['name']]);
        view('admin/people', ['title' => 'People and roles', 'user' => $user, 'staff' => $staff, 'q' => $q]);
    }

    public function setRoles(string $staffId): void
    {
        $user = Auth::requireRight('admin');
        csrf_check();
        $staff = Staff::find($staffId) ?? abort(404);
        $want = array_values(array_intersect(array_keys(Auth::ROLES), (array) ($_POST['roles'] ?? [])));
        if ($staff['id'] === $user['id'] && !in_array('admin', $want, true) && !Auth::isFixedAdmin($staff)) {
            flash('You cannot remove your own admin role.', 'error');
            redirect('/admin/people');
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        Database::query('DELETE FROM user_roles WHERE staff_id = ?', [$staff['id']]);
        foreach ($want as $r) Database::query('INSERT INTO user_roles (staff_id, role, granted_by) VALUES (?, ?, ?)', [$staff['id'], $r, $user['id']]);
        $pdo->commit();
        flash('Roles saved for ' . $staff['name'] . '.');
        redirect('/admin/people' . (isset($_POST['q']) && $_POST['q'] !== '' ? '?q=' . rawurlencode((string) $_POST['q']) : ''));
    }

    public function feedback(): void
    {
        $user = Auth::requireRight('admin');
        $tab = ($_GET['status'] ?? '') === 'resolved' ? 'resolved' : 'new';
        $items = Feedback::list($tab);
        view('admin/feedback', [
            'title' => 'Help requests',
            'user' => $user,
            'tab' => $tab,
            'items' => $items,
            'newCount' => Feedback::countNew(),
            'people' => Staff::many(array_merge(array_column($items, 'staff_id'), array_column($items, 'resolved_by'))),
        ]);
    }

    public function resolve(string $id): void
    {
        $user = Auth::requireRight('admin');
        csrf_check();
        $done = ($_POST['done'] ?? '1') === '1';
        Feedback::resolve((int) $id, $user['id'], $done);
        flash($done ? 'Marked as dealt with.' : 'Moved back to open.');
        redirect('/admin/feedback' . ($done ? '' : '?status=resolved'));
    }
}
