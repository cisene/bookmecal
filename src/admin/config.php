<?php
// src/admin/config.php
require_once __DIR__ . '/auth.php';

$configFile = dirname(__DIR__) . '/data/config.json';
$configDir = dirname($configFile);
if (!is_dir($configDir)) {
    mkdir($configDir, 0775, true);
}

$config = array();
if (file_exists($configFile)) {
    $decoded = json_decode(file_get_contents($configFile), true);
    if (is_array($decoded)) { $config = $decoded; }
}

$successMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        exit('CSRF verification failed.');
    }

    if (!isset($config['app'])) { $config['app'] = array(); }
    $config['app']['timezone'] = isset($_POST['timezone']) ? $_POST['timezone'] : 'Europe/Stockholm';
    $config['app']['locale'] = isset($_POST['locale']) ? $_POST['locale'] : 'sv_SE.UTF-8';
    $config['app']['language'] = isset($_POST['language']) ? $_POST['language'] : 'sv';
    $config['app']['date_format'] = isset($_POST['date_format']) ? $_POST['date_format'] : 'Y-m-d';
    $config['app']['time_format'] = isset($_POST['time_format']) ? $_POST['time_format'] : 'H:i';
    $config['app']['last_modified_by'] = $currentAdmin;

    $fp = fopen($configFile, 'c+b');
    if ($fp && flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }
    
    log_audit_action($currentAdmin, 'CONFIG_UPDATE', 'Updated global calendar configuration settings.');
    $successMsg = 'Configuration updated successfully by ' . htmlspecialchars($currentAdmin);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Configuration - Admin</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 1100px; margin: 0 auto; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
        .form-group input { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        .btn { background: #007bff; color: #fff; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .alert { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/header.php'; ?>

    <div style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <h2>Configuration</h2>
        <?php if (!empty($successMsg)): ?><div class="alert"><?php echo $successMsg; ?></div><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
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
    </div>
</div>
</body>
</html>