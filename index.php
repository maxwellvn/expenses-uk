<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$routes = [
    ['GET', '#^/$#', [ClaimController::class, 'home']],
    ['GET', '#^/login$#', [AuthController::class, 'form']],
    ['GET', '#^/auth/kingschat$#', [AuthController::class, 'kingschat']],
    ['GET|POST', '#^/auth/callback$#', [AuthController::class, 'callback']],
    ['POST', '#^/logout$#', [AuthController::class, 'logout']],
    ['GET', '#^/claims/new$#', [ClaimController::class, 'create']],
    ['POST', '#^/claims$#', [ClaimController::class, 'store']],
    ['GET', '#^/claims/(\d+)$#', [ClaimController::class, 'show']],
    ['GET', '#^/claims/(\d+)/edit$#', [ClaimController::class, 'edit']],
    ['POST', '#^/claims/(\d+)$#', [ClaimController::class, 'update']],
    ['POST', '#^/claims/(\d+)/comment$#', [ClaimController::class, 'comment']],
    ['GET', '#^/files/(\d+)$#', [ClaimController::class, 'file']],
    ['GET', '#^/review$#', [ReviewController::class, 'index']],
    ['GET', '#^/payments$#', [FinanceController::class, 'payments']],
    ['POST', '#^/claims/(\d+)/paid$#', [FinanceController::class, 'pay']],
    ['GET', '#^/all$#', [FinanceController::class, 'all']],
    ['GET', '#^/all/export$#', [FinanceController::class, 'export']],
    ['GET', '#^/admin$#', [AdminController::class, 'people']],
    ['GET', '#^/admin/people$#', [AdminController::class, 'people']],
    ['POST', '#^/admin/people/([\w.-]+)$#', [AdminController::class, 'setRoles']],
    ['GET', '#^/admin/feedback$#', [AdminController::class, 'feedback']],
    ['POST', '#^/admin/feedback/(\d+)$#', [AdminController::class, 'resolve']],
    ['POST', '#^/sync/staff$#', [SyncController::class, 'staff']],
    ['POST', '#^/cron/notify$#', [NotifyController::class, 'cron']],
    ['GET|POST', '#^/auth/agent/([a-f0-9]{64})$#', [NotifyController::class, 'callback']],
    ['GET', '#^/admin/notifications$#', [NotifyController::class, 'index']],
    ['POST', '#^/admin/notifications/connect$#', [NotifyController::class, 'connect']],
    ['POST', '#^/admin/notifications/disconnect$#', [NotifyController::class, 'disconnect']],
    ['POST', '#^/admin/notifications/test$#', [NotifyController::class, 'test']],
    ['POST', '#^/admin/notifications/retry$#', [NotifyController::class, 'retry']],
    ['GET', '#^/help$#', [HelpController::class, 'form']],
    ['POST', '#^/help$#', [HelpController::class, 'send']],
    ['POST', '#^/claims/(\d+)/decision$#', [ReviewController::class, 'decide']],
];

// Only answer on the configured host (Hostinger nests subdomain folders inside the parent site's folder).
$appHost = (string) parse_url(env('APP_URL'), PHP_URL_HOST);
if (env('APP_ENV') === 'production' && strcasecmp($appHost, (string) ($_SERVER['HTTP_HOST'] ?? '')) !== 0) {
    http_response_code(404);
    exit;
}

$path = '/' . trim(substr((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), strlen(base_path())), '/');
$method = $_SERVER['REQUEST_METHOD'];

// A request body bigger than post_max_size arrives empty; say so instead of a confusing CSRF error.
if ($method === 'POST' && !$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'form')) {
    flash('The upload was too large. Attach fewer or smaller files (10 MB each).', 'error');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? url('/')));
    exit;
}

try {
    foreach ($routes as [$verbs, $pattern, [$class, $action]]) {
        if (preg_match($pattern, $path, $m) && in_array($method, explode('|', $verbs), true)) {
            (new $class())->$action(...array_slice($m, 1));
            exit;
        }
    }
    abort(404);
} catch (Throwable $e) {
    error_log((string) $e);
    if (env('APP_ENV') === 'local') throw $e;
    abort(500, 'Something broke on our side. Try again in a minute.');
}
