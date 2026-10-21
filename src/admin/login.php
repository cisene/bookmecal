<?php
/**
 * src/admin/login.php
 * Sign-in form for the admin pages. Accounts live in admin/.htpasswd (see users.php).
 * Failed attempts are counted per client and per username; too many lock sign-in for a while.
 */

require_once __DIR__ . '/session.php';

/** Lockout window in seconds and failed attempts allowed in it. */
const LOGIN_WINDOW = 900;
const LOGIN_MAX_FAILURES_PER_CLIENT = 5;
const LOGIN_MAX_FAILURES_PER_USER = 10;

if (admin_session_user() !== '') {
    header('Location: index.php');
    exit;
}

$error = null;
$username = '';

if (is_post()) {
    require_csrf();
    $username = mb_substr(request_string($_POST, 'username'), 0, 50);
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $limiter = new RateLimiter();
    $client = RateLimiter::clientKey();
    $userKey = strtolower($username);

    if ($limiter->count('login', $client, LOGIN_WINDOW) >= LOGIN_MAX_FAILURES_PER_CLIENT
        || $limiter->count('login_user', $userKey, LOGIN_WINDOW) >= LOGIN_MAX_FAILURES_PER_USER) {
        SecurityLog::record('login_locked', ['username' => $username]);
        header('Retry-After: ' . LOGIN_WINDOW);
        http_response_code(429);
        $error = 'locked';
    } elseif ((new AdminAccounts())->verify($username, $password)) {
        $limiter->clear('login', $client);
        $limiter->clear('login_user', $userKey);
        admin_sign_in($username);
        SecurityLog::record('login_ok', ['username' => $username]);
        log_audit_action($username, 'LOGIN', 'Signed in');
        header('Location: index.php');
        exit;
    } else {
        $limiter->record('login', $client, LOGIN_WINDOW);
        $limiter->record('login_user', $userKey, LOGIN_WINDOW);
        SecurityLog::record('login_failed', ['username' => $username]);
        http_response_code(401);
        $error = 'failed';
    }
}

$notice = $_SESSION['login_notice'] ?? null;
unset($_SESSION['login_notice']);

render_admin('login', [
    'title' => 'admin.login.title',
    'error' => $error,
    'notice' => in_array($notice, ['expired', 'signedOut'], true) ? $notice : null,
    'username' => $username,
    'lockMinutes' => intdiv(LOGIN_WINDOW, 60),
]);
