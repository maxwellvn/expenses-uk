<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

foreach (file(ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if ($line[0] === '#' || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $_ENV[trim($k)] = trim($v);
}

function env(string $key, string $default = ''): string
{
    return $_ENV[$key] ?? $default;
}

date_default_timezone_set('Europe/London');
error_reporting(E_ALL);
ini_set('display_errors', env('APP_ENV') === 'local' ? '1' : '0');

spl_autoload_register(function (string $class): void {
    foreach (['', 'Models/', 'Controllers/'] as $dir) {
        $file = ROOT . "/app/$dir$class.php";
        if (preg_match('/^\w+$/', $class) && is_file($file)) {
            require $file;
            return;
        }
    }
});

$https = (parse_url(env('APP_URL'), PHP_URL_SCHEME) === 'https');
session_name('ukexp');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $https]);
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

const CATEGORIES = [
    'Travel', 'Fuel and mileage', 'Accommodation', 'Meals', 'Office supplies',
    'Postage and printing', 'Equipment', 'Ministry materials', 'Events and hospitality',
    'Phone and internet', 'Other',
];

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function base_path(): string
{
    return rtrim((string) parse_url(env('APP_URL'), PHP_URL_PATH), '/');
}

function url(string $path = '/'): string
{
    return base_path() . $path;
}

function asset(string $path): string
{
    $file = ROOT . '/assets/' . $path;
    return url('/assets/' . $path) . (is_file($file) ? '?v=' . filemtime($file) : '');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

function csrf_check(): void
{
    if (!hash_equals(csrf_token(), (string) ($_POST['_csrf'] ?? ''))) abort(403, 'This page expired. Go back, refresh and try again.');
}

function flash(?string $message = null, string $tone = 'ok'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = [$message, $tone];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['old'][$key] ?? $default;
}

function view(string $name, array $data = [], string $layout = 'layout'): void
{
    extract($data);
    ob_start();
    require ROOT . "/app/Views/$name.php";
    $content = ob_get_clean();
    require ROOT . "/app/Views/$layout.php";
    unset($_SESSION['old'], $_SESSION['errors']);
}

function abort(int $code, string $message = 'Page not found.'): never
{
    http_response_code($code);
    view('error', ['code' => $code, 'message' => $message, 'title' => 'Something went wrong'], Auth::user() ? 'layout' : 'bare');
    exit;
}

function money(int $pence): string
{
    return '£' . number_format($pence / 100, 2);
}

function parse_pence(string $input): ?int
{
    $v = str_replace([',', '£', ' '], '', trim($input));
    if (!preg_match('/^\d{1,7}(\.\d{1,2})?$/', $v)) return null;
    return (int) round(((float) $v) * 100);
}

function claim_ref(int $id): string
{
    return 'EXP-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
}

function when(?string $ts): string
{
    if (!$ts) return '';
    $t = strtotime($ts);
    $days = (int) floor((strtotime('today') - strtotime(date('Y-m-d', $t))) / 86400);
    return match (true) {
        $days === 0 => 'Today, ' . date('H:i', $t),
        $days === 1 => 'Yesterday, ' . date('H:i', $t),
        $days < 7 => date('l, H:i', $t),
        default => date('j M Y', $t),
    };
}

function first_name(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $titles = ['pastor', 'pst', 'brother', 'bro', 'sister', 'sis', 'deacon', 'dcn', 'deaconess', 'dcns', 'dr', 'mr', 'mrs', 'ms', 'miss', 'rev'];
    while (count($parts) > 1 && in_array(strtolower(rtrim($parts[0], '.')), $titles, true)) array_shift($parts);
    return $parts[0];
}

function initials(string $name): string
{
    $parts = array_values(array_filter(preg_split('/\s+/', first_name($name) . ' ' . substr($name, strrpos($name, ' ') ?: 0))));
    return strtoupper(substr($parts[0] ?? '?', 0, 1) . substr($parts[1] ?? '', 0, 1));
}

function avatar(array $person, string $size = 'md'): string
{
    $img = $person['avatar_url'] ?? '';
    if ($img && preg_match('#^https://#', $img)) {
        return '<img class="avatar avatar-' . $size . '" src="' . e($img) . '" alt="" loading="lazy" referrerpolicy="no-referrer">';
    }
    return '<span class="avatar avatar-' . $size . '" aria-hidden="true">' . e(initials($person['name'] ?? '?')) . '</span>';
}

const STATUS = [
    'pending' => ['In review', 'wait', 'clock'],
    'changes_requested' => ['Needs changes', 'ask', 'pen'],
    'approved' => ['Approved, awaiting payment', 'ok', 'check'],
    'paid' => ['Paid', 'ok', 'check'],
    'rejected' => ['Declined', 'no', 'ban'],
];

function status_chip(string $status): string
{
    [$label, $tone, $ico] = STATUS[$status] ?? [$status, 'wait', 'clock'];
    return '<span class="status st-' . $tone . '">' . icon($ico) . e($label) . '</span>';
}

header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data: blob: https:; form-action 'self' https://accounts.kingsch.at; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");

/** Inline icons, 1.75 stroke to match 14px medium text. */
function icon(string $name, int $size = 16): string
{
    $paths = [
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'upload' => '<path d="M12 16V4m0 0-4 4m4-4 4 4"/><path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>',
        'camera' => '<path d="M4 8a2 2 0 0 1 2-2h1.5l1.4-2h6.2l1.4 2H18a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z"/><circle cx="12" cy="12.5" r="3.5"/>',
        'back' => '<path d="m14 6-6 6 6 6"/>',
        'x' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'trash' => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'pen' => '<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/>',
        'ban' => '<circle cx="12" cy="12" r="8.5"/><path d="m6 6 12 12"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/>',
        'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2Z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
        'alert' => '<path d="M12 4 2.5 20h19Z"/><path d="M12 10v4M12 17.5v.01"/>',
        'pound' => '<path d="M16.5 6.5A4 4 0 0 0 9 8.5V18M6 18h12M6.5 12.5h7"/>',
        'help' => '<circle cx="12" cy="12" r="8.5"/><path d="M9.6 9.5a2.5 2.5 0 1 1 3.4 2.3c-.6.3-1 .8-1 1.5v.4M12 16.8v.01"/>',
        'users' => '<circle cx="9" cy="8.5" r="3.5"/><path d="M3 19c.5-3 3-5 6-5s5.5 2 6 5M16 5.5a3.2 3.2 0 0 1 0 6.2M18 14.3c1.6.6 2.7 2.2 3 4.7"/>',
        'inbox' => '<path d="M4 13.5 6.5 5h11l2.5 8.5V19H4Z"/><path d="M4 13.5h4.5l1 2h5l1-2H20"/>',
        'download' => '<path d="M12 4v12m0 0-4-4m4 4 4-4"/><path d="M4 18v1a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-1"/>',
        'list' => '<path d="M9 6h11M9 12h11M9 18h11"/><path d="M4.5 6h.01M4.5 12h.01M4.5 18h.01"/>',
        'logout' => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 16l-4-4 4-4M6 12h10"/>',
    ];
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

function bytes(int $n): string
{
    return $n >= 1048576 ? round($n / 1048576, 1) . ' MB' : max(1, (int) round($n / 1024)) . ' KB';
}

/** when() for use mid-sentence: "since today, 09:05" but "since Monday, 09:05". */
function when_lc(?string $ts): string
{
    $w = when($ts);
    return preg_match('/^(Today|Yesterday)/', $w) ? lcfirst($w) : $w;
}

/** Category label for an expense line: "Other: Parking" when the claimant said what it was. */
function category_label(array $item): string
{
    return $item['category'] === 'Other' && !empty($item['category_other']) ? 'Other: ' . $item['category_other'] : $item['category'];
}
