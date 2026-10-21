<?php
/**
 * src/admin/auth.php
 * Shared bootstrap for admin pages: authentication, session, CSRF, audit log and rendering.
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Admin pages show customer data: never store them in browser or proxy caches
header('Cache-Control: no-store');

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'cookie_secure' => SecurityHeaders::isHttps(),
        'use_strict_mode' => true,
    ]);
}

// Fail closed if the web server did not authenticate the request (e.g. missing/ignored .htaccess)
$currentAdmin = (string)($_SERVER['PHP_AUTH_USER'] ?? $_SERVER['REMOTE_USER'] ?? $_SERVER['REDIRECT_REMOTE_USER'] ?? '');
if ($currentAdmin === '') {
    header('WWW-Authenticate: Basic realm="Book Me Calendar - Restricted Admin"');
    http_response_code(401);
    exit('Authentication required.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_token(): string {
    return $_SESSION['csrf_token'];
}

/**
 * Stops the request unless it is a POST carrying this session's CSRF token.
 */
function require_csrf(): void {
    $token = $_POST['csrf_token'] ?? null;
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit('CSRF verification failed.');
    }
}

function is_post(): bool {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
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
