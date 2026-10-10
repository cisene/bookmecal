<?php
// src/admin/auth.php - Shared bootstrap for session state, user tracking, and audit logging

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Guarantee $_SESSION is initialized as an array to prevent undefined superglobal warnings
if (!isset($_SESSION) || !is_array($_SESSION)) {
    $_SESSION = array();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Safely capture the authenticated Basic Auth user
$currentAdmin = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : 'Administrator';

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