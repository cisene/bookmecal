<?php
// src/admin/setup_google.php - Step-by-step Google Calendar Sync Enrollment Wizard
require_once __DIR__ . '/auth.php';

$configFile = dirname(__DIR__) . '/data/config.json';
$configDir = dirname($configFile);
if (!is_dir($configDir)) {
    @mkdir($configDir, 0775, true);
}

$config = array();
if (file_exists($configFile)) {
    $decoded = json_decode(file_get_contents($configFile), true);
    if (is_array($decoded)) { $config = $decoded; }
}

$step = isset($_REQUEST['step']) ? (int)$_REQUEST['step'] : 1;
if ($step < 1) { $step = 1; }
if ($step > 3) { $step = 3; }

$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        exit('CSRF verification failed.');
    }

    if (!isset($config['calendar'])) { $config['calendar'] = array(); }

    if ($step === 1) {
        $config['calendar']['google_user'] = trim(isset($_POST['google_user']) ? $_POST['google_user'] : '');
        if (!empty($_POST['google_password'])) {
            $config['calendar']['google_password'] = $_POST['google_password'];
        }
        $config['calendar']['last_modified_by'] = $currentAdmin;
        $step = 2;
    } elseif ($step === 2) {
        $config['calendar']['token_expiry'] = trim(isset($_POST['token_expiry']) ? $_POST['token_expiry'] : date('Y-m-d H:i:s', strtotime('+1 year')));
        $config['calendar']['access_token'] = trim(isset($_POST['access_token']) ? $_POST['access_token'] : 'simulated_oauth_token_' . bin2hex(random_bytes(8)));
        $step = 3;
    } elseif ($step === 3) {
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

        log_audit_action($currentAdmin, 'GOOGLE_CALENDAR_ENROLL', 'Completed Google Calendar enrollment wizard successfully.');
        $successMsg = 'Google Calendar sync flow successfully configured and activated!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Google Calendar Enrollment Wizard</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .steps-indicator { display: flex; justify-content: space-between; margin-bottom: 25px; border-bottom: 2px solid #e9ecef; padding-bottom: 10px; }
        .step-badge { font-weight: bold; color: #6c757d; font-size: 14px; }
        .step-badge.active { color: #007bff; border-bottom: 2px solid #007bff; padding-bottom: 8px; margin-bottom: -12px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
        .form-group input { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
        .btn { background: #007bff; color: #fff; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 14px; display: inline-block; text-decoration: none; }
        .btn-secondary { background: #6c757d; margin-right: 10px; }
        .alert { background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px; font-weight: 500; }
        .help-text { font-size: 13px; color: #666; margin-top: 5px; }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/header.php'; ?>

    <div class="card">
        <h2>Google Calendar Sync Enrollment Guide</h2>
        <p>Follow this quick, guided wizard to connect your Google Calendar service account and synchronize appointments automatically.</p>

        <div class="steps-indicator">
            <div class="step-badge <?php echo $step === 1 ? 'active' : ''; ?>">Step 1: Credentials</div>
            <div class="step-badge <?php echo $step === 2 ? 'active' : ''; ?>">Step 2: Authorization</div>
            <div class="step-badge <?php echo $step === 3 ? 'active' : ''; ?>">Step 3: Cache & Finish</div>
        </div>

        <?php if (!empty($successMsg)): ?>
            <div class="alert"><?php echo $successMsg; ?></div>
            <p><a href="config.php" class="btn">Return to Configuration</a></p>
        <?php else: ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="step" value="<?php echo $step; ?>">

                <?php if ($step === 1): ?>
                    <h3>Step 1: Enter Google Service Account Credentials</h3>
                    <p class="help-text">Provide your Google API Service Account email and API secret/password.</p>
                    
                    <div class="form-group" style="margin-top: 20px;">
                        <label>Google Service User / Email</label>
                        <input type="text" name="google_user" value="<?php echo htmlspecialchars(isset($config['calendar']['google_user']) ? $config['calendar']['google_user'] : ''); ?>" placeholder="e.g. booking-bot@project.iam.gserviceaccount.com" required>
                    </div>
                    <div class="form-group">
                        <label>Google Service Secret / Password</label>
                        <input type="password" name="google_password" placeholder="Leave blank to keep existing secret">
                    </div>
                    
                    <button type="submit" class="btn">Next Step &rarr;</button>

                <?php elseif ($step === 2): ?>
                    <h3>Step 2: OAuth Flow & Token Verification</h3>
                    <p class="help-text">Verify token generation and set the active token expiration timestamp.</p>
                    
                    <div class="form-group" style="margin-top: 20px;">
                        <label>Simulated / Active Access Token</label>
                        <input type="text" name="access_token" value="<?php echo htmlspecialchars(isset($config['calendar']['access_token']) ? $config['calendar']['access_token'] : 'ya29.a0AfH6SM...'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Token Expiry Time</label>
                        <input type="text" name="token_expiry" value="<?php echo htmlspecialchars(isset($config['calendar']['token_expiry']) ? $config['calendar']['token_expiry'] : date('Y-m-d H:i:s', strtotime('+1 year'))); ?>" placeholder="YYYY-MM-DD HH:MM:SS" required>
                    </div>

                    <a href="setup_google.php?step=1" class="btn btn-secondary">&larr; Back</a>
                    <button type="submit" class="btn">Next Step &rarr;</button>

                <?php elseif ($step === 3): ?>
                    <h3>Step 3: Calendar Cache & Finalization</h3>
                    <p class="help-text">Configure how often cached calendar sync data is refreshed to optimize API performance.</p>
                    
                    <div class="form-group" style="margin-top: 20px;">
                        <label>Cache Refresh Interval (seconds)</label>
                        <input type="number" name="cache_refresh_interval" value="<?php echo htmlspecialchars(isset($config['calendar']['cache_refresh_interval']) ? $config['calendar']['cache_refresh_interval'] : 3600); ?>" min="60" step="60" required>
                        <div class="help-text">Standard is 3600 seconds (1 hour).</div>
                    </div>

                    <a href="setup_google.php?step=2" class="btn btn-secondary">&larr; Back</a>
                    <button type="submit" class="btn" style="background: #28a745;">Complete Enrollment ✓</button>

                <?php endif; ?>
            </form>

        <?php endif; ?>
    </div>
</div>
</body>
</html>