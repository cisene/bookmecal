<?php
// send_reminders.php - CLI/Cron script for sending 24-hour booking reminders

// Ensure script is executed via CLI for safety (can be removed if triggered via web cron URL)
if (php_sapi_name() !== 'cli') {
    // Optional: Allow web execution with a secret token, but standard is CLI
}

$config = require_once __DIR__ . '/config.php';

$dataDir = $config['storage']['data_dir'] ?? __DIR__ . '/data';
$bookingsFile = $dataDir . '/bookings.json';

if (!file_exists($bookingsFile)) {
    echo "No bookings file found.\n";
    exit;
}

$fp = fopen($bookingsFile, 'c+b');
if (!$fp) {
    echo "Could not open bookings file.\n";
    exit;
}

if (flock($fp, LOCK_EX)) {
    $content = stream_get_contents($fp);
    $bookings = json_decode($content, true) ?: [];

    $now = new DateTime();
    $tomorrow = (clone $now)->modify('+24 hours');
    
    $updated = false;
    $fromEmail = $config['smtp']['from_email'] ?? 'noreply@example.com';
    $fromName = $config['smtp']['from_name'] ?? 'Book Me Calendar';

    foreach ($bookings as &$b) {
        // Only process approved bookings that have not yet received a reminder
        if (($b['status'] ?? '') === 'approved' && empty($b['reminder_sent'])) {
            $startTime = new DateTime($b['start_datetime']);
            
            // Check if the booking starts within the next 24 hours and hasn't already passed
            if ($startTime >= $now && $startTime <= $tomorrow) {
                $clientEmail = $b['client_email'];
                $clientName = $b['client_name'];
                $startStr = $b['start_datetime'];
                $endStr = $b['end_datetime'];

                $subject = 'Reminder: Your upcoming booking tomorrow - Book Me Calendar';
                $message = "Hello {$clientName},\n\n"
                    . "This is a friendly reminder that you have an upcoming approved booking tomorrow:\n"
                    . "Start: {$startStr}\n"
                    . "End: {$endStr}\n\n"
                    . "We look forward to seeing you!\n\n"
                    . "Best regards,\nBook Me Calendar Team";

                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
                $headers .= "Reply-To: {$fromEmail}\r\n";

                if (@mail($clientEmail, $subject, $message, $headers)) {
                    $b['reminder_sent'] = true;
                    $updated = true;
                    echo "Reminder successfully sent to {$clientEmail} for booking ID {$b['id']}.\n";
                } else {
                    echo "Failed to send reminder to {$clientEmail} for booking ID {$b['id']}.\n";
                }
            }
        }
    }
    unset($b);

    if ($updated) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($bookings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($fp);
    }

    flock($fp, LOCK_UN);
    fclose($fp);
    echo "Reminder check completed.\n";
} else {
    fclose($fp);
    echo "Could not acquire exclusive lock on bookings file.\n";
}