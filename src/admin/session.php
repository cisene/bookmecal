<?php
/**
 * src/admin/session.php
 * Shared bootstrap for every admin page, including the login page: session, CSRF,
 * signed-in user, audit log and rendering. Pages that need a signed-in user include auth.php.
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/classes/AdminAccounts.php';
require_once dirname(__DIR__) . '/classes/RateLimiter.php';
require_once dirname(__DIR__) . '/classes/SecurityLog.php';

/** Signed-in sessions end after this much inactivity, and in any case after ADMIN_MAX_SECONDS. */
const ADMIN_IDLE_SECONDS = 1800;
const ADMIN_MAX_SECONDS = 43200;

// Admin pages show customer data: never store them in browser or proxy caches
header('Cache-Control: no-store');

// Optional: refuse plain HTTP for the admin pages (settings.json admin_require_https)
if (!empty(ResourceProvider::getSettings()['admin_require_https']) && !SecurityHeaders::isHttps()) {
    http_response_code(403);
    exit('The admin pages are only available over HTTPS.');
}

if (session_status() === PHP_SESSION_NONE) {
    session_name('bookmecal_admin');
    session_start([
        'cookie_httponly' => true,
        // Lax, not Strict: OAuth callbacks (setup_google.php) arrive as cross-site navigations.
        // Every state change is a POST with a CSRF token, so Lax is enough.
        'cookie_samesite' => 'Lax',
        'cookie_secure' => SecurityHeaders::isHttps(),
        'cookie_path' => rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/x'), '/') . '/',
        'use_strict_mode' => true,
        'use_only_cookies' => true,
    ]);
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$currentAdmin = '';

function csrf_token(): string {
    return $_SESSION['csrf_token'];
}

/**
 * Stops the request unless it is a POST carrying this session's CSRF token.
 */
function require_csrf(): void {
    $token = $_POST['csrf_token'] ?? null;
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        SecurityLog::record('csrf_failed', ['page' => basename($_SERVER['SCRIPT_NAME'] ?? '')]);
        http_response_code(403);
        exit('CSRF verification failed.');
    }
}

function is_post(): bool {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Starts a signed-in session for $username: new session ID (no fixation) and new CSRF token.
 */
function admin_sign_in(string $username): void {
    session_regenerate_id(true);
    $_SESSION = [
        'csrf_token' => bin2hex(random_bytes(32)),
        'admin' => [
            'user' => $username,
            'fingerprint' => (new AdminAccounts())->fingerprint($username),
            'signed_in_at' => time(),
            'seen_at' => time(),
        ],
    ];
}

/**
 * Ends the session and leaves $notice ('expired', 'signedOut') for the login page.
 */
function admin_sign_out(?string $notice = null): void {
    session_regenerate_id(true);
    $_SESSION = ['csrf_token' => bin2hex(random_bytes(32))] + ($notice !== null ? ['login_notice' => $notice] : []);
}

/**
 * The signed-in username, or '' when nobody is signed in. Ends sessions that are idle too long,
 * too old, or whose account was removed or got a new password.
 */
function admin_session_user(): string {
    $admin = $_SESSION['admin'] ?? null;
    if (!is_array($admin) || !is_string($admin['user'] ?? null)) {
        return '';
    }
    $now = time();
    $expired = $now - (int)($admin['seen_at'] ?? 0) > ADMIN_IDLE_SECONDS || $now - (int)($admin['signed_in_at'] ?? 0) > ADMIN_MAX_SECONDS;
    $fingerprint = (new AdminAccounts())->fingerprint($admin['user']);
    if ($expired || $fingerprint === null || !hash_equals($fingerprint, (string)($admin['fingerprint'] ?? ''))) {
        admin_sign_out('expired');
        return '';
    }
    $_SESSION['admin']['seen_at'] = $now;
    return $admin['user'];
}

/**
 * Records an audit trail entry; keeps the newest 200 entries.
 */
function log_audit_action(string $username, string $action, string $details = ''): void {
    JsonStore::update(ResourceProvider::dataPath('audit.json'), function (array $logs) use ($username, $action, $details) {
        array_unshift($logs, [
            'timestamp' => date('Y-m-d H:i:s'),
            'user' => $username,
            'action' => $action,
            'details' => $details,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
        return array_slice($logs, 0, 200);
    });
}

/**
 * Renders an admin page template inside the admin layout.
 * $vars['title'] is a translation key; $vars['flash'] a list of ['type' => 'success'|'error', 'text' => ...].
 */
function render_admin(string $template, array $vars = []): void {
    global $currentAdmin;
    $localization = Localization::getInstance();
    View::render('admin/' . $template, $vars + [
        'title' => 'admin.appTitle',
        'flash' => [],
        'currentAdmin' => $currentAdmin,
        'currentPage' => basename($_SERVER['SCRIPT_NAME'] ?? ''),
        'csrfToken' => csrf_token(),
        'lang' => $localization->getCurrentLang(),
        'languages' => $localization->availableLanguages(),
    ], 'admin');
}

function flash_success(string $key, array $placeholders = []): array {
    return ['type' => 'success', 'text' => __t($key, $placeholders)];
}

function flash_error(string $message): array {
    return ['type' => 'error', 'text' => $message];
}
