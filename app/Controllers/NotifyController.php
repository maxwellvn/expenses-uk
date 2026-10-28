<?php
declare(strict_types=1);

/** Admin page for KingsChat notifications, the sender-account connection, and the cron drain. */
final class NotifyController
{
    public function index(): void
    {
        $user = Auth::requireRight('admin');
        $agent = KingsChat::agent();
        view('admin/notifications', [
            'title' => 'Notifications',
            'user' => $user,
            'agent' => $agent,
            'connectedBy' => $agent['connected_by'] ? Staff::find($agent['connected_by']) : null,
            'counts' => array_column(Database::query('SELECT status, COUNT(*) n FROM notifications GROUP BY status')->fetchAll(), 'n', 'status'),
            'recent' => Database::query('SELECT n.*, s.name FROM notifications n LEFT JOIN ' . Database::staffTable() . ' s ON s.id COLLATE utf8mb4_unicode_ci = n.staff_id ORDER BY n.id DESC LIMIT 40')->fetchAll(),
        ]);
    }

    /** Starts KingsChat sign-in for the sender account. The one-time token in the return address proves it was this admin. */
    public function connect(): void
    {
        Auth::requireRight('admin');
        csrf_check();
        $token = bin2hex(random_bytes(32));
        Database::query('UPDATE kc_agent SET pending_token = ?, pending_expires = NOW() + INTERVAL 15 MINUTE WHERE id = 1', [$token]);
        $_SESSION['kc_connect_by'] = Auth::user()['id'];
        header('Location: ' . env('KC_AUTH_URL', 'https://accounts.kingsch.at/') . '?' . http_build_query([
            'client_id' => env('KC_CLIENT_ID', 'com.kingschat'),
            'post_redirect' => 'true',
            'redirect_uri' => env('APP_URL') . '/auth/agent/' . $token,
            'response_type' => 'token',
            'scopes' => json_encode(['conference_calls']),
        ]));
        exit;
    }

    /** KingsChat returns here after the sender account signs in. */
    public function callback(string $token): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            view('callback', ['title' => 'Connecting'], 'bare');
            return;
        }
        $row = Database::query('SELECT pending_token FROM kc_agent WHERE id = 1 AND pending_expires > NOW()')->fetch();
        if (!$row || !$row['pending_token'] || !hash_equals($row['pending_token'], $token)) abort(403, 'This connection link has expired. Start again from Admin, Notifications.');
        $access = (string) ($_POST['accessToken'] ?? $_POST['access_token'] ?? '');
        $refresh = (string) ($_POST['refreshToken'] ?? $_POST['refresh_token'] ?? '');
        $handle = $access !== '' ? AuthController::kingsChatUsername($access) : null;
        if (!$handle || $refresh === '') {
            flash('KingsChat did not return what is needed to send messages. Try connecting again.', 'error');
            redirect('/admin/notifications');
        }
        KingsChat::saveTokens($handle, $access, $refresh, (string) ($_SESSION['kc_connect_by'] ?? ''));
        flash("Connected. Notifications will be sent from @$handle.");
        redirect('/admin/notifications');
    }

    public function disconnect(): void
    {
        Auth::requireRight('admin');
        csrf_check();
        KingsChat::disconnect();
        flash('Sender account disconnected. Messages will wait in the queue until one is connected.');
        redirect('/admin/notifications');
    }

    /** Sends the admin a test message right away and reports what happened. */
    public function test(): void
    {
        $user = Auth::requireRight('admin');
        csrf_check();
        $h = (string) $user['kc_handle'];
        $r = $h !== '' ? KingsChat::send($h, "Test from Expense claims\nKingsChat notifications are working.\n" . env('APP_URL')) : ['ok' => false, 'error' => 'your profile has no KingsChat handle'];
        flash($r['ok'] ? "Test message sent to @$h. Check KingsChat." : 'Test failed: ' . ($r['error'] ?? 'unknown error'), $r['ok'] ? 'ok' : 'error');
        redirect('/admin/notifications');
    }

    public function retry(): void
    {
        Auth::requireRight('admin');
        csrf_check();
        Database::query('UPDATE notifications SET status = "pending", attempts = 0, next_try_at = NOW() WHERE status = "failed"');
        $r = Notify::drain(50);
        flash('Retried: ' . ($r['sent'] ?? 0) . ' sent, ' . ($r['failed'] ?? 0) . ' still failing.');
        redirect('/admin/notifications');
    }

    /** POST /cron/notify with the sync secret: delivery backstop run from the workCentral server every 15 minutes. */
    public function cron(): void
    {
        header('Content-Type: application/json');
        $given = preg_replace('/^Bearer\s+/i', '', (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
        $secret = env('SYNC_SECRET');
        if (strlen($secret) < 32 || !hash_equals($secret, $given)) {
            http_response_code(401);
            exit('{"ok":false}');
        }
        echo json_encode(['ok' => true] + Notify::drain(50));
        exit;
    }
}
