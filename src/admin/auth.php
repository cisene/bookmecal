<?php
// src/admin/auth.php - Shared bootstrap for session state, user tracking, and audit logging

if (session_status() === PHP_SESSION_NONE) {
    @session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'cookie_secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
        'use_strict_mode' => true,
    ]);
}

// Guarantee $_SESSION is initialized as an array to prevent undefined superglobal warnings
if (!isset($_SESSION) || !is_array($_SESSION)) {
    $_SESSION = array();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Capture the authenticated Basic Auth user; fail closed if the web server did not authenticate the request
// (e.g. missing/ignored .htaccess), instead of silently granting admin access.
$currentAdmin = '';
if (!empty($_SERVER['PHP_AUTH_USER'])) {
    $currentAdmin = $_SERVER['PHP_AUTH_USER'];
} elseif (!empty($_SERVER['REMOTE_USER'])) {
    $currentAdmin = $_SERVER['REMOTE_USER'];
} elseif (!empty($_SERVER['REDIRECT_REMOTE_USER'])) {
    $currentAdmin = $_SERVER['REDIRECT_REMOTE_USER'];
}

if ($currentAdmin === '') {
    header('WWW-Authenticate: Basic realm="Book Me Calendar - Restricted Admin"');
    http_response_code(401);
    exit('Authentication required.');
}

// Reject non-string CSRF tokens (e.g. csrf_token[]=x) before hash_equals() sees them
if (isset($_POST['csrf_token']) && !is_string($_POST['csrf_token'])) {
    http_response_code(403);
    exit('CSRF verification failed.');
}

/**
 * Record an audit trail entry for multi-user actions.
 */
function log_audit_action($username, $action, $details = '') {
    $auditFile = dirname(__DIR__) . '/data/audit.json';
    $auditDir = dirname($auditFile);
    if (!is_dir($auditDir)) {
        @mkdir($auditDir, 0775, true);
    }
    
    $logs = array();
    if (file_exists($auditFile)) {
        $decoded = json_decode(file_get_contents($auditFile), true);
        if (is_array($decoded)) {
            $logs = $decoded;
        }
    }
    
    // Prepend new log entry
    array_unshift($logs, array(
        'timestamp' => date('Y-m-d H:i:s'),
        'user' => $username,
        'action' => $action,
        'details' => $details,
        'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown'
    ));
    
    // Retain a maximum of 200 entries to prevent file bloat
    if (count($logs) > 200) {
        $logs = array_slice($logs, 0, 200);
    }
    
    $fp = @fopen($auditFile, 'c+b');
    if ($fp && @flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}