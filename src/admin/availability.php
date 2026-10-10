<?php
// src/admin/availability.php
require_once __DIR__ . '/auth.php';

$availabilityFile = __DIR__ . '/../src/data/booking/availability.json';
$days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
$hours = array();
for ($i = 0; $i < 24; $i++) {
    $hours[] = sprintf('%02d:00', $i);
}

$availabilityData = array();
if (file_exists($availabilityFile)) {
    $decoded = json_decode(file_get_contents($availabilityFile), true);
    if (is_array($decoded)) { $availabilityData = $decoded; }
}

$successMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        exit('CSRF verification failed.');
    }

    $postedSlots = isset($_POST['slots']) ? $_POST['slots'] : array();
    $newMatrix = array();
    $dayIndex = 1;

    foreach ($days as $day) {
        $daySlots = array();
        foreach ($hours as $hour) {
            $daySlots[$hour] = isset($postedSlots[$day][$hour]) ? 1 : 0;
        }
        $newMatrix[$day] = array(
            'day_index' => $dayIndex++,
            'slots' => $daySlots,
            'updated_by' => $currentAdmin
        );
    }

    $fp = fopen($availabilityFile, 'c+b');
    if ($fp && flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($newMatrix, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }
    $availabilityData = $newMatrix;
    $successMsg = 'Availability schedule updated successfully by ' . htmlspecialchars($currentAdmin);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Availability Matrix - Admin</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 1050px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; background: #fff; }
        th, td { padding: 8px 10px; border: 1px solid #dee2e6; text-align: center; }
        th { background: #f8f9fa; text-transform: capitalize; }
        td:first-child { font-weight: bold; background: #f8f9fa; }
        .btn { background: #28a745; color: #fff; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; margin-top: 20px; }
        .alert { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/header.php'; ?>

    <div style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <h2>Graphical Availability Matrix</h2>
        <p>Manage operating hours. Hours 00:00 to 06:00 and 20:00 to 23:00 are pre-marked as unavailable by default.</p>

        <?php if (!empty($successMsg)): ?><div class="alert"><?php echo $successMsg; ?></div><?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <table>
                <thead>
                    <tr>
                        <th>Hour</th>
                        <?php foreach ($days as $day): ?><th><?php echo $day; ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hours as $hour): ?>
                        <tr>
                            <td><?php echo $hour; ?></td>
                            <?php foreach ($days as $day): 
                                $isChecked = isset($availabilityData[$day]['slots'][$hour]) && $availabilityData[$day]['slots'][$hour] == 1;
                            ?>
                                <td>
                                    <input type="checkbox" name="slots[<?php echo $day; ?>][<?php echo $hour; ?>]" value="1" <?php echo $isChecked ? 'checked' : ''; ?>>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="submit" class="btn">Save Availability Schedule</button>
        </form>
    </div>
</div>
</body>
</html>