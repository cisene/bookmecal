<?php
// send_reminders.php - Automated Hourly Appointment Reminders with Direct Cancellation Token
require_once 'db.php';
require_once 'i18n.php';
require_once 'tz_helper.php';

$appUrl = 'https://yourclinic.com'; // Base URL for cancellation link rendering

// Fetch active bookings scheduled between 23 and 25 hours from now that haven't received a reminder
$nowUTC = new DateTime('now', new DateTimeZone('UTC'));
$windowStart = (clone $nowUTC)->modify('+23 hours')->format('Y-m-d H:i:s');
$windowEnd   = (clone $nowUTC)->modify('+25 hours')->format('Y-m-d H:i:s');

$stmt = $pdo->prepare("
    SELECT b.*, s.name AS service_name, s.price 
    FROM booking_requests b
    JOIN services s ON b.service_id = s.id
    WHERE b.appointment_status = 'scheduled'
      AND b.reminder_sent = 0
      AND b.start_datetime BETWEEN ? AND ?
");
$stmt->execute([$windowStart, $windowEnd]);
$upcomingBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($upcomingBookings as $booking) {
    $lang = $booking['preferred_language'] ?? 'sv';
    $tz   = $booking['client_timezone'] ?? 'Europe/Stockholm';

    // 1. Generate secure cancellation token if not present
    if (empty($booking['cancellation_token'])) {
        $token = bin2hex(random_bytes(32));
        $updateTokenStmt = $pdo->prepare("UPDATE booking_requests SET cancellation_token = ? WHERE id = ?");
        $updateTokenStmt->execute([$token, $booking['id']]);
    } else {
        $token = $booking['cancellation_token'];
    }

    $cancelUrl = $appUrl . "/cancel.php?token=" . $token;

    // 2. Format localized datetime and construct localized strings
    $formattedTime = formatLocalizedDateTime($booking['start_datetime'], $tz, $lang);

    $subject = __t('reminder_subject', [
        'service' => $booking['service_name']
    ], $lang);

    $body  = __t('reminder_heading', [], $lang) . "\n\n";
    $body .= __t('reminder_intro', [
        'name' => $booking['client_name'],
        'time' => $formattedTime
    ], $lang) . "\n\n";
    $body .= __t('reminder_policy_notice', [
        'price' => $booking['price']
    ], $lang) . "\n\n";
    $body .= __t('cancellation_link_text', [
        'url' => $cancelUrl
    ], $lang);

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/plain; charset=UTF-8\r\n";
    $headers .= "From: Clinic Booking <noreply@yourclinic.com>\r\n";

    // 3. Dispatch reminder email and mark record as sent
    if (@mail($booking['client_email'], $subject, $body, $headers)) {
        $flagStmt = $pdo->prepare("UPDATE booking_requests SET reminder_sent = 1 WHERE id = ?");
        $flagStmt->execute([$booking['id']]);
    }
}