<?php
declare(strict_types=1);

final class Auth
{
    public const ROLES = ['approver' => 'Approver', 'finance' => 'Finance', 'admin' => 'Admin'];

    /** What each right needs. Admin holds every right. Nobody acts on their own claim (checked where the claim is known). */
    private const RIGHTS = [
        'approve' => ['approver', 'admin'],
        'pay' => ['finance', 'admin'],
        'view_all' => ['approver', 'finance', 'admin'],
        'admin' => ['admin'],
    ];

    private static ?array $user = null;

    /** Re-reads the staff row and roles every request so changes in workCentral or People apply at once. */
    public static function user(): ?array
    {
        if (self::$user === null && isset($_SESSION['uid'])) {
            $u = Staff::find($_SESSION['uid']);
            if ($u && $u['active']) {
                $u['roles'] = self::rolesFor($u);
                self::$user = $u;
            } else {
                unset($_SESSION['uid']);
            }
        }
        return self::$user;
    }

    public static function rolesFor(array $staff): array
    {
        $roles = Database::query('SELECT role FROM user_roles WHERE staff_id = ?', [$staff['id']])->fetchAll(PDO::FETCH_COLUMN);
        if (self::isFixedAdmin($staff)) $roles[] = 'admin';
        return array_values(array_unique($roles));
    }

    public static function isFixedAdmin(array $staff): bool
    {
        $handles = array_filter(array_map(fn($h) => strtolower(trim($h)), explode(',', env('ADMIN_KC_HANDLES'))));
        return in_array(strtolower((string) $staff['kc_handle']), $handles, true);
    }

    public static function can(string $right, ?array $u = null): bool
    {
        $u ??= self::user();
        return $u !== null && (bool) array_intersect($u['roles'] ?? [], self::RIGHTS[$right] ?? []);
    }

    public static function login(array $staff): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = $staff['id'];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public static function require(): array
    {
        return self::user() ?? redirect('/login');
    }

    public static function requireRight(string $right): array
    {
        $u = self::require();
        if (!self::can($right, $u)) abort(403, 'Your account does not have access to this page.');
        return $u;
    }
}
