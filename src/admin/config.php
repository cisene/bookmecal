<?php
// src/admin/config.php
require_once __DIR__ . '/auth.php';

$dataDir = dirname(__DIR__) . '/data';
$configFile = $dataDir . '/config.json';
$tokensFile = $dataDir . '/tokens.json';

if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0775, true);
}

$config = array();
if (file_exists($configFile)) {
    $decoded = json_decode(file_get_contents($configFile), true);
    if (is_array($decoded)) { $config = $decoded; }
}

$tokens = array();
if (file_exists($tokensFile)) {
    $decodedTokens = json_decode(file_get_contents($tokensFile), true);
    if (is_array($decodedTokens)) { $tokens = $decodedTokens; }
}

$successMsg = '';

// Check if currently enrolled (has client_id in config and tokens in tokens.json)
$isEnrolled = !empty($config['calendar']['client_id']) && !empty($tokens['access_token']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        exit('CSRF verification failed.');
    }

    $action = isset($_POST['action']) ? $_POST['action'] : 'save';

    if ($action === 'unenroll') {
        if (isset($config['calendar'])) {
            unset($config['calendar']['client_id'], $config['calendar']['client_secret'], $config['calendar']['owner_name'], $config['calendar']['notification_email'], $config['calendar']['calendar_id']);
            $config['calendar']['last_modified_by'] = $currentAdmin;
        }

        $fp = @fopen($configFile, 'c+b');
        if ($fp && @flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        if (file_exists($tokensFile)) {
            @unlink($tokensFile);
        }

        log_audit_action($currentAdmin, 'GOOGLE_CALENDAR_UNENROLL', 'Removed Google Calendar enrollment and purged stored OAuth tokens.');
        $successMsg = 'Successfully unenrolled from Google Calendar and cleared all stored sync tokens.';
        $isEnrolled = false;
        $tokens = array();
    } else {
        if (!isset($config['app'])) { $config['app'] = array(); }
        $config['app']['timezone'] = isset($_POST['timezone']) ? $_POST['timezone'] : 'Europe/Stockholm';
        $config['app']['locale'] = isset($_POST['locale']) ? $_POST['locale'] : 'sv_SE.UTF-8';
        $config['app']['language'] = isset($_POST['language']) ? $_POST['language'] : 'sv';
        $config['app']['date_format'] = isset($_POST['date_format']) ? $_POST['date_format'] : 'Y-m-d';
        $config['app']['time_format'] = isset($_POST['time_format']) ? $_POST['time_format'] : 'H:i';
        $config['app']['storage_backend'] = isset($_POST['storage_backend']) ? $_POST['storage_backend'] : 'json';
        $config['app']['last_modified_by'] = $currentAdmin;

        if (!isset($config['calendar'])) { $config['calendar'] = array(); }
        $config['calendar']['cache_refresh_interval'] = isset($_POST['cache_refresh_interval']) ? (int)$_POST['cache_refresh_interval'] : 3600;

        $fp = @fopen($configFile, 'c+b');
        if ($fp && @flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        
        log_audit_action($currentAdmin, 'CONFIG_UPDATE', 'Updated global application settings and storage backend configuration.');
        $successMsg = 'Configuration updated successfully by ' . htmlspecialchars($currentAdmin);
    }
}

$currentBackend = isset($config['app']['storage_backend']) ? $config['app']['storage_backend'] : 'json';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Configuration - Admin</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 1100px; margin: 0 auto; }
        .card { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
        .form-group input, .form-group select { width: 100%; max-width: 500px; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        .btn { background: #007bff; color: #fff; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-danger-small { background: #dc3545; color: #fff; padding: 5px 12px; font-size: 13px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-block; }
        .btn-danger-small:hover { background: #c82333; }
        .alert { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .warning-text { color: #856404; background: #fff3cd; border: 1px solid #ffeeba; padding: 10px; border-radius: 4px; margin-top: 5px; max-width: 500px; font-size: 13px; font-weight: 500; }
        .section-title { margin-top: 25px; border-bottom: 1px solid #dee2e6; padding-bottom: 8px; font-size: 18px; color: #495057; display: flex; justify-content: space-between; align-items: center; }
        .wizard-container { display: flex; align-items: center; gap: 10px; }
        .wizard-link { font-size: 14px; background: #e7f5ff; color: #007bff; padding: 5px 12px; border-radius: 4px; text-decoration: none; border: 1px solid #b3d7ff; }
        .wizard-link.disabled { background: #e9ecef; color: #6c757d; border-color: #dee2e6; pointer-events: none; cursor: not-allowed; opacity: 0.65; }
        .enrolled-badge { background: #d4edda; color: #155724; font-size: 12px; padding: 2px 8px; border-radius: 12px; font-weight: bold; border: 1px solid #c3e6cb; }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/header.php'; ?>

    <div class="card">
        <h2>Configuration Settings</h2>
        <?php if (!empty($successMsg)): ?><div class="alert"><?php echo $successMsg; ?></div><?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="save">
            
            <div class="section-title" style="margin-top:0;">General Application Settings</div>
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
            <div class="form-group">
                <label>Storage Backend</label>
                <select name="storage_backend">
                    <option value="json" <?php echo $currentBackend === 'json' ? 'selected' : ''; ?>>JSON (File-based)</option>
                    <option value="sqlite" <?php echo $currentBackend === 'sqlite' ? 'selected' : ''; ?>>SQLite</option>
                    <option value="mysql" <?php echo $currentBackend === 'mysql' ? 'selected' : ''; ?>>MySQL</option>
                </select>
                <div class="warning-text">⚠️ Warning: Do not change unless you know what you are doing.</div>
            </div>

            <div class="section-title">
                <span>
                    Google Calendar Service Integration 
                    <?php if ($isEnrolled): ?><span class="enrolled-badge">Enrolled Active</span><?php endif; ?>
                </span>
                <div class="wizard-container">
                    <a href="setup_google.php" class="wizard-link <?php echo $isEnrolled ? 'disabled' : ''; ?>">
                        ✨ Launch Enrollment Wizard &rarr;
                    </a>
                    <?php if ($isEnrolled): ?>
                        <button type="submit" name="action" value="unenroll" class="btn-danger-small" onclick="return confirm('Are you sure you want to unenroll? This will remove all calendar identity parameters and delete your Google OAuth tokens.');">
                            Unenroll
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group" style="margin-top: 15px;">
                <label>Calendar Cache Refresh Interval (seconds)</label>
                <input type="number" name="cache_refresh_interval" value="<?php echo htmlspecialchars(isset($config['calendar']['cache_refresh_interval']) ? $config['calendar']['cache_refresh_interval'] : 3600); ?>" min="60" step="60">
            </div>

            <button type="submit" class="btn">Save Configuration</button>
        </form>
    </div>
</div>
</body>
</html>