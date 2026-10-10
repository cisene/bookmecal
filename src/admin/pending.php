<?php
// src/admin/pending.php
require_once __DIR__ . '/auth.php';

$pendingDir = __DIR__ . '/../src/data/pending';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        exit('CSRF verification failed.');
    }

    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $targetId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $targetFile = $pendingDir . '/booking_' . $targetId . '.json';

    if (file_exists($targetFile)) {
        $fp = fopen($targetFile, 'c+b');
        if ($fp && flock($fp, LOCK_EX)) {
            $content = stream_get_contents($fp);
            $booking = json_decode($content, true);
            if (is_array($booking)) {
                $booking['status'] = ($action === 'approve') ? 'approved' : 'rejected';
                $booking['processed_by'] = $currentAdmin;
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode($booking, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                fflush($fp);
            }
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
    header('Location: pending.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pending Bookings - Admin</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 1050px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        th, td { padding: 12px 15px; border-bottom: 1px solid #dee2e6; text-align: left; }
        th { background: #f8f9fa; }
        .btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; color: #fff; font-size: 12px; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/header.php'; ?>

    <div style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <h2>Pending Bookings</h2>
        <?php
        $files = glob($pendingDir . '/booking_*.json');
        if (empty($files)):
        ?>
            <p>No pending booking requests.</p>
        <?php else: ?>
            <table>
                <tr><th>ID</th><th>Client</th><th>Email</th><th>Start</th><th>Status</th><th>Actions</th></tr>
                <?php foreach ($files as $file):
                    $booking = json_decode(file_get_contents($file), true);
                    if (!is_array($booking)) continue;
                ?>
                    <tr>
                        <td><?php echo isset($booking['id']) ? (int)$booking['id'] : 0; ?></td>
                        <td><?php echo htmlspecialchars(isset($booking['client_name']) ? $booking['client_name'] : ''); ?></td>
                        <td><?php echo htmlspecialchars(isset($booking['client_email']) ? $booking['client_email'] : ''); ?></td>
                        <td><?php echo htmlspecialchars(isset($booking['start_datetime']) ? $booking['start_datetime'] : ''); ?></td>
                        <td><?php echo htmlspecialchars(isset($booking['status']) ? $booking['status'] : 'pending'); ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="id" value="<?php echo isset($booking['id']) ? (int)$booking['id'] : 0; ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-success">Approve</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="id" value="<?php echo isset($booking['id']) ? (int)$booking['id'] : 0; ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-danger">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>