<?php
/**
 * src/admin/holidays.php
 * Holiday Calendar Management
 * Manages regional holiday blocks (e.g., holidays/se.json) to prevent hardcoded dates.
 */

require_once __DIR__ . '/auth.php';

$holidaysDir = dirname(__DIR__) . '/holidays';
$regionCode = 'se'; // Default region, extensible later
$holidayFile = $holidaysDir . '/' . $regionCode . '.json';

if (!is_dir($holidaysDir)) {
    @mkdir($holidaysDir, 0775, true);
}

// Initialize structure if file doesn't exist
$holidayData = array(
    'country' => 'Sweden',
    'code' => strtoupper($regionCode),
    'holidays' => array()
);

if (file_exists($holidayFile)) {
    $decoded = json_decode(file_get_contents($holidayFile), true);
    if (is_array($decoded)) {
        $holidayData = $decoded;
    }
}

$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(403);
        exit('CSRF verification failed.');
    }

    $action = isset($_POST['action']) ? trim($_POST['action']) : '';

    if ($action === 'add_holiday') {
        $date = trim(isset($_POST['date']) ? $_POST['date'] : '');
        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');

        if (empty($date) || empty($name)) {
            $errorMsg = 'Both date and holiday name are required.';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $errorMsg = 'Invalid date format. Use YYYY-MM-DD.';
        } else {
            $exists = false;
            foreach ($holidayData['holidays'] as $h) {
                if ($h['date'] === $date) {
                    $exists = true;
                    break;
                }
            }

            if ($exists) {
                $errorMsg = 'A holiday on this date already exists.';
            } else {
                $holidayData['holidays'][] = array(
                    'date' => $date,
                    'name' => $name
                );

                usort($holidayData['holidays'], function($a, $b) {
                    return strcmp($a['date'], $b['date']);
                });

                $fp = @fopen($holidayFile, 'c+b');
                if ($fp && @flock($fp, LOCK_EX)) {
                    ftruncate($fp, 0);
                    rewind($fp);
                    fwrite($fp, json_encode($holidayData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    fflush($fp);
                    flock($fp, LOCK_UN);
                    fclose($fp);
                }

                if (function_exists('log_audit_action')) {
                    log_audit_action(isset($currentAdmin) ? $currentAdmin : 'admin', 'HOLIDAY_ADD', "Added holiday: {$name} on {$date}");
                }

                $successMsg = 'Holiday added successfully!';
            }
        }
    } elseif ($action === 'delete_holiday') {
        $targetDate = trim(isset($_POST['date']) ? $_POST['date'] : '');
        
        $filtered = array();
        $removedName = '';
        foreach ($holidayData['holidays'] as $h) {
            if ($h['date'] === $targetDate) {
                $removedName = $h['name'];
            } else {
                $filtered[] = $h;
            }
        }

        if ($removedName !== '') {
            $holidayData['holidays'] = $filtered;

            $fp = @fopen($holidayFile, 'c+b');
            if ($fp && @flock($fp, LOCK_EX)) {
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode($holidayData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                fflush($fp);
                flock($fp, LOCK_UN);
                fclose($fp);
            }

            if (function_exists('log_audit_action')) {
                log_audit_action(isset($currentAdmin) ? $currentAdmin : 'admin', 'HOLIDAY_DELETE', "Deleted holiday: {$removedName} on {$targetDate}");
            }

            $successMsg = 'Holiday deleted successfully.';
        } else {
            $errorMsg = 'Holiday not found.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Holiday Calendar Management - Admin</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f4f7f6; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 1100px; margin: 0 auto; }
        .card { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; background: #fff; }
        th, td { padding: 12px 15px; border: 1px solid #dee2e6; text-align: left; font-size: 14px; }
        th { background: #f8f9fa; font-weight: 600; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 5px; color: #495057; }
        .form-group input { width: 100%; max-width: 400px; padding: 10px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box; }
        .form-row { display: flex; gap: 20px; max-width: 500px; }
        .form-row .form-group { flex: 1; }
        .btn { background: #007bff; color: #fff; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-block; transition: background 0.2s; }
        .btn:hover { background: #0056b3; }
        .btn-danger { background: #dc3545; padding: 6px 12px; font-size: 13px; }
        .btn-danger:hover { background: #bd2130; }
        .alert { background: #d4edda; color: #155724; padding: 12px 15px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; padding: 12px 15px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #f5c6cb; }
        code { background: #e9ecef; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 13px; }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/header.php'; ?>

    <div class="card">
        <h2>Holiday Calendar Management (<?php echo htmlspecialchars($holidayData['country']); ?>)</h2>
        <p>Manage public holidays and red days dynamically stored in <code>holidays/se.json</code>[cite: 1]. These dates block booking availability automatically.</p>

        <?php if (!empty($successMsg)): ?>
            <div class="alert"><?php echo htmlspecialchars($successMsg); ?></div>
        <?php endif; ?>
        <?php if (!empty($errorMsg)): ?>
            <div class="error"><?php echo htmlspecialchars($errorMsg); ?></div>
        <?php endif; ?>

        <h3>Configured Holidays</h3>
        <?php if (empty($holidayData['holidays'])): ?>
            <p style="color: #6c757d; font-style: italic;">No holidays defined yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Holiday Name</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($holidayData['holidays'] as $holiday): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($holiday['date']); ?></code></td>
                            <td><strong><?php echo htmlspecialchars($holiday['name']); ?></strong></td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to remove <?php echo htmlspecialchars($holiday['name']); ?>?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                    <input type="hidden" name="action" value="delete_holiday">
                                    <input type="hidden" name="date" value="<?php echo htmlspecialchars($holiday['date']); ?>">
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h3 style="margin-top: 40px; border-top: 1px solid #dee2e6; padding-top: 25px;">Add New Holiday</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <input type="hidden" name="action" value="add_holiday">

            <div class="form-row">
                <div class="form-group">
                    <label>Date (YYYY-MM-DD)</label>
                    <input type="date" name="date" required placeholder="YYYY-MM-DD">
                </div>
                <div class="form-group">
                    <label>Holiday Name</label>
                    <input type="text" name="name" required placeholder="e.g. Midsummer Eve (Midsommarafton)">
                </div>
            </div>

            <button type="submit" class="btn">Add Holiday</button>
        </form>
    </div>
</div>
</body>
</html>