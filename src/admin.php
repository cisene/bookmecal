<?php
// admin.php - Dashboard with 24-Hour Late Cancellation Enforcement & Admin Alerts
require_once 'db.php';
require_once 'i18n.php';
require_once 'tz_helper.php';

$adminLang = 'sv';
$adminTz   = 'Europe/Stockholm';
$adminEmail = 'admin@clinic.com';

function translateAdmin(string $key, array $replacements = [], string $lang = 'sv'): string {
    $langFilePath = __DIR__ . "/lang/{$lang}.json";
    if (!file_exists($langFilePath)) {
        $langFilePath = __DIR__ . "/lang/sv.json";
    }
    $translations = json_decode(file_get_contents($langFilePath), true) ?? [];
    $text = $translations[$key] ?? $key;

    foreach ($replacements as $placeholder => $value) {
        $text = str_replace('{' . $placeholder . '}', $value, $text);
    }
    return $text;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $action    = $_POST['action'] ?? '';

    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE booking_requests SET status = 'approved' WHERE id = ?");
        $stmt->execute([$bookingId]);
        
        require_once 'gcal_helper.php';
        syncBookingToGCal($bookingId, $pdo);

    } elseif ($action === 'update_attendance') {
        $requestedStatus = $_POST['appointment_status'] ?? 'scheduled';
        $notes           = trim($_POST['notes'] ?? '');

        $stmt = $pdo->prepare("
            SELECT b.*, s.name AS service_name, s.price 
            FROM booking_requests b 
            JOIN services s ON b.service_id = s.id 
            WHERE b.id = ?
        ");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($booking) {
            $previousStatus = $booking['appointment_status'];
            $finalStatus    = $requestedStatus;

            // Enforce 24-Hour Cancellation Rule
            if ($requestedStatus === 'cancelled' && $previousStatus !== 'cancelled') {
                $now = new DateTime('now', new DateTimeZone('UTC'));
                $appointmentTime = new DateTime($booking['start_datetime'], new DateTimeZone('UTC'));
                
                // Calculate hours remaining until appointment
                $hoursUntilAppointment = ($appointmentTime->getTimestamp() - $now->getTimestamp()) / 3600;

                // If cancellation is within 24 hours, reclassify as late_no_show (billable)
                if ($hoursUntilAppointment < 24) {
                    $finalStatus = 'late_no_show';
                    $lateNotice = " [System Notice: Cancelled within 24h threshold (" . round($hoursUntilAppointment, 1) . "h remaining). Full charge applies.]";
                    $notes .= $lateNotice;
                }
            }

            // Persist updated status and notes
            $updateStmt = $pdo->prepare("UPDATE booking_requests SET appointment_status = ?, notes = ? WHERE id = ?");
            $updateStmt->execute([$finalStatus, $notes, $bookingId]);

            // Dispatch admin notification if status moved to cancelled or late_no_show
            if (($finalStatus === 'cancelled' || $finalStatus === 'late_no_show') && $previousStatus === 'scheduled') {
                $formattedTime = formatLocalizedDateTime($booking['start_datetime'], $adminTz, $adminLang);

                $subject = translateAdmin('admin_cancellation_subject', [
                    'id'   => $booking['id'],
                    'name' => $booking['client_name']
                ], $adminLang);

                $body = translateAdmin('admin_cancellation_body', [
                    'id'    => $booking['id'],
                    'name'  => $booking['client_name'],
                    'time'  => $formattedTime,
                    'notes' => $notes ?: 'N/A'
                ], $adminLang);

                $headers  = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/plain; charset=UTF-8\r\n";
                $headers .= "From: Clinic Booking <noreply@clinic.com>\r\n";

                @mail($adminEmail, $subject, $body, $headers);
            }
        }
    }
}

$stmt = $pdo->query("
    SELECT b.*, s.name AS service_name, s.price, s.duration_minutes, s.buffer_minutes 
    FROM booking_requests b 
    JOIN services s ON b.service_id = s.id 
    ORDER BY b.start_datetime DESC
");
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Booking & Attendance</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 20px; background: #f4f6f9; }
        .container { max-width: 1200px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; background: #fff; margin-top: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th, td { padding: 12px; border: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
        th { background: #edf2f7; font-weight: 600; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.85em; font-weight: bold; display: inline-block; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-approved { background: #d4edda; color: #155724; }
        .btn-approve { background: #28a745; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; }
        .btn-save { background: #0066cc; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; margin-top: 4px; }
        .late-flag { color: #c53030; font-weight: bold; font-size: 0.85em; display: block; margin-top: 4px; }
    </style>
</head>
<body>

<div class="container">
    <h2>Appointment Queue & Attendance Management</h2>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Client & Lang/TZ</th>
                <th>Service Details</th>
                <th>Policy Agreement</th>
                <th>Approval State</th>
                <th>Attendance Status</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($bookings as $b): ?>
                <tr>
                    <td><strong>#<?= $b['id'] ?></strong></td>
                    <td>
                        <strong><?= htmlspecialchars($b['client_name']) ?></strong><br>
                        <small><?= htmlspecialchars($b['client_email']) ?></small><br>
                        <small>Lang: <strong><?= strtoupper($b['preferred_language']) ?></strong> | TZ: <?= $b['client_timezone'] ?></small>
                    </td>
                    <td>
                        <?= htmlspecialchars($b['service_name']) ?><br>
                        <small>Work: <?= $b['duration_minutes'] ?>m | Clean-up: <?= $b['buffer_minutes'] ?>m</small><br>
                        <small>Local Time: <?= formatLocalizedDateTime($b['start_datetime'], $b['client_timezone'], $b['preferred_language']) ?></small>
                    </td>
                    <td>
                        <?= $b['policy_accepted'] ? '<span style="color:green; font-weight:bold;">Agreed (Full Charge)</span>' : '<span style="color:red;">No</span>' ?>
                    </td>
                    <td>
                        <span class="badge badge-<?= $b['status'] ?>"><?= strtoupper($b['status']) ?></span>
                        <?php if ($b['status'] === 'pending'): ?>
                            <form method="POST" style="margin-top:5px;">
                                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn-approve">Approve & Sync GCal</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST">
                            <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                            <input type="hidden" name="action" value="update_attendance">
                            <input type="hidden" name="notes" value="<?= htmlspecialchars($b['notes'] ?? '') ?>">
                            <select name="appointment_status" onchange="this.form.submit()">
                                <option value="scheduled" <?= $b['appointment_status'] === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                <option value="attended" <?= $b['appointment_status'] === 'attended' ? 'selected' : '' ?>>Attended</option>
                                <option value="late_no_show" <?= $b['appointment_status'] === 'late_no_show' ? 'selected' : '' ?>>Late / No-Show (Bill Full)</option>
                                <option value="cancelled" <?= $b['appointment_status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </form>
                        <?php if ($b['appointment_status'] === 'late_no_show'): ?>
                            <span class="late-flag">⚠️ Billable (Late / Missed)</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST">
                            <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                            <input type="hidden" name="action" value="update_attendance">
                            <input type="hidden" name="appointment_status" value="<?= $b['appointment_status'] ?>">
                            <input type="text" name="notes" value="<?= htmlspecialchars($b['notes'] ?? '') ?>" placeholder="Add note...">
                            <button type="submit" class="btn-save">Save</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>