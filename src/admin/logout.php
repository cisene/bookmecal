<?php
/**
 * src/admin/logout.php
 * Signs the current admin out (POST with CSRF token only, so a link elsewhere cannot do it).
 */

require_once __DIR__ . '/auth.php';

if (!is_post()) {
    header('Location: index.php');
    exit;
}
require_csrf();
log_audit_action($currentAdmin, 'LOGOUT', 'Signed out');
admin_sign_out('signedOut');
header('Location: login.php');
exit;
