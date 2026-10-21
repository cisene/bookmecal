<?php
/**
 * src/admin/config.php
 * Configuration management panel supporting IANA Timezone selection 
 * (Stockholm, Oslo, Helsinki, Tallinn, Riga, Vilnius).
 */
require_once __DIR__ . '/auth.php';

$settingsFile = dirname(__DIR__) . '/data/settings.json';
$successMsg = '';
$errorMsg = '';

$defaultSettings = [
    'default_country' => 'se',
    'timezone' => 'Europe/Stockholm',
    'supported_timezones' => [
        'Europe/Stockholm',
        'Europe/Oslo',
        'Europe/Helsinki',
        'Europe/Tallinn',
        'Europe/Riga',
        'Europe/Vilnius'
    ],
    'show_past_slots' => true
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(403);
        exit('CSRF verification failed.');
    }

    $timezone = is_string($_POST['timezone'] ?? null) ? trim($_POST['timezone']) : 'Europe/Stockholm';
    $defaultCountry = is_string($_POST['default_country'] ?? null) ? strtolower(trim($_POST['default_country'])) : 'se';
    $showPastSlots = isset($_POST['show_past_slots']) ? true : false;

    if (!in_array($timezone, $defaultSettings['supported_timezones'], true)) {
        $errorMsg = 'Selected timezone is not supported.';
    } elseif (!preg_match('/^[a-z]{2}$/', $defaultCountry)) {
        $errorMsg = 'Default country must be a two-letter code.';
    } else {
        $newSettings = $defaultSettings;
        $newSettings['timezone'] = $timezone;
        $newSettings['default_country'] = $defaultCountry;
        $newSettings['show_past_slots'] = $showPastSlots;

        $dir = dirname($settingsFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $fp = @fopen($settingsFile, 'c+b');
        if ($fp && @flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($newSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        if (function_exists('log_audit_action')) {
            log_audit_action($currentAdmin, 'CONFIG_UPDATE', "Updated system configuration (Timezone: {$timezone})");
        }

        $successMsg = 'Configuration settings saved successfully!';
    }
}

$currentSettings = $defaultSettings;
if (file_exists($settingsFile)) {
    $decoded = json_decode(file_get_contents($settingsFile), true);
    if (is_array($decoded)) {
        $currentSettings = array_merge($defaultSettings, $decoded);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Configuration - Admin</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f4f7f6; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 8px; }
        select, input[type="text"] { width: 100%; max-width: 400px; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { background: #007bff; color: #fff; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .alert { background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 15px; }
        .error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/header.php'; ?>

    <div class="card">
        <h2>System Configuration & Regional Timezone</h2>
        <p>Configure global operational parameters, default country scope, and IANA timezones.</p>

        <?php if (!empty($successMsg)): ?><div class="alert"><?php echo htmlspecialchars($successMsg); ?></div><?php endif; ?>
        <?php if (!empty($errorMsg)): ?><div class="error"><?php echo htmlspecialchars($errorMsg); ?></div><?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="form-group">
                <label>System Timezone (IANA)</label>
                <select name="timezone">
                    <?php foreach ($currentSettings['supported_timezones'] as $tz): ?>
                        <option value="<?php echo htmlspecialchars($tz); ?>" <?php echo $currentSettings['timezone'] === $tz ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tz); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="display:block; color:#666; margin-top:5px;">Controls all slot timing calculations and ISO timestamp outputs.</small>
            </div>

            <div class="form-group">
                <label>Default Country Code</label>
                <input type="text" name="default_country" value="<?php echo htmlspecialchars($currentSettings['default_country']); ?>" maxlength="2">
            </div>

            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px; font-weight: normal; cursor: pointer;">
                    <input type="checkbox" name="show_past_slots" value="1" <?php echo !empty($currentSettings['show_past_slots']) ? 'checked' : ''; ?>>
                    <strong>Show past slots in administrative views</strong>
                </label>
            </div>

            <button type="submit" class="btn">Save Configuration</button>
        </form>
    </div>
</div>
</body>
</html>