<?php
/**
 * src/admin/auth.php
 * Included first by every admin page that needs a signed-in user: sends everyone else to login.php.
 */

require_once __DIR__ . '/session.php';

$currentAdmin = admin_session_user();
if ($currentAdmin === '') {
    header('Location: login.php');
    exit;
}
