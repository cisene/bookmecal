<?php
/**
 * src/admin/setup_google.php
 * Three-step Google Calendar OAuth enrollment: credentials, authorization, cache settings.
 */

require_once __DIR__ . '/auth.php';

$configFile = ResourceProvider::dataPath('config.json');
$tokensFile = ResourceProvider::dataPath('tokens.json');
$config = ResourceProvider::getConfig() ?? [];
$calendar = is_array($config['calendar'] ?? null) ? $config['calendar'] : [];

$step = min(3, max(1, (int)request_string($_REQUEST, 'step', '1')));
$redirectUri = ((($_SERVER['HTTPS'] ?? '') === 'on') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['SCRIPT_NAME'] ?? '') . '?step=2';
$flash = [];
$completed = false;

/**
 * Merges $changes into the 'calendar' section of config.json.
 */
function save_calendar_config(string $file, array $changes): bool {
    return JsonStore::update($file, function (array $config) use ($changes) {
        $config['calendar'] = array_merge(is_array($config['calendar'] ?? null) ? $config['calendar'] : [], $changes);
        return $config;
    });
}

/**
 * Exchanges an OAuth authorization code for tokens; returns the decoded response or null.
 */
function exchange_oauth_code(string $code, string $clientId, string $clientSecret, string $redirectUri): ?array {
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = is_string($response) ? json_decode($response, true) : null;
    return ($status === 200 && isset($data['access_token'])) ? $data : null;
}

// Step 2 callback from Google: verify state (login-CSRF protection), then exchange the code
if ($step === 2 && isset($_GET['code'])) {
    $code = request_string($_GET, 'code');
    $expectedState = $_SESSION['google_oauth_state'] ?? '';
    unset($_SESSION['google_oauth_state']);

    if ($code === '' || $expectedState === '' || !hash_equals($expectedState, request_string($_GET, 'state'))) {
        $flash[] = flash_error('OAuth state mismatch. Please start the authorization again.');
    } elseif (empty($calendar['client_id']) || empty($calendar['client_secret'])) {
        $flash[] = flash_error('Google Client ID and Client Secret must be configured in step 1 before authorizing.');
        $step = 1;
    } else {
        $tokens = exchange_oauth_code($code, $calendar['client_id'], $calendar['client_secret'], $redirectUri);
        if ($tokens === null) {
            $flash[] = flash_error('Failed to exchange the authorization code with Google. Verify the Client ID and Client Secret.');
            $step = 1;
        } else {
            JsonStore::update($tokensFile, function (array $stored) use ($tokens, $currentAdmin) {
                return array_merge($stored, [
                    'access_token' => $tokens['access_token'],
                    'token_expiry' => date('Y-m-d H:i:s', time() + (int)($tokens['expires_in'] ?? 3600)),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'updated_by' => $currentAdmin,
                ], isset($tokens['refresh_token']) ? ['refresh_token' => $tokens['refresh_token']] : []);
            });
            @chmod($tokensFile, 0600);
            log_audit_action($currentAdmin, 'GOOGLE_OAUTH_EXCHANGE', 'Exchanged OAuth authorization code for tokens.');
            header('Location: setup_google.php?step=3');
            exit;
        }
    }
}

if (is_post()) {
    require_csrf();

    if ($step === 1) {
        $changes = [
            'owner_name' => request_string($_POST, 'owner_name'),
            'notification_email' => request_string($_POST, 'notification_email'),
            'client_id' => request_string($_POST, 'client_id'),
            'calendar_id' => request_string($_POST, 'calendar_id', 'primary'),
            'last_modified_by' => $currentAdmin,
        ];
        $secret = request_string($_POST, 'client_secret');
        if ($secret !== '') {
            $changes['client_secret'] = $secret;
        }

        if ($changes['client_id'] === '') {
            $flash[] = flash_error('Client ID is required.');
        } elseif ($changes['notification_email'] !== '' && filter_var($changes['notification_email'], FILTER_VALIDATE_EMAIL) === false) {
            $flash[] = flash_error('Notification email is not a valid address.');
        } elseif (save_calendar_config($configFile, $changes)) {
            header('Location: setup_google.php?step=2');
            exit;
        } else {
            $flash[] = flash_error('Could not write config.json. Check file permissions.');
        }
    } elseif ($step === 3) {
        save_calendar_config($configFile, ['cache_refresh_interval' => max(60, (int)request_string($_POST, 'cache_refresh_interval', '3600'))]);
        log_audit_action($currentAdmin, 'GOOGLE_CALENDAR_ENROLL', 'Completed Google Calendar enrollment wizard.');
        $completed = true;
        $flash[] = flash_success('admin.google.done');
    }
    $calendar = ResourceProvider::getConfig()['calendar'] ?? $calendar;
}

$authUrl = null;
if ($step === 2 && !empty($calendar['client_id'])) {
    $_SESSION['google_oauth_state'] = bin2hex(random_bytes(16));
    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $calendar['client_id'],
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'https://www.googleapis.com/auth/calendar',
        'access_type' => 'offline',
        'prompt' => 'consent',
        'state' => $_SESSION['google_oauth_state'],
    ]);
}

render_admin('setup_google', [
    'title' => 'admin.google.title',
    'flash' => $flash,
    'step' => $step,
    'completed' => $completed,
    'authUrl' => $authUrl,
    'calendar' => [
        'owner_name' => (string)($calendar['owner_name'] ?? ''),
        'notification_email' => (string)($calendar['notification_email'] ?? ''),
        'client_id' => (string)($calendar['client_id'] ?? ''),
        'has_secret' => !empty($calendar['client_secret']),
        'calendar_id' => (string)($calendar['calendar_id'] ?? 'primary'),
        'cache_refresh_interval' => (int)($calendar['cache_refresh_interval'] ?? 3600),
    ],
]);
