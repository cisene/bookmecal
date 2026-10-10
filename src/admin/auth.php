<?php
// src/admin/auth.php - Included at the top of admin scripts
session_start();

// Load config for credentials
$configFile = __DIR__ . '/../data/config.json';
$config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
$adminUser = $config['app']['admin_user'] ?? 'admin';
$adminPass = $config['app']['admin_pass'] ?? 'secret123';

// Check if already authenticated via session
if (empty($_SESSION['admin_authenticated'])) {
    // Check if HTTP Basic Auth headers were sent by the browser
    if (!isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
        header('WWW-Authenticate: Basic realm="Book Me Calendar Admin"');
        header('HTTP/1.0 401 Unauthorized');
        exit('Authentication required.');
    } else {
        // Validate credentials against config
        if ($_SERVER['PHP_AUTH_USER'] === $adminUser && $_SERVER['PHP_AUTH_PW'] === $adminPass) {
            $_SESSION['admin_authenticated'] = true;
        } else {
            header('WWW-Authenticate: Basic realm="Book Me Calendar Admin"');
            header('HTTP/1.0 401 Unauthorized');
            exit('Invalid credentials.');
        }
    }
}

// Initialize CSRF token for subsequent form tabs
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}