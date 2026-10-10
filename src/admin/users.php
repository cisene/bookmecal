<?php
// src/admin/users.php - User management and audit log viewer
require_once __DIR__ . '/auth.php';

$htpasswdFile = __DIR__ . '/.htpasswd';
$auditFile = dirname(__DIR__) . '/data/audit.json';

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        exit('CSRF verification failed.');
    }

    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'save_user') {
        $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        // Validate username using native functions to avoid PCRE/JIT issues
        $cleanUserForCheck = str_replace('_', '', $username);
        $isValidUsername = (
            strlen($username) >= 3 && 
            strlen($username) <= 20 && 
            ctype_alnum($cleanUserForCheck)
        );

        if (!$isValidUsername) {
            $error = 'Invalid username format (3-20 alphanumeric characters/underscores).';
        } elseif (empty($password)) {
            $error = 'Password cannot be empty.';
        } else {
            $salt = bin2hex(random_bytes(8));
            $hash = crypt($password, '$6$' . $salt . '$');
            
            $users = array();
            if (file_exists($htpasswdFile)) {
                $lines = file($htpasswdFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos($line, ':') !== false) {
                        list($u, $h) = explode(':', $line, 2);
                        if (trim($u) !== $username) {
                            $users[trim($u)] = trim($h);
                        }
                    }
                }
            }
            
            $users[$username] = $hash;
            
            $content = '';
            foreach ($users as $u => $h) {
                $content .= $u . ':' . $h . "\n";
            }

            if (file_put_contents($htpasswdFile, $content) !== false) {
                $msg = 'User "' . htmlspecialchars($username) . '" saved successfully.';
                log_audit_action($currentAdmin, 'USER_MODIFY', 'Added or updated credentials for user: ' . $username);
            } else {
                $error = 'Failed to write to .htpasswd file. Check file permissions in src/admin/.';
            }
        }
    } elseif ($action === 'delete_user') {
        $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
        if ($username === $currentAdmin) {
            $error = 'You cannot delete your own active account while logged in.';
        } elseif (!empty($username) && file_exists($htpasswdFile)) {
            $lines = file($htpasswdFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $content = '';
            $found = false;
            foreach ($lines as $line) {
                if (strpos($line, ':') !== false) {
                    list($u, $h) = explode(':', $line, 2);
                    if (trim($u) !== $username) {
                        $content .= $line . "\n";
                    } else {
                        $found = true;
                    }
                }
            }
            if ($found && file_put_contents($htpasswdFile, $content)) {
                $msg = 'User "' . htmlspecialchars($username) . '" removed successfully.';
                log_audit_action($currentAdmin, 'USER_DELETE', 'Removed user: ' . $username);
            }
        }
    }
}

$configuredUsers = array();
if (file_exists($htpasswdFile)) {
    $lines = file($htpasswdFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, ':') !== false) {
            list($u) = explode(':', $line, 2);
            $configuredUsers[] = trim($u);
        }
    }
}

$auditLogs = array();
if (file_exists($auditFile)) {
    $decoded = json_decode(file_get_contents($auditFile), true);
    if (is_array($decoded)) {
        $auditLogs = $decoded;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Administration & Audit Log</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 1100px; margin: 0 auto; }
        .card { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; background: #fff; }
        th, td { padding: 10px 12px; border: 1px solid #dee2e6; text-align: left; font-size: 14px; }
        th { background: #f8f9fa; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
        .form-group input { width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { background: #007bff; color: #fff; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-danger { background: #dc3545; padding: 5px 10px; font-size: 12px; }
        .alert { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/header.php'; ?>

    <div class="card">
        <h2>Staff & Administrator Accounts</h2>
        <p>Manage users authorized to access the admin panel via Apache Basic Authentication.</p>

        <?php if (!empty($msg)): ?><div class="alert"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
        <?php if (!empty($error)): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <h3>Configured Users</h3>
        <?php if (empty($configuredUsers)): ?>
            <p>No users found in .htpasswd.</p>
        <?php else: ?>
            <table>
                <tr><th>Username</th><th>Actions</th></tr>
                <?php foreach ($configuredUsers as $user): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($user); ?></strong></td>
                        <td>
                            <?php if ($user !== $currentAdmin): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to remove <?php echo htmlspecialchars($user); ?>?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="username" value="<?php echo htmlspecialchars($user); ?>">
                                    <button type="submit" class="btn btn-danger">Revoke Access</button>
                                </form>
                            <?php else: ?>
                                <span style="color: #6c757d; font-size: 12px;">(Current User)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <h3 style="margin-top: 30px;">Add or Update Staff User</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="save_user">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required placeholder="e.g. receptionist2">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="Secure password">
            </div>
            <button type="submit" class="btn">Save User Account</button>
        </form>
    </div>

    <div class="card">
        <h2>Staff Audit Trail</h2>
        <p>Real-time log of administrative actions, booking approvals, and settings changes performed by staff (max 200 entries).</p>

        <?php if (empty($auditLogs)): ?>
            <p>No audit events recorded yet.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Details</th>
                    <th>IP Address</th>
                </tr>
                <?php foreach ($auditLogs as $log): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(isset($log['timestamp']) ? $log['timestamp'] : ''); ?></td>
                        <td><strong><?php echo htmlspecialchars(isset($log['user']) ? $log['user'] : ''); ?></strong></td>
                        <td><code><?php echo htmlspecialchars(isset($log['action']) ? $log['action'] : ''); ?></code></td>
                        <td><?php echo htmlspecialchars(isset($log['details']) ? $log['details'] : ''); ?></td>
                        <td><?php echo htmlspecialchars(isset($log['ip']) ? $log['ip'] : ''); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>