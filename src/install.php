<?php
// install.php - Automated installation and environment verification script for Book Me Calendar

$dataDir = __DIR__ . '/data';
$cacheDir = __DIR__ . '/cache';
$configFile = __DIR__ . '/config.php';

$messages = [];
$errors = [];
$installed = false;

// Ensure directories exist or try to create them
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
}
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

// Handle form submission for setup
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appName = trim($_POST['app_name'] ?? 'Book Me Calendar');
    $adminUser = trim($_POST['admin_user'] ?? 'admin');
    $adminPass = trim($_POST['admin_pass'] ?? '');

    if (empty($adminPass)) {
        $errors[] = 'Admin password cannot be empty.';
    } else {
        // Test write permissions
        if (!is_writable($dataDir)) {
            $errors[] = 'The data/ directory is not writable. Please check folder permissions (chmod 755 or 775).';
        }
        if (!is_writable($cacheDir)) {
            $errors[] = 'The cache/ directory is not writable. Please check folder permissions.';
        }

        if (empty($errors)) {
            // Initialize default JSON files if they don't exist
            $bookingsFile = $dataDir . '/bookings.json';
            $holidaysFile = $dataDir . '/holidays.json';
            $operatingHoursFile = $dataDir . '/operating_hours.json';

            if (!file_exists($bookingsFile)) {
                file_put_contents($bookingsFile, json_encode([], JSON_PRETTY_PRINT));
            }
            if (!file_exists($holidaysFile)) {
                file_put_contents($holidaysFile, json_encode([], JSON_PRETTY_PRINT));
            }
            if (!file_exists($operatingHoursFile)) {
                $defaultHours = [
                    1 => ['start_time' => '08:00', 'end_time' => '17:00'],
                    2 => ['start_time' => '08:00', 'end_time' => '17:00'],
                    3 => ['start_time' => '08:00', 'end_time' => '17:00'],
                    4 => ['start_time' => '08:00', 'end_time' => '17:00'],
                    5 => ['start_time' => '08:00', 'end_time' => '17:00'],
                ];
                file_put_contents($operatingHoursFile, json_encode($defaultHours, JSON_PRETTY_PRINT));
            }

            // Create or update config with admin credentials
            $configContent = "<?php\n// config.php - Central configuration\n\nreturn [\n"
                . "    'storage' => [\n"
                . "        'type'     => 'json',\n"
                . "        'data_dir' => __DIR__ . '/data',\n"
                . "    ],\n"
                . "    'app' => [\n"
                . "        'name'          => " . var_export($appName, true) . ",\n"
                . "        'base_url'      => 'http://' . (\$_SERVER['HTTP_HOST'] ?? 'localhost'),\n"
                . "        'default_lang'  => 'en',\n"
                . "        'timezone'      => 'Europe/Stockholm',\n"
                . "        'slot_duration' => 60,\n"
                . "        'admin_user'    => " . var_export($adminUser, true) . ",\n"
                . "        'admin_pass'    => " . var_export($adminPass, true) . ",\n"
                . "    ],\n"
                . "    'smtp' => [\n"
                . "        'enabled'    => false,\n"
                . "        'from_email' => 'noreply@example.com',\n"
                . "        'from_name'  => " . var_export($appName, true) . ",\n"
                . "    ]\n"
                . "];\n";

            if (file_put_contents($configFile, $configContent) !== false) {
                $installed = true;
                $messages[] = 'Installation completed successfully! You can now access your calendar and admin panel.';
            } else {
                $errors[] = 'Failed to write config.php. Please check file permissions.';
            }
        }
    }
}

// System checks
$phpVersionOk = version_compare(PHP_VERSION, '8.1.0', '>=');
$jsonExtensionOk = extension_loaded('json');
$dataWritable = is_writable($dataDir);
$cacheWritable = is_writable($cacheDir);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install - Book Me Calendar</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            margin: 0;
            padding: 40px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        h1, h2 { color: #2c3e50; }
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
            background-color: #28a745;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }
        .btn:hover { background-color: #218838; }
        .checklist {
            list-style: none;
            padding: 0;
            margin-bottom: 20px;
        }
        .checklist li {
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
        }
        .badge-ok { color: #28a745; font-weight: bold; }
        .badge-fail { color: #dc3545; font-weight: bold; }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Book Me Calendar Installer</h1>
    <p>This script checks your server environment and sets up your configuration and data storage.</p>

    <h2>Environment Checks</h2>
    <ul class="checklist">
        <li>
            PHP Version >= 8.1 (Current: <?php echo PHP_VERSION; ?>)
            <span class="<?php echo $phpVersionOk ? 'badge-ok' : 'badge-fail'; ?>">
                <?php echo $phpVersionOk ? 'OK' : 'FAIL'; ?>
            </span>
        </li>
        <li>
            JSON Extension
            <span class="<?php echo $jsonExtensionOk ? 'badge-ok' : 'badge-fail'; ?>">
                <?php echo $jsonExtensionOk ? 'OK' : 'MISSING'; ?>
            </span>
        </li>
        <li>
            Data Directory Writable (<code>/data</code>)
            <span class="<?php echo $dataWritable ? 'badge-ok' : 'badge-fail'; ?>">
                <?php echo $dataWritable ? 'OK' : 'NOT WRITABLE'; ?>
            </span>
        </li>
        <li>
            Cache Directory Writable (<code>/cache</code>)
            <span class="<?php echo $cacheWritable ? 'badge-ok' : 'badge-fail'; ?>">
                <?php echo $cacheWritable ? 'OK' : 'NOT WRITABLE'; ?>
            </span>
        </li>
    </ul>

    <?php if (!empty($errors)): ?>
        <div class="alert-error">
            <?php foreach ($errors as $err): ?>
                <div><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($installed): ?>
        <div class="alert-success">
            <?php foreach ($messages as $msg): ?>
                <div><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endforeach; ?>
        </div>
        <p>
            <a href="index.php" class="btn" style="text-align: center; display: block; text-decoration: none; margin-bottom: 10px;">Go to Calendar</a>
            <a href="admin.php" class="btn" style="text-align: center; display: block; text-decoration: none; background-color: #007bff;">Go to Admin Panel</a>
        </p>
    <?php elseif ($phpVersionOk && $jsonExtensionOk): ?>
        <h2>Configuration Setup</h2>
        <form method="POST">
            <div class="form-group">
                <label for="app_name">Calendar / App Name:</label>
                <input type="text" id="app_name" name="app_name" value="Book Me Calendar" required>
            </div>
            <div class="form-group">
                <label for="admin_user">Admin Username:</label>
                <input type="text" id="admin_user" name="admin_user" value="admin" required>
            </div>
            <div class="form-group">
                <label for="admin_pass">Admin Password:</label>
                <input type="password" id="admin_pass" name="admin_pass" required>
            </div>
            <button type="submit" class="btn">Run Installation</button>
        </form>
    <?php else: ?>
        <div class="alert-error">
            Your server does not meet the minimum requirements to install Book Me Calendar.
        </div>
    <?php endif; ?>
</div>

</body>
</html>