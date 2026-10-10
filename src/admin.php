<?php
// admin.php - Comprehensive Admin Management Dashboard

$configFile = __DIR__ . '/data/config.json';
$dataDir = __DIR__ . '/src/data';
$pendingDir = $dataDir . '/pending';
$availabilityFile = $dataDir . '/booking/availability.json';

session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$config = array();
if (file_exists($configFile)) {
    $fileContent = file_get_contents($configFile);
    $decodedConfig = json_decode($fileContent, true);
    if (is_array($decodedConfig)) {
        $config = $decodedConfig;
    }
}

$adminUser = isset($config['app']['admin_user']) ? $config['app']['admin_user'] : 'admin';
$adminPass = isset($config['app']['admin_pass']) ? $config['app']['admin_pass'] : 'secret123';

// Handle login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $loginError = 'CSRF verification failed.';
    } elseif ($_POST['username'] === $adminUser && $_POST['password'] === $adminPass) {
        $_SESSION['admin_logged_in'] = true;
        session_regenerate_id(true);
        header('Location: admin.php');
        exit;
    } else {
        $loginError = 'Invalid credentials.';
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    header('Location: admin.php');
    exit;
}

$isLoggedIn = isset($_SESSION['admin_logged_in']) ? $_SESSION['admin_logged_in'] : false;
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'pending';

// Handle form submissions for config and data updates
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        exit('CSRF verification failed.');
    }

    $formType = isset($_POST['form_type']) ? $_POST['form_type'] : '';

    // 1. Update Global Configuration
    if ($formType === 'save_config') {
        if (!isset($config['app'])) {
            $config['app'] = array();
        }
        $config['app']['timezone'] = isset($_POST['timezone']) ? $_POST['timezone'] : 'Europe/Stockholm';
        $config['app']['locale'] = isset($_POST['locale']) ? $_POST['locale'] : 'sv_SE.UTF-8';
        $config['app']['language'] = isset($_POST['language']) ? $_POST['language'] : 'sv';
        $config['app']['date_format'] = isset($_POST['date_format']) ? $_POST['date_format'] : 'Y-m-d';
        $config['app']['time_format'] = isset($_POST['time_format']) ? $_POST['time_format'] : 'H:i';

        $fp = fopen($configFile, 'c+b');
        if ($fp && flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        header('Location: admin.php?tab=config&success=1');
        exit;
    }

    // 2. Update Availability Slots
    if ($formType === 'save_availability') {
        $rawAvailability = isset($_POST['availability_json']) ? $_POST['availability_json'] : '[]';
        $newAvailability = json_decode($rawAvailability, true);
        if (is_array($newAvailability)) {
            $fp = fopen($availabilityFile, 'c+b');
            if ($fp && flock($fp, LOCK_EX)) {
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode($newAvailability, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                fflush($fp);
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }
        header('Location: admin.php?tab=availability&success=1');
        exit;
    }

    // 3. Handle Pending Request Actions
    if ($formType === 'booking_action') {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        $targetId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $targetFile = $pendingDir . '/booking_' . $targetId . '.json';

        if (file_exists($targetFile)) {
            $fp = fopen($targetFile, 'c+b');
            if ($fp && flock($fp, LOCK_EX)) {
                $content = stream_get_contents($fp);
                $booking = json_decode($content, true);
                if (is_array($booking)) {
                    $booking['status'] = ($action === 'approve') ? 'approved' : 'rejected';
                    ftruncate($fp, 0);
                    rewind($fp);
                    fwrite($fp, json_encode($booking, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    fflush($fp);
                }
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }
        header('Location: admin.php?tab=pending');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Book Me Calendar</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 900px; margin: 0 auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .nav-tabs { display: flex; list-style: none; padding: 0; margin: 0 0 20px 0; border-bottom: 2px solid #dee2e6; }
        .nav-tabs li { margin-right: 10px; }
        .nav-tabs a { display: block; padding: 10px 15px; text-decoration: none; color: #495057; font-weight: bold; border-radius: 4px 4px 0 0; }
        .nav-tabs a.active { background: #007bff; color: #fff; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        .btn { background: #007bff; color: #fff; padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background: #f8f9fa; }
        .alert-success { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>
<div class="container">
    <?php if (!$isLoggedIn): ?>
        <h2>Admin Login</h2>
        <?php if (!empty($loginError)): ?><p style="color:red;"><?php echo htmlspecialchars($loginError); ?></p><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="form-group"><label>Username</label><input type="text" name="username" required></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
            <button type="submit" name="login" class="btn">Log In</button>
        </form>
    <?php else: ?>
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h1>Admin Dashboard</h1>
            <a href="admin.php?logout=1" class="btn" style="background:#6c757d;">Log Out</a>
        </div>

        <ul class="nav-tabs">
            <li><a href="admin.php?tab=pending" class="<?php echo $activeTab === 'pending' ? 'active' : ''; ?>">Pending Bookings</a></li>
            <li><a href="admin.php?tab=config" class="<?php echo $activeTab === 'config' ? 'active' : ''; ?>">Configuration</a></li>
            <li><a href="admin.php?tab=availability" class="<?php echo $activeTab === 'availability' ? 'active' : ''; ?>">Availability Slots</a></li>
        </ul>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert-success">Changes saved successfully!</div>
        <?php endif; ?>

        <?php if ($activeTab === 'pending'): ?>
            <h2>Pending Requests (src/data/pending/)</h2>
            <?php
            $files = glob($pendingDir . '/booking_*.json');
            if (empty($files)):
            ?>
                <p>No pending booking requests.</p>
            <?php else: ?>
                <table>
                    <tr><th>ID</th><th>Client</th><th>Email</th><th>Start</th><th>Status</th><th>Actions</th></tr>
                    <?php foreach ($files as $file):
                        $booking = json_decode(file_get_contents($file), true);
                        if (!is_array($booking)) continue;
                    ?>
                        <tr>
                            <td><?php echo isset($booking['id']) ? (int)$booking['id'] : 0; ?></td>
                            <td><?php echo htmlspecialchars(isset($booking['client_name']) ? $booking['client_name'] : ''); ?></td>
                            <td><?php echo htmlspecialchars(isset($booking['client_email']) ? $booking['client_email'] : ''); ?></td>
                            <td><?php echo htmlspecialchars(isset($booking['start_datetime']) ? $booking['start_datetime'] : ''); ?></td>
                            <td><?php echo htmlspecialchars(isset($booking['status']) ? $booking['status'] : 'pending'); ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="form_type" value="booking_action">
                                    <input type="hidden" name="id" value="<?php echo isset($booking['id']) ? (int)$booking['id'] : 0; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-success" style="padding:4px 8px;font-size:12px;">Approve</button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="form_type" value="booking_action">
                                    <input type="hidden" name="id" value="<?php echo isset($booking['id']) ? (int)$booking['id'] : 0; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-danger" style="padding:4px 8px;font-size:12px;">Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>

        <?php elseif ($activeTab === 'config'): ?>
            <h2>Configuration (data/config.json)</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="form_type" value="save_config">
                <div class="form-group">
                    <label>Timezone</label>
                    <input type="text" name="timezone" value="<?php echo htmlspecialchars(isset($config['app']['timezone']) ? $config['app']['timezone'] : 'Europe/Stockholm'); ?>">
                </div>
                <div class="form-group">
                    <label>Locale</label>
                    <input type="text" name="locale" value="<?php echo htmlspecialchars(isset($config['app']['locale']) ? $config['app']['locale'] : 'sv_SE.UTF-8'); ?>">
                </div>
                <div class="form-group">
                    <label>Language (sv / en)</label>
                    <input type="text" name="language" value="<?php echo htmlspecialchars(isset($config['app']['language']) ? $config['app']['language'] : 'sv'); ?>">
                </div>
                <div class="form-group">
                    <label>Date Format</label>
                    <input type="text" name="date_format" value="<?php echo htmlspecialchars(isset($config['app']['date_format']) ? $config['app']['date_format'] : 'Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>Time Format</label>
                    <input type="text" name="time_format" value="<?php echo htmlspecialchars(isset($config['app']['time_format']) ? $config['app']['time_format'] : 'H:i'); ?>">
                </div>
                <button type="submit" class="btn">Save Configuration</button>
            </form>

        <?php elseif ($activeTab === 'availability'): ?>
            <h2>Availability Slots (src/data/booking/availability.json)</h2>
            <?php
            $currentAvail = file_exists($availabilityFile) ? file_get_contents($availabilityFile) : '[]';
            ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="form_type" value="save_availability">
                <div class="form-group">
                    <label>Availability JSON Payload</label>
                    <textarea name="availability_json" rows="12" style="font-family:monospace;"><?php echo htmlspecialchars($currentAvail); ?></textarea>
                </div>
                <button type="submit" class="btn">Save Availability</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>

```