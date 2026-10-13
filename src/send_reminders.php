<?php
/**
 * src/send_reminders.php
 * CLI/Cron script for checking upcoming bookings and sending automated reminders.
 * Runs under strict timezone rules and null-fallback data integrity.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Access denied. This script can only be run via command line.');
}

require_once __DIR__ . '/classes/ResourceProvider.php';
require_once __DIR__ . '/classes/BookingRepository.php';

ResourceProvider::initTimezone();

$dataDir = __DIR__ . '/data';
$pendingDir = $dataDir . '/pending';

if (!is_dir($pendingDir)) {
    exit("No pending bookings directory found.\n");
}

$files = glob($pendingDir . '/booking_*.json');
if ($files === false || empty($files)) {
    exit("No bookings found to process.\n");
}

$now = new DateTime('now');
$reminderCount = 0;

foreach ($files as $file) {
    $content = file_get_contents($file);
    if ($content === false) {
        continue;
    }
    
    $booking = json_decode($content, true);
    if (!is_array($booking)) {
        continue;
    }

    $date = $booking['date'] ?? null;
    $time = $booking['time'] ?? null;
    $status = $booking['status'] ?? null;
    $reminderSent = $booking['reminder_sent'] ?? false;

    if ($status !== 'confirmed' || $reminderSent || $date === null || $time === null) {
        continue;
    }

    $appointmentTime = DateTime::createFromFormat('Y-m-d H:i', "{$date} {$time}");
    if (!$appointmentTime) {
        continue;
    }

    // Calculate time difference
    $interval = $now->diff($appointmentTime);
    
    // Send reminder if appointment is within the next 24 hours and in the future
    if ($appointmentTime > $now && $interval->days === 0 && $interval->h <= 24) {
        $customerEmail = $booking['customer_email'] ?? null;
        $customerName = $booking['customer_name'] ?? null;

        if ($customerEmail) {
            $subject = "Reminder: Your upcoming appointment on {$date} at {$time}";
            $message = "Hello {$customerName},\n\nThis is a friendly reminder of your upcoming booking on {$date} at {$time}.\n\nBest regards,\nVenue Administration";
            $headers = "From: noreply@" . ($_SERVER['SERVER_NAME'] ?? 'localhost');

            // Dispatch mail (or log in dev environments)
            @mail($customerEmail, $subject, $message, $headers);
            
            // Mark reminder as sent
            $booking['reminder_sent'] = true;
            $fp = @fopen($file, 'c+b');
            if ($fp && @flock($fp, LOCK_EX)) {
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode($booking, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                fflush($fp);
                flock($fp, LOCK_UN);
                fclose($fp);
            }
            
            $reminderCount++;
        }
    }
}

echo "Reminder check complete. Sent {$reminderCount} reminder(s).\n";