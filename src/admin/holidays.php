<?php
/**
 * src/admin/holidays.php
 * Administrative panel for managing country-specific holiday schedules.
 */
require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/classes/ResourceProvider.php';

ResourceProvider::initTimezone();
$settings = ResourceProvider::getSettings();
$defaultCountry = $settings['default_country'] ?? 'se';

$allowedCountries = ['se', 'no', 'fi', 'ee', 'lv', 'lt'];
$country = ResourceProvider::sanitizeCountryCode($_GET['country'] ?? $defaultCountry);
if (!in_array($country, $allowedCountries, true)) {
    $country = 'se';
}
$holidaysFile = dirname(__DIR__) . "/holidays/{$country}.json";
$successMessage = '';
$errorMessage = '';

$holidaysData = ResourceProvider::getHolidays($country);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(403);
        exit('CSRF verification failed.');
    }

    $action = $_POST['action'] ?? '';

    try {
        if (!is_array($holidaysData)) {
            $holidaysData = [];
        }

        if ($action === 'add_holiday') {
            $date = trim($_POST['date'] ?? '');
            $title = trim($_POST['title'] ?? '');

            $d = DateTime::createFromFormat('Y-m-d', $date);
            if (!$d || $d->format('Y-m-d') !== $date) {
                throw new Exception('Invalid date format. Use YYYY-MM-DD.');
            }

            if ($title === '') {
                $title = 'Holiday';
            }

            // Append or update
            $holidaysData[] = [
                'date' => $date,
                'title' => $title
            ];

            // Sort chronologically
            usort($holidaysData, function($a, $b) {
                return strcmp($a['date'], $b['date']);
            });

            $dir = dirname($holidaysFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            $payload = json_encode(['country' => $country, 'holidays' => $holidaysData], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            $fp = @fopen($holidaysFile, 'c+b');
            if (!$fp || !@flock($fp, LOCK_EX)) {
                throw new Exception('Failed to acquire file lock for holiday file.');
            }
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $payload);
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);

            $successMessage = 'Holiday successfully added!';
        } elseif ($action === 'delete_holiday') {
            $targetDate = trim($_POST['date'] ?? '');
            $holidaysData = array_values(array_filter($holidaysData, function($h) use ($targetDate) {
                return ($h['date'] ?? '') !== $targetDate;
            }));

            $payload = json_encode(['country' => $country, 'holidays' => $holidaysData], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $fp = @fopen($holidaysFile, 'c+b');
            if ($fp && @flock($fp, LOCK_EX)) {
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, $payload);
                fflush($fp);
                flock($fp, LOCK_UN);
                fclose($fp);
            }
            $successMessage = 'Holiday successfully removed.';
        }
    } catch (Exception $e) {
        $errorMessage = 'An error occurred: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Holiday Management - Admin</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        :root { --primary: #0284c7; --primary-dark: #0369a1; --bg: #f4f7f6; --card-bg: #ffffff; --text: #1e293b; --border: #cbd5e1; }
        body { font-family: system-ui, sans-serif; background-color: var(--bg); color: var(--text); margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: var(--card-bg); padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; }
        h2 { font-size: 1.5rem; margin-top: 0; color: var(--primary-dark); margin-bottom: 1.5rem; }
        .alert { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .form-inline { display: flex; gap: 15px; align-items: flex-end; margin-bottom: 20px; }
        .form-group { flex: 1; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 5px; font-size: 0.9rem; }
        input[type="text"], select { width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box; }
        .btn { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; }
        .btn:hover { background: var(--primary-dark); }
        .btn-danger { background: #dc2626; padding: 6px 12px; font-size: 0.85rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #f1f5f9; font-size: 0.95rem; }
        th { background: #f8fafc; font-weight: 600; }
        .null-val { color: #94a3b8; font-style: italic; }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/header.php'; ?>

    <div class="card">
        <h2>Regional Holiday Management (Country: <?php echo strtoupper(htmlspecialchars($country)); ?>)</h2>

        <?php if (!empty($successMessage)): ?><div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div><?php endif; ?>
        <?php if (!empty($errorMessage)): ?><div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div><?php endif; ?>

        <form method="GET" style="margin-bottom: 20px;">
            <label style="font-weight: 600; margin-bottom: 5px; display: block;">Select Country Region Scope:</label>
            <select name="country" onchange="this.form.submit()" style="max-width: 200px;">
                <option value="se" <?php echo $country === 'se' ? 'selected' : ''; ?>>Sweden (SE)</option>
                <option value="no" <?php echo $country === 'no' ? 'selected' : ''; ?>>Norway (NO)</option>
                <option value="fi" <?php echo $country === 'fi' ? 'selected' : ''; ?>>Finland (FI)</option>
                <option value="ee" <?php echo $country === 'ee' ? 'selected' : ''; ?>>Estonia (EE)</option>
                <option value="lv" <?php echo $country === 'lv' ? 'selected' : ''; ?>>Latvia (LV)</option>
                <option value="lt" <?php echo $country === 'lt' ? 'selected' : ''; ?>>Lithuania (LT)</option>
            </select>
        </form>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <input type="hidden" name="action" value="add_holiday">

            <div class="form-inline">
                <div class="form-group">
                    <label>Date (YYYY-MM-DD)</label>
                    <input type="text" name="date" placeholder="2026-12-24" maxlength="10" required pattern="^\d{4}-\d{2}-\d{2}$">
                </div>
                <div class="form-group">
                    <label>Holiday Title / Description</label>
                    <input type="text" name="title" placeholder="Christmas Eve" required>
                </div>
                <div>
                    <button type="submit" class="btn">Add Holiday</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Configured Holidays</h2>
        <?php if (empty($holidaysData)): ?>
            <p class="null-val">No holidays configured for this region (NULL).</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Title</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($holidaysData as $h): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($h['date'] ?? ''); ?></code></td>
                            <td><strong><?php echo htmlspecialchars($h['title'] ?? ''); ?></strong></td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this holiday?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                    <input type="hidden" name="action" value="delete_holiday">
                                    <input type="hidden" name="date" value="<?php echo htmlspecialchars($h['date'] ?? ''); ?>">
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>