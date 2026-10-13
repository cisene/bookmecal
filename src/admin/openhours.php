<?php
/**
 * src/admin/openhours.php
 * Administrative panel for managing venue opening hours (`data/openhours.json`).
 * Removes all fallback default assumptions; unconfigured records evaluate to NULL.
 */
require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/classes/ResourceProvider.php';

ResourceProvider::initTimezone();

$openHoursFile = dirname(__DIR__) . '/data/openhours.json';
$successMessage = '';$errorMessage = '';

$daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

$currentHours = null;
if (file_exists($openHoursFile)) {
    $content = file_get_contents($openHoursFile);
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $content = substr($content, 3);
    }
    $decoded = json_decode($content, true);
    if (is_array($decoded)) {
        $currentHours =$decoded;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '',$_POST['csrf_token'])) {
        http_response_code(403);
        exit('CSRF verification failed.');
    }

    try {
        $postedData = $_POST['hours'] ?? [];$newConfig = [];

        foreach ($daysOfWeek as $day) {$hasData = isset($postedData[$day]);
            if (!$hasData) {
                $newConfig[$day] = [
                    'is_open' => null,
                    'open_time' => null,
                    'close_time' => null
                ];
                continue;
            }

            $isOpen = isset($postedData[$day]['is_open']) ? (bool)$postedData[$day]['is_open'] : null;
            
            $openTimeRaw = trim($postedData[$day]['open_time'] ?? '');$openTime = null;
            if ($openTimeRaw !== '') {
                $dOpen = DateTime::createFromFormat('H:i',$openTimeRaw);
                if ($dOpen && $dOpen->format('H:i') ===$openTimeRaw) {
                    $openTime =$openTimeRaw;
                }
            }

            $closeTimeRaw = trim($postedData[$day]['close_time'] ?? '');$closeTime = null;
            if ($closeTimeRaw !== '') {
                $dClose = DateTime::createFromFormat('H:i',$closeTimeRaw);
                if ($dClose && $dClose->format('H:i') ===$closeTimeRaw) {
                    $closeTime =$closeTimeRaw;
                }
            }

            $newConfig[$day] = [
                'is_open' => $isOpen,
                'open_time' => $openTime,
                'close_time' => $closeTime
            ];
        }

        $jsonEncoded = json_encode($newConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $dir = dirname($openHoursFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        if (!file_exists($openHoursFile)) {
            @file_put_contents($openHoursFile, '{}');
            @chmod($openHoursFile, 0664);
        }

        if (!is_writable($openHoursFile)) {
            throw new Exception("File is not writable. Please check server permissions for: {$openHoursFile}");
        }

        $fp = @fopen($openHoursFile, 'c+b');
        if (!$fp || !@flock($fp, LOCK_EX)) {
            throw new Exception('Failed to acquire file lock for openhours.json. Check file permissions.');
        }

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp,$jsonEncoded);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        if (function_exists('log_audit_action')) {
            log_audit_action(isset($currentAdmin) ?$currentAdmin : 'admin', 'OPENHOURS_UPDATE', 'Updated weekly venue opening hours.');
        }

        $successMessage = 'Opening hours successfully updated!';
        $currentHours =$newConfig;
    } catch (Exception $e) {
        $errorMessage = 'An error occurred while saving: ' .$e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Venue Opening Hours - Admin</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        :root {
            --primary: #0284c7;
            --primary-dark: #0369a1;
            --bg: #f4f7f6;
            --card-bg: #ffffff;
            --text: #1e293b;
            --border: #cbd5e1;
        }
        body { font-family: system-ui, sans-serif; background-color: var(--bg); color: var(--text); margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: var(--card-bg); padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h2 { font-size: 1.5rem; margin-top: 0; color: var(--primary-dark); margin-bottom: 1.5rem; }
        .alert { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .day-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
        .day-name { font-weight: 600; text-transform: capitalize; width: 120px; }
        .time-inputs { display: flex; align-items: center; gap: 10px; }
        input.time-input-24h { width: 80px; padding: 6px 10px; border: 1px solid var(--border); border-radius: 6px; font-family: monospace; text-align: center; }
        input[type="checkbox"] { transform: scale(1.2); cursor: pointer; }
        button.save-btn { background: var(--primary); color: white; border: none; padding: 12px; width: 100%; border-radius: 6px; font-weight: bold; cursor: pointer; margin-top: 25px; }
        button.save-btn:hover { background: var(--primary-dark); }
        .null-badge { color: #94a3b8; font-size: 0.85rem; font-style: italic; }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/header.php'; ?>
    <div class="card">
        <h2>Venue Opening Hours (Strict Null Fallback)</h2>
        <p style="color: #64748b; font-size: 0.9rem; margin-top: -10px; margin-bottom: 20px;">
            Unconfigured days evaluate to <code>NULL</code> with no hardcoded assumptions.
        </p>

        <?php if (!empty($successMessage)): ?><div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div><?php endif; ?>
        <?php if (!empty($errorMessage)): ?><div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div><?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            
            <?php foreach ($daysOfWeek as $day):$dayConfig = isset($currentHours[$day]) ? $currentHours[$day] : null;
                $isOpen =$dayConfig['is_open'] ?? null;
                $openTime =$dayConfig['open_time'] ?? null;
                $closeTime =$dayConfig['close_time'] ?? null;
            ?>
                <div class="day-row">
                    <div class="day-name">
                        <?php echo ucfirst($day); ?><br>
                        <?php if ($dayConfig === null): ?>
                            <span class="null-badge">NULL (Unset)</span>
                        <?php endif; ?>
                    </div>
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer; font-size: 0.9rem;">
                            <input type="checkbox" name="hours[<?php echo $day; ?>][is_open]" value="1" <?php echo ($isOpen === true) ? 'checked' : ''; ?>>
                            Open
                        </label>
                        <div class="time-inputs">
                            <input type="text" class="time-input-24h" name="hours[<?php echo $day; ?>][open_time]" value="<?php echo htmlspecialchars($openTime ?? ''); ?>" placeholder="HH:mm" maxlength="5">
                            <span>to</span>
                            <input type="text" class="time-input-24h" name="hours[<?php echo $day; ?>][close_time]" value="<?php echo htmlspecialchars($closeTime ?? ''); ?>" placeholder="HH:mm" maxlength="5">
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="save-btn">Save Changes</button>
        </form>
    </div>
</div>
</body>
</html>