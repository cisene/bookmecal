<?php
/**
 * ics.php
 * Generates read-only iCalendar (.ics) feeds for Apple Calendar, Google Calendar, 
 * and other RFC 5545 compliant calendar clients.
 */

require_once __DIR__ . '/BookingRepository.php';
require_once __DIR__ . '/ResourceProvider.php';

// Set headers for calendar subscription feed
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="booking_feed.ics"; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Optional token security check if required by settings
$inputToken = $_GET['token'] ?? '';
// Validate token against data/config.json or tokens.json if feed protection is desired

echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//Book Me Calendar//SE//EN\r\n";
echo "CALSCALE:GREGORIAN\r\n";
echo "METHOD:PUBLISH\r\n";
echo "X-WR-CALNAME:Book Me Calendar - Bookings\r\n";
echo "X-WR-TIMEZONE:Europe/Stockholm\r\n";

// Fetch approved bookings or events
$repository = new BookingRepository();
$bookings = $repository->getApprovedBookings(); // Or equivalent method fetching active calendar entries

foreach ($bookings as $booking) {
    // Expecting keys: id, date, time, duration_minutes, customer_name, service_name
    $dateClean = str_replace('-', '', $booking['date']);
    $timeClean = str_replace(':', '', $booking['time'] ?? '090000');
    $startTime = $dateClean . 'T' . $timeClean . '00';
    
    $duration = intval($booking['duration_minutes'] ?? 60);
    $endTimestamp = strtotime("{$booking['date']} {$booking['time']} + {$duration} minutes");
    $endTime = date('Ymd\THis', $endTimestamp);

    echo "BEGIN:VEVENT\r\n";
    echo "UID:booking-" . ($booking['id'] ?? uniqid()) . "@bookmecal\r\n";
    echo "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
    echo "DTSTART:" . $startTime . "\r\n";
    echo "DTEND:" . $endTime . "\r\n";
    echo "SUMMARY:" . addcslashes($booking['service_name'] ?? 'Booked Appointment', ",\\;") . "\r\n";
    echo "DESCRIPTION:" . addcslashes("Booked by: " . ($booking['customer_name'] ?? 'Client'), ",\\;") . "\r\n";
    echo "STATUS:CONFIRMED\r\n";
    echo "END:VEVENT\r\n";
}

echo "END:VCALENDAR\r\n";
