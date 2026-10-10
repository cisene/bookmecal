<?php
// admin.php - Administrative dashboard for managing bookings with CSRF protection

$config = require_once __DIR__ . '/config.php';
require_once __DIR__ . '/BookingRepository.php';

$adminUser = $config['app']['admin_user'] ?? 'admin';
$adminPass = $config['app']['admin_pass'] ?? 'secret123';

session_start();

// Initialize CSRF token if not present
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $loginError = 'Invalid security token (CSRF verification failed).';
    } elseif ($_POST['username'] === $adminUser && $_POST['password'] === $adminPass) {
        $_SESSION['admin_logged_in'] = true;
        session_regenerate_id(true);
        header('Location: admin.php');
        exit;
    } else {
        $loginError = 'Invalid username or password.';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    header('Location: admin.php');
    exit;
}

$isLoggedIn = $_SESSION['admin_logged_in'] ?? false;

$repository = new BookingRepository($config['storage']['data_dir'] ?? null);
$bookingsFile = ($config['storage']['data_dir'] ?? __DIR__ . '/data') . '/bookings.json';

// Handle booking status update actions via POST for CSRF safety
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        exit('CSRF verification failed.');
    }

    $action = $_POST['action'];
    $targetId = (int)$_POST['id'];
    
    if (file_exists($bookingsFile)) {
        $fp = fopen($bookingsFile, 'c+b');
        if ($fp && flock($fp, LOCK_EX)) {
            $content = stream_get_contents($fp);
            $bookings = json_decode($content, true) ?: [];
            
            $updated = false;
            foreach ($bookings as &$b) {
                if ($b['id'] === $targetId) {
                    if ($action === 'approve') {
                        $b['status'] = 'approved';
                        $updated = true;
                    } elseif ($action === 'reject') {
                        $b['status'] = 'rejected';
                        $updated = true;
                    }
                }
            }
            unset($b);

            if ($updated) {
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode($bookings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                fflush($fp);
            }
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
    header('Location: admin.php');
    exit;
}

// Fetch all bookings for display
$bookings = [];
if (file_exists($bookingsFile)) {
    $fp = fopen($bookingsFile, 'rb');
    if ($fp) {
        flock($fp, LOCK_SH);
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        $bookings = json_decode($content, true) ?: [];
    }
}
usort($bookings, fn($a, $b) => $b['id'] <=> $a['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Book Me Calendar</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        h1, h2 { color: #2c3e50; }
        .login-box {
            max-width: 350px;
            margin: 100px auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .form-group input {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .btn {
            background-color: #007bff;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }
        .btn:hover { background-color: #0056b3; }
        .btn-success { background-color: #28a745; }
        .btn-success:hover { background-color: #218838; }
        .btn-danger { background-color: #dc3545; }
        .btn-danger:hover { background-color: #c82333; }
        .btn-logout { background-color: #6c757d; float: right; }
        .btn-logout:hover { background-color: #5a6268; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        th { background-color: #f1f3f5; }
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-pending { background-color: #ffc107; color: #212529; }
        .badge-approved { background-color: #28a745; color: #fff; }
        .badge-rejected { background-color: #dc3545; color: #fff; }
        .error { color: #dc3545; margin-bottom: 10px; font-size: 14px; }
        .clearfix::after { content: ""; clear: both; display: table; }
        .inline-form { display: inline; }
    </style>
</head>
<body>

<div class="container">
    <?php if (!$isLoggedIn): ?>
        <div class="login-box">
            <h2>Admin Login</h2>
            <?php if (!empty($loginError)): ?>
                <div class="error"><?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" name="login" class="btn" style="width: 100%;">Log In</button>
            </form>
            <p style="margin-top: 15px; font-size: 12px; color: #666; text-align: center;">Default: admin / secret123</p>
        </div>
    <?php else: ?>
        <div class="clearfix">
            <h1 style="float: left; margin: 0;">Admin Dashboard</h1>
            <a href="admin.php?logout=1" class="btn btn-logout">Log Out</a>
        </div>
        
        <h2>Booking Requests</h2>
        
        <?php if (empty($bookings)): ?>
            <p>No bookings found.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Client</th>
                        <th>Email</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td><?php echo (int)$b['id']; ?></td>
                            <td><?php echo htmlspecialchars($b['client_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($b['client_email'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($b['start_datetime'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($b['end_datetime'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <span class="badge badge-<?php echo htmlspecialchars($b['status'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo ucfirst(htmlspecialchars($b['status'], ENT_QUOTES, 'UTF-8')); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($b['status'] === 'pending'): ?>
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="id" value="<?php echo (int)$b['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-success" style="padding: 4px 8px; font-size: 12px;">Approve</button>
                                    </form>
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="id" value="<?php echo (int)$b['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn btn-danger" style="padding: 4px 8px; font-size: 12px;">Reject</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: #666; font-size: 12px;">Processed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>