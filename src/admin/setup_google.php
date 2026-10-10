<?php
// src/admin/setup_google.php - Real Google Calendar OAuth Enrollment Wizard
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

// Handle Google OAuth Callback in Step 2 if 'code' parameter is returned from Google
if ($step === 2 && isset($_GET['code'])) {
    $authCode = $_GET['code'];
    $clientId = isset($config['calendar']['client_id']) ? trim($config['calendar']['client_id']) : '';
    $clientSecret = isset($config['calendar']['client_secret']) ? trim($config['calendar']['client_secret']) : '';
    $redirectUri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[PHP_SELF]?step=2";

    if (empty($clientId) || empty($clientSecret)) {
        $errorMsg = 'Google Client ID and Client Secret must be configured in Step 1 before authorizing.';
        $step = 1;
    } else {
        $tokenEndpoint = 'https://oauth2.googleapis.com/token';
        $postData = http_build_query(array(
            'code' => $authCode,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code'
        ));

        $ch = curl_init($tokenEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $responseData = json_decode($response, true);

        if ($httpCode === 200 && isset($responseData['access_token'])) {
            $tokens['access_token'] = $responseData['access_token'];
            if (isset($responseData['refresh_token'])) {
                $tokens['refresh_token'] = $responseData['refresh_token'];
            }
            $expiresIn = isset($responseData['expires_in']) ? (int)$responseData['expires_in'] : 3600;
            $tokens['token_expiry'] = date('Y-m-d H:i:s', time() + $expiresIn);
            $tokens['updated_at'] = date('Y-m-d H:i:s');
            $tokens['updated_by'] = $currentAdmin;

            $fp = @fopen($tokensFile, 'c+b');
            if ($fp && @flock($fp, LOCK_EX)) {
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                fflush($fp);
                flock($fp, LOCK_UN);
                fclose($fp);
            }

            log_audit_action($currentAdmin, 'GOOGLE_OAUTH_EXCHANGE', 'Successfully exchanged OAuth authorization code for live tokens.');
            header('Location: setup_google.php?step=3');
            exit;
        } else {
            $errorMsg = 'Failed to exchange authorization code with Google. (HTTP Code: ' . $httpCode . '). Please verify your Client ID and Client Secret.';
            $step = 1;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        exit('CSRF verification failed.');
    }

    if (!isset($config['calendar'])) { $config['calendar'] = array(); }

    if ($step === 1) {
        $ownerName = trim(isset($_POST['owner_name']) ? $_POST['owner_name'] : '');
        $notificationEmail = trim(isset($_POST['notification_email']) ? $_POST['notification_email'] : '');
        $clientId = trim(isset($_POST['client_id']) ? $_POST['client_id'] : '');
        $clientSecret = trim(isset($_POST['client_secret']) ? $_POST['client_secret'] : '');
        $calendarId = trim(isset($_POST['calendar_id']) ? $_POST['calendar_id'] : 'primary');

        if (empty($clientId)) {
            $errorMsg = 'Client ID is required.';
        } else {
            $config['calendar']['owner_name'] = $ownerName;
            $config['calendar']['notification_email'] = $notificationEmail;
            $config['calendar']['client_id'] = $clientId;
            if (!empty($clientSecret)) {
                $config['calendar']['client_secret'] = $clientSecret;
            }
            $config['calendar']['calendar_id'] = $calendarId;
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
            header('Location: setup_google.php?step=2');
            exit;
        }
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
    <title>Google Calendar OAuth Enrollment Wizard</title>
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
        .btn-google { background: #4285F4; color: #fff; display: inline-block; padding: 12px 24px; border-radius: 4px; font-weight: bold; text-decoration: none; margin-top: 15px; }
        .btn-google:hover { background: #357ae8; }
        .alert { background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px; font-weight: 500; }
        .error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px; font-weight: 500; }
        .help-text { font-size: 13px; color: #666; margin-top: 5px; }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/header.php'; ?>

    <div class="card">
        <h2>Google Calendar OAuth Enrollment Wizard</h2>
        <p>Connect your Google Calendar via official OAuth authentication to enable seamless live synchronization.</p>

        <div class="steps-indicator">
            <div class="step-badge <?php echo $step === 1 ? 'active' : ''; ?>">Step 1: Credentials</div>
            <div class="step-badge <?php echo $step === 2 ? 'active' : ''; ?>">Step 2: OAuth Grant</div>
            <div class="step-badge <?php echo $step === 3 ? 'active' : ''; ?>">Step 3: Cache & Finish</div>
        </div>

        <?php if (!empty($successMsg)): ?>
            <div class="alert"><?php echo $successMsg; ?></div>
            <p><a href="config.php" class="btn">Return to Configuration</a></p>
        <?php else: ?>

            <?php if (!empty($errorMsg)): ?><div class="error"><?php echo htmlspecialchars($errorMsg); ?></div><?php endif; ?>

            <?php if ($step === 1): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="step" value="1">

                    <h3>Step 1: Google API Credentials & Identity</h3>
                    <p class="help-text">Enter your Google Cloud Console OAuth Client credentials and calendar details. Need help finding these? <a href="google_guide.php" target="_blank">📖 View Illustrated Guide</a></p>
                    
                    <div class="form-group" style="margin-top: 20px;">
                        <label>Calendar Owner / Administrator Name</label>
                        <input type="text" name="owner_name" value="<?php echo htmlspecialchars(isset($config['calendar']['owner_name']) ? $config['calendar']['owner_name'] : ''); ?>" placeholder="e.g. Dr. Jane Doe" required>
                    </div>
                    <div class="form-group">
                        <label>Notification Email</label>
                        <input type="email" name="notification_email" value="<?php echo htmlspecialchars(isset($config['calendar']['notification_email']) ? $config['calendar']['notification_email'] : ''); ?>" placeholder="e.g. calendar@clinic.com" required>
                    </div>
                    <div class="form-group">
                        <label>Google OAuth Client ID</label>
                        <input type="text" name="client_id" value="<?php echo htmlspecialchars(isset($config['calendar']['client_id']) ? $config['calendar']['client_id'] : ''); ?>" placeholder="e.g. 123456789-abc.apps.googleusercontent.com" required>
                    </div>
                    <div class="form-group">
                        <label>Google OAuth Client Secret</label>
                        <input type="password" name="client_secret" value="<?php echo htmlspecialchars(isset($config['calendar']['client_secret']) ? $config['calendar']['client_secret'] : ''); ?>" placeholder="Client secret string" required>
                    </div>
                    <div class="form-group">
                        <label>Google Calendar ID</label>
                        <input type="text" name="calendar_id" value="<?php echo htmlspecialchars(isset($config['calendar']['calendar_id']) ? $config['calendar']['calendar_id'] : 'primary'); ?>" required>
                    </div>
                    
                    <button type="submit" class="btn">Next Step &rarr;</button>
                </form>

            <?php elseif ($step === 2): ?>
                <h3>Step 2: Google Account Authorization</h3>
                <p class="help-text">Click below to authenticate securely with Google and grant calendar synchronization access.</p>

                <?php
                $clientId = isset($config['calendar']['client_id']) ? trim($config['calendar']['client_id']) : '';
                $redirectUri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[PHP_SELF]?step=2";
                
                if (empty($clientId)) {
                    echo '<div class="error">Error: Client ID is missing. Please return to Step 1 and enter your Google OAuth Client ID.</div>';
                    echo '<p><a href="setup_google.php?step=1" class="btn btn-secondary">&larr; Back to Step 1</a></p>';
                } else {
                    $googleAuthUrl = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query(array(
                        'client_id' => $clientId,
                        'redirect_uri' => $redirectUri,
                        'response_type' => 'code',
                        'scope' => 'https://www.googleapis.com/auth/calendar',
                        'access_type' => 'offline',
                        'prompt' => 'consent'
                    ));
                ?>
                    <div style="margin: 30px 0; text-align: center;">
                        <a href="<?php echo htmlspecialchars($googleAuthUrl); ?>" class="btn-google">
                            🔒 Authorize with Google Calendar
                        </a>
                    </div>

                    <p style="margin-top: 20px;">
                        <a href="setup_google.php?step=1" class="btn btn-secondary">&larr; Back to Step 1</a>
                    </p>
                <?php } ?>

            <?php elseif ($step === 3): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="step" value="3">

                    <h3>Step 3: Calendar Cache & Finalization</h3>
                    <p class="help-text">OAuth tokens successfully acquired and stored securely in <code>src/data/tokens.json</code>.</p>
                    
                    <div class="form-group" style="margin-top: 20px;">
                        <label>Calendar Cache Refresh Interval (seconds)</label>
                        <input type="number" name="cache_refresh_interval" value="<?php echo htmlspecialchars(isset($config['calendar']['cache_refresh_interval']) ? $config['calendar']['cache_refresh_interval'] : 3600); ?>" min="60" step="60" required>
                    </div>

                    <a href="setup_google.php?step=2" class="btn btn-secondary">&larr; Back</a>
                    <button type="submit" class="btn" style="background: #28a745;">Complete Enrollment ✓</button>
                </form>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>
</body>
</html>