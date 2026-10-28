<?php
declare(strict_types=1);

/**
 * Sends KingsChat messages from the connected sender account (same API workCentral UK uses).
 * Tokens live in kc_agent; the access token is refreshed with the refresh token when it expires.
 */
final class KingsChat
{
    private const API = 'https://connect.kingsch.at/api';

    public static function agent(): array
    {
        return Database::query('SELECT * FROM kc_agent WHERE id = 1')->fetch() ?: [];
    }

    public static function connected(): bool
    {
        return !empty(self::agent()['refresh_token']);
    }

    public static function saveTokens(string $handle, string $access, ?string $refresh, string $by): void
    {
        Database::query(
            'UPDATE kc_agent SET handle = ?, access_token = ?, refresh_token = COALESCE(?, refresh_token), access_expires_at = NOW() + INTERVAL 9 MINUTE,
               connected_by = ?, connected_at = NOW(), pending_token = NULL, pending_expires = NULL WHERE id = 1',
            [strtolower($handle), $access, $refresh, $by],
        );
    }

    public static function disconnect(): void
    {
        Database::query('UPDATE kc_agent SET handle = NULL, access_token = NULL, refresh_token = NULL, access_expires_at = NULL WHERE id = 1');
    }

    /** @return array{ok:bool, error?:string, skip?:bool} */
    public static function send(string $handle, string $text): array
    {
        $agent = self::agent();
        if (empty($agent['refresh_token'])) return ['ok' => false, 'error' => 'sender account not connected', 'retry' => true];

        $token = self::accessToken($agent);
        if (!$token) return ['ok' => false, 'error' => 'could not refresh sender token', 'retry' => true];

        $userId = self::userId($handle, $token);
        if ($userId === null) return ['ok' => false, 'error' => "no KingsChat user @$handle", 'skip' => true];

        $body = json_encode(['message' => ['body' => ['text' => ['body' => $text]]]]);
        [$code] = self::http('POST', self::API . '/users/' . rawurlencode($userId) . '/new_message', $token, $body);
        if ($code === 401) {
            $token = self::accessToken(self::agent(), true);
            if ($token) [$code] = self::http('POST', self::API . '/users/' . rawurlencode($userId) . '/new_message', $token, $body);
        }
        return $code >= 200 && $code < 300 ? ['ok' => true] : ['ok' => false, 'error' => "KingsChat answered $code", 'retry' => $code === 429 || $code >= 500 || $code === 401];
    }

    private static function accessToken(array $agent, bool $force = false): ?string
    {
        if (!$force && !empty($agent['access_token']) && strtotime((string) $agent['access_expires_at']) > time()) return $agent['access_token'];
        $ch = curl_init(env('KC_TOKEN_URL', 'https://connect.kingsch.at/oauth2/token'));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['client_id' => env('KC_CLIENT_ID', 'com.kingschat'), 'grant_type' => 'refresh_token', 'refresh_token' => $agent['refresh_token']]),
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = is_string($res) ? json_decode($res, true) : null;
        $access = (string) ($data['access_token'] ?? $data['accessToken'] ?? '');
        if ($code !== 200 || $access === '') {
            error_log("KingsChat token refresh failed ($code)");
            return null;
        }
        // Providers that rotate refresh tokens invalidate the old one, so always store what comes back.
        $refresh = (string) ($data['refresh_token'] ?? $data['refreshToken'] ?? $agent['refresh_token']);
        $ttl = max(60, (int) ($data['expires_in'] ?? $data['expiresIn'] ?? 600) - 60);
        Database::query('UPDATE kc_agent SET access_token = ?, refresh_token = ?, access_expires_at = NOW() + INTERVAL ? SECOND WHERE id = 1', [$access, $refresh, $ttl]);
        return $access;
    }

    private static function userId(string $handle, string $token): ?string
    {
        $h = strtolower(ltrim(trim($handle), '@'));
        $cached = Database::query('SELECT kc_user_id FROM kc_users WHERE handle = ? AND resolved_at > NOW() - INTERVAL 30 DAY', [$h])->fetchColumn();
        if ($cached) return (string) $cached;
        [$code, $res] = self::http('GET', self::API . '/users?username=' . rawurlencode($h), $token);
        if ($code !== 200) return null;
        $data = json_decode((string) $res, true) ?: [];
        $candidates = array_merge([$data['user'] ?? null, $data['profile']['user'] ?? null], $data['users'] ?? [], $data['results'] ?? []);
        foreach ($candidates as $u) {
            if (!is_array($u)) continue;
            $id = (string) ($u['user_id'] ?? $u['id'] ?? '');
            $name = strtolower((string) ($u['username'] ?? ''));
            if ($id !== '' && ($name === '' || $name === $h)) {
                Database::query('REPLACE INTO kc_users (handle, kc_user_id, resolved_at) VALUES (?, ?, NOW())', [$h, $id]);
                return $id;
            }
        }
        return null;
    }

    /** @return array{0:int,1:string|false} */
    private static function http(string $method, string $url, string $token, ?string $json = null): array
    {
        $ch = curl_init($url);
        $headers = ['Accept: application/json', 'Authorization: Bearer ' . $token];
        if ($json !== null) $headers[] = 'Content-Type: application/json';
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers]);
        if ($json !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        $res = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$code, $res];
    }
}
