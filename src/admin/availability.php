<?php
// src/admin/availability.php
require_once __DIR__ . '/auth.php';

$availabilityFile = dirname(__DIR__) . '/data/booking/availability.json';
$days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
$hours = array();
for ($i = 0; $i < 24; $i++) {
    $hours[] = sprintf('%02d:00', $i);
}

$availabilityDir = dirname($availabilityFile);
if (!is_dir($availabilityDir)) {
    mkdir($availabilityDir, 0775, true);
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
    
    log_audit_action($currentAdmin, 'AVAILABILITY_UPDATE', 'Updated weekly operating hours matrix.');
    $successMsg = 'Availability schedule updated successfully!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Availability Slots - Admin</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 1100px; margin: 0 auto; }
        .card { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .table-responsive { overflow-x: auto; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { padding: 10px; border: 1px solid #e9ecef; text-align: center; }
        th { background: #f8f9fa; text-transform: capitalize; font-size: 14px; color: #495057; }
        td:first-child { font-weight: bold; background: #f8f9fa; color: #495057; font-size: 13px; }
        
        .slot-label {
            display: block;
            width: 100%;
            height: 32px;
            line-height: 32px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            font-weight: bold;
            transition: all 0.2s ease;
            user-select: none;
        }
        .slot-input { display: none; }
        
        .slot-input + .slot-label {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .slot-input + .slot-label::after { content: "Closed"; }

        .slot-input:checked + .slot-label {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .slot-input:checked + .slot-label::after { content: "Open"; }

        .btn { background: #28a745; color: #fff; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 15px; font-weight: bold; margin-top: 20px; }
        .btn:hover { background: #218838; }
        .alert { background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 15px; font-weight: 500; }
        .legend { display: flex; gap: 20px; margin-bottom: 15px; font-size: 13px; align-items: center; }
        .legend-box { width: 16px; height: 16px; border-radius: 3px; display: inline-block; vertical-align: middle; }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/header.php'; ?>

    <div class="card">
        <h2>Availability Slots</h2>
        <p>Click on any hourly block to toggle its availability state. Night hours (00:00–06:00) and late evenings (20:00–23:00) are closed by default.</p>

        <?php if (!empty($successMsg)): ?><div class="alert"><?php echo $successMsg; ?></div><?php endif; ?>

        <div class="legend">
            <div><span class="legend-box" style="background: #d4edda; border: 1px solid #c3e6cb;"></span> Open for Booking</div>
            <div><span class="legend-box" style="background: #f8d7da; border: 1px solid #f5c6cb;"></span> Unavailable / Closed</div>
        </div>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="table-responsive">
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
                                    $uniqueId = "slot_{$day}_{$hour}";
                                ?>
                                    <td>
                                        <input type="checkbox" id="<?php echo $uniqueId; ?>" class="slot-input" name="slots[<?php echo $day; ?>][<?php echo $hour; ?>]" value="1" <?php echo $isChecked ? 'checked' : ''; ?>>
                                        <label for="<?php echo $uniqueId; ?>" class="slot-label"></label>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn">Save Availability Schedule</button>
        </form>
    </div>
</div>
</body>
</html>