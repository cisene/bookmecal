<?php
// src/admin/setup_google.php - Real Google Calendar Sync Enrollment Wizard
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

$step = isset($_REQUEST['step']) ? (int)$_REQUEST['step'] : 1;
if ($step < 1) { $step = 1; }
if ($step > 3) { $step = 3; }

$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        exit('CSRF verification failed.');
    }

    if (!isset($config['calendar'])) { $config['calendar'] = array(); }

    if ($step === 1) {
        // Step 1: Real Identity & Communication Parameters -> Saved to config.json
        $config['calendar']['owner_name'] = trim(isset($_POST['owner_name']) ? $_POST['owner_name'] : '');
        $config['calendar']['notification_email'] = trim(isset($_POST['notification_email']) ? $_POST['notification_email'] : '');
        $config['calendar']['google_user'] = trim(isset($_POST['google_user']) ? $_POST['google_user'] : '');
        $config['calendar']['calendar_id'] = trim(isset($_POST['calendar_id']) ? $_POST['calendar_id'] : 'primary');
        $config['calendar']['last_modified_by'] = $currentAdmin;

        $fp = @fopen($configFile, 'c+b');
        if ($fp && @flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        $step = 2;
    } elseif ($step === 2) {
        // Step 2: Real OAuth Credentials & Tokens -> Saved strictly to tokens.json
        $tokens['access_token'] = trim(isset($_POST['access_token']) ? $_POST['access_token'] : '');
        $tokens['refresh_token'] = trim(isset($_POST['refresh_token']) ? $_POST['refresh_token'] : '');
        $tokens['token_expiry'] = trim(isset($_POST['token_expiry']) ? $_POST['token_expiry'] : date('Y-m-d H:i:s', strtotime('+1 hour')));
        $tokens['updated_at'] = date('Y-m-d H:i:s');
        $tokens['updated_by'] = $currentAdmin;

        if (empty($tokens['access_token'])) {
            $errorMsg = 'Access token cannot be empty for a real calendar enrollment.';
            $step = 2;
        } else {
            $fp = @fopen($tokensFile, 'c+b');
            if ($fp && @flock($fp, LOCK_EX)) {
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                fflush($fp);
                flock($fp, LOCK_UN);
                fclose($fp);
            }
            $step = 3;
        }
    } elseif ($step === 3) {
        // Step 3: Cache and Finalize -> config.json
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

        log_audit_action($currentAdmin, 'GOOGLE_CALENDAR_ENROLL', 'Successfully completed real Google Calendar identity enrollment and token linkage.');
        $successMsg = 'Google Calendar identity and sync tokens successfully enrolled and secured!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Real Google Calendar Enrollment Wizard</title>
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
        .error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px; font-weight: 500; }
        .help-text { font-size: 13px; color: #666; margin-top: 5px; }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/header.php'; ?>

    <div class="card">
        <h2>Real Google Calendar Enrollment Wizard</h2>
        <p>Enroll your real calendar identity parameters into config and bind live OAuth synchronization tokens securely to tokens.json.</p>

        <div class="steps-indicator">
            <div class="step-badge <?php echo $step === 1 ? 'active' : ''; ?>">Step 1: Identity & Config</div>
            <div class="step-badge <?php echo $step === 2 ? 'active' : ''; ?>">Step 2: OAuth Tokens</div>
            <div class="step-badge <?php echo $step === 3 ? 'active' : ''; ?>">Step 3: Cache & Finish</div>
        </div>

        <?php if (!empty($successMsg)): ?>
            <div class="alert"><?php echo $successMsg; ?></div>
            <p><a href="config.php" class="btn">Return to Configuration</a></p>
        <?php else: ?>

            <?php if (!empty($errorMsg)): ?><div class="error"><?php echo $errorMsg; ?></div><?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="step" value="<?php echo $step; ?>">

                <?php if ($step === 1): ?>
                    <h3>Step 1: Real Identity & Communication Parameters</h3>
                    <p class="help-text">Saved to <code>src/data/config.json</code> for ongoing system operations and notifications.</p>
                    
                    <div class="form-group" style="margin-top: 20px;">
                        <label>Calendar Owner / Administrator Name</label>
                        <input type="text" name="owner_name" value="<?php echo htmlspecialchars(isset($config['calendar']['owner_name']) ? $config['calendar']['owner_name'] : ''); ?>" placeholder="e.g. Dr. Jane Doe" required>
                    </div>
                    <div class="form-group">
                        <label>Notification & Communication Email</label>
                        <input type="email" name="notification_email" value="<?php echo htmlspecialchars(isset($config['calendar']['notification_email']) ? $config['calendar']['notification_email'] : ''); ?>" placeholder="e.g. calendar@clinic.com" required>
                    </div>
                    <div class="form-group">
                        <label>Google Service Account / Account User Email</label>
                        <input type="text" name="google_user" value="<?php echo htmlspecialchars(isset($config['calendar']['google_user']) ? $config['calendar']['google_user'] : ''); ?>" placeholder="e.g. service-bot@project.iam.gserviceaccount.com" required>
                    </div>
                    <div class="form-group">
                        <label>Google Calendar ID</label>
                        <input type="text" name="calendar_id" value="<?php echo htmlspecialchars(isset($config['calendar']['calendar_id']) ? $config['calendar']['calendar_id'] : 'primary'); ?>" placeholder="primary or custom calendar ID hash" required>
                    </div>
                    
                    <button type="submit" class="btn">Next Step &rarr;</button>

                <?php elseif ($step === 2): ?>
                    <h3>Step 2: Real OAuth Tokens Enrollment</h3>
                    <p class="help-text">Saved securely to <code>src/data/tokens.json</code> to separate tokens from configuration storage.</p>
                    
                    <div class="form-group" style="margin-top: 20px;">
                        <label>Live OAuth Access Token</label>
                        <input type="text" name="access_token" value="<?php echo htmlspecialchars(isset($tokens['access_token']) ? $tokens['access_token'] : ''); ?>" placeholder="ya29.a0AfH6SM..." required>
                    </div>
                    <div class="form-group">
                        <label>Live OAuth Refresh Token</label>
                        <input type="text" name="refresh_token" value="<?php echo htmlspecialchars(isset($tokens['refresh_token']) ? $tokens['refresh_token'] : ''); ?>" placeholder="1//04..." required>
                    </div>
                    <div class="form-group">
                        <label>Current Token Expiry Time</label>
                        <input type="text" name="token_expiry" value="<?php echo htmlspecialchars(isset($tokens['token_expiry']) ? $tokens['token_expiry'] : date('Y-m-d H:i:s', strtotime('+1 hour'))); ?>" placeholder="YYYY-MM-DD HH:MM:SS" required>
                    </div>

                    <a href="setup_google.php?step=1" class="btn btn-secondary">&larr; Back</a>
                    <button type="submit" class="btn">Next Step &rarr;</button>

                <?php elseif ($step === 3): ?>
                    <h3>Step 3: Calendar Cache & Finalization</h3>
                    <p class="help-text">Configure caching parameters stored in <code>src/data/config.json</code>.</p>
                    
                    <div class="form-group" style="margin-top: 20px;">
                        <label>Calendar Cache Refresh Interval (seconds)</label>
                        <input type="number" name="cache_refresh_interval" value="<?php echo htmlspecialchars(isset($config['calendar']['cache_refresh_interval']) ? $config['calendar']['cache_refresh_interval'] : 3600); ?>" min="60" step="60" required>
                    </div>

                    <a href="setup_google.php?step=2" class="btn btn-secondary">&larr; Back</a>
                    <button type="submit" class="btn" style="background: #28a745;">Complete Real Enrollment ✓</button>

                <?php endif; ?>
            </form>

        <?php endif; ?>
    </div>
</div>
</body>
</html>