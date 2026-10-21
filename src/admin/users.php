<?php
/**
 * src/admin/users.php
 * Admin accounts (admin/.htpasswd), the audit log and the security event log.
 */

require_once __DIR__ . '/auth.php';

$accounts = new AdminAccounts();
$flash = [];

if (is_post()) {
    require_csrf();
    $action = request_string($_POST, 'action');
    $username = request_string($_POST, 'username');

    if ($action === 'save_user') {
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $problem = AdminAccounts::passwordProblem($password);
        if (!preg_match(AdminAccounts::USERNAME_PATTERN, $username)) {
            $flash[] = flash_error('Invalid username format (3-20 letters, digits or underscores).');
        } elseif ($problem !== null) {
            $flash[] = flash_error($problem);
        } elseif ($accounts->setPassword($username, $password)) {
            // A new password ends other sessions of that account; keep the current one signed in
            if ($username === $currentAdmin) {
                $_SESSION['admin']['fingerprint'] = $accounts->fingerprint($username);
            }
            log_audit_action($currentAdmin, 'USER_MODIFY', 'Added or updated credentials for user: ' . $username);
            header('Location: users.php?done=saved');
            exit;
        } else {
            $flash[] = flash_error('Failed to write the .htpasswd file. Check file permissions in src/admin/.');
        }
    } elseif ($action === 'delete_user') {
        if ($username === $currentAdmin) {
            $flash[] = flash_error('You cannot delete your own account while logged in.');
        } elseif ($accounts->exists($username)) {
            if ($accounts->delete($username)) {
                log_audit_action($currentAdmin, 'USER_DELETE', 'Removed user: ' . $username);
                header('Location: users.php?done=deleted');
                exit;
            }
            $flash[] = flash_error('Failed to write the .htpasswd file. Check file permissions in src/admin/.');
        }
    }
}

// Extra fields of a security event (bucket, username, page) shown as one text column
$securityEvents = array_map(function (array $event) {
    $details = array_diff_key($event, array_flip(['time', 'event', 'ip', 'client']));
    return $event + ['details' => implode(', ', array_map(function ($key, $value) {
        return $key . ': ' . $value;
    }, array_keys($details), $details))];
}, SecurityLog::recent(100));

$done = request_string($_GET, 'done');
if (in_array($done, ['saved', 'deleted'], true)) {
    $flash[] = flash_success('admin.users.done.' . $done);
}

render_admin('users', [
    'title' => 'admin.users.title',
    'flash' => $flash,
    'users' => array_keys($accounts->all()),
    'passwordMinLength' => AdminAccounts::PASSWORD_MIN_LENGTH,
    'auditLogs' => JsonStore::read(ResourceProvider::dataPath('audit.json')) ?? [],
    'securityEvents' => $securityEvents,
]);
