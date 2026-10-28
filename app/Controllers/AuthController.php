<?php
declare(strict_types=1);

final class AuthController
{
    private const ERRORS = [
        'kc' => 'KingsChat sign-in did not finish. Try again.',
        'nouser' => 'Your KingsChat account is not on the workCentral UK staff list. Use the help form below and we will sort it out.',
        'inactive' => 'Your workCentral UK account is switched off. Use the help form below if you think this is a mistake.',
    ];

    public function form(): void
    {
        if (Auth::user()) redirect('/');
        $error = self::ERRORS[$_GET['error'] ?? ''] ?? null;
        view('login', ['title' => 'Sign in', 'error' => $error, 'handle' => (string) ($_GET['kc'] ?? '')], 'bare');
    }

    public function kingschat(): void
    {
        $params = http_build_query([
            'client_id' => env('KC_CLIENT_ID', 'com.kingschat'),
            'post_redirect' => 'true',
            'redirect_uri' => env('APP_URL') . '/auth/callback',
            'response_type' => 'token',
            'scopes' => json_encode(['conference_calls']),
        ]);
        header('Location: ' . env('KC_AUTH_URL', 'https://accounts.kingsch.at/') . '?' . $params);
        exit;
    }

    /** KingsChat posts the token back, or puts it in the URL hash on GET. Only the token is trusted; the profile is fetched server-side. */
    public function callback(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            view('callback', ['title' => 'Signing in'], 'bare');
            return;
        }
        $token = (string) ($_POST['accessToken'] ?? $_POST['access_token'] ?? '');
        $username = $token !== '' ? self::kingsChatUsername($token) : null;
        if (!$username) redirect('/login?error=kc');

        $staff = Staff::findByHandle($username);
        if (!$staff) redirect('/login?error=nouser&kc=' . rawurlencode($username));
        if (!$staff['active']) redirect('/login?error=inactive');

        Auth::login($staff);
        redirect('/');
    }

    public function logout(): void
    {
        csrf_check();
        Auth::logout();
        redirect('/login');
    }

    public static function kingsChatUsername(string $token): ?string
    {
        $ch = curl_init(env('KC_PROFILE_URL', 'https://connect.kingsch.at/api/profile'));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200 || !is_string($body)) return null;
        $user = json_decode($body, true)['profile']['user'] ?? [];
        $name = $user['username'] ?? null;
        return is_string($name) && $name !== '' ? $name : null;
    }
}
