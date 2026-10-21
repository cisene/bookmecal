<?php
/**
 * src/admin/users.php
 * Admin accounts (.htpasswd for Apache Basic Auth) and the audit log viewer.
 */

require_once __DIR__ . '/auth.php';

$htpasswdFile = __DIR__ . '/.htpasswd';
$flash = [];

/**
 * Reads .htpasswd as username => hash.
 */
function read_htpasswd(string $file): array {
    $users = [];
    foreach (is_file($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [] as $line) {
        if (strpos($line, ':') !== false) {
            [$user, $hash] = explode(':', $line, 2);
            $users[trim($user)] = trim($hash);
        }
    }
    return $users;
}

function write_htpasswd(string $file, array $users): bool {
    $content = '';
    foreach ($users as $user => $hash) {
        $content .= $user . ':' . $hash . "\n";
    }
    return file_put_contents($file, $content, LOCK_EX) !== false;
}

if (is_post()) {
    require_csrf();
    $action = request_string($_POST, 'action');
    $username = request_string($_POST, 'username');
    $users = read_htpasswd($htpasswdFile);

    if ($action === 'save_user') {
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $username)) {
            $flash[] = flash_error('Invalid username format (3-20 letters, digits or underscores).');
        } elseif ($password === '') {
            $flash[] = flash_error('Password cannot be empty.');
        } else {
            $users[$username] = crypt($password, '$6$' . bin2hex(random_bytes(8)) . '$');
            if (write_htpasswd($htpasswdFile, $users)) {
                log_audit_action($currentAdmin, 'USER_MODIFY', 'Added or updated credentials for user: ' . $username);
                header('Location: users.php?done=saved');
                exit;
            }
            $flash[] = flash_error('Failed to write the .htpasswd file. Check file permissions in src/admin/.');
        }
    } elseif ($action === 'delete_user') {
        if ($username === $currentAdmin) {
            $flash[] = flash_error('You cannot delete your own account while logged in.');
        } elseif (isset($users[$username])) {
            unset($users[$username]);
            if (write_htpasswd($htpasswdFile, $users)) {
                log_audit_action($currentAdmin, 'USER_DELETE', 'Removed user: ' . $username);
                header('Location: users.php?done=deleted');
                exit;
            }
            $flash[] = flash_error('Failed to write the .htpasswd file. Check file permissions in src/admin/.');
        }
    }
}

$done = request_string($_GET, 'done');
if (in_array($done, ['saved', 'deleted'], true)) {
    $flash[] = flash_success('admin.users.done.' . $done);
}

render_admin('users', [
    'title' => 'admin.users.title',
    'flash' => $flash,
    'users' => array_keys(read_htpasswd($htpasswdFile)),
    'auditLogs' => JsonStore::read(ResourceProvider::dataPath('audit.json')) ?? [],
]);
