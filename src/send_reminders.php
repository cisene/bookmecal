<?php
/**
 * src/send_reminders.php
 * Cron script: emails a reminder for approved bookings starting within the next 24 hours.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Access denied. This script can only be run via command line.');
}

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/classes/BookingRepository.php';
require_once __DIR__ . '/classes/EmailNotifier.php';
require_once __DIR__ . '/classes/Constants.php';

$repository = new BookingRepository();
$notifier = new EmailNotifier(ResourceProvider::getConfig() ?? []);
$country = ResourceProvider::getSettings()['default_country'] ?? 'se';
$currency = (new Constants())->getLocaleForCountry($country)['currency'] ?? '';

$now = new DateTime('now', ResourceProvider::getTimezone());
$horizon = (clone $now)->modify('+24 hours');
$sent = 0;

foreach ($repository->getBookings() as $id => $booking) {
    // pending.php marks accepted bookings as 'approved'; 'confirmed' is kept for older records
    if (!in_array($booking['status'] ?? null, ['approved', 'confirmed'], true) || !empty($booking['reminder_sent'])) {
        continue;
    }
    $start = $repository->bookingStart($booking);
    if ($start === null || $start <= $now || $start > $horizon) {
        continue;
    }

    $service = ResourceProvider::getServiceById((string)($booking['service_id'] ?? '')) ?? [];
    $ok = $notifier->sendReminder(
        (string)($booking['customer_email'] ?? ''),
        str_replace(["\r", "\n"], ' ', (string)($booking['customer_name'] ?? '')),
        (string)($service['name'] ?? ''),
        $start->format('Y-m-d H:i'),
        (string)($service['price_incl_vat'] ?? $service['price'] ?? ''),
        $currency,
        $booking['language'] ?? null
    );

    if ($ok) {
        $repository->updateBooking($id, ['reminder_sent' => true]);
        $sent++;
    }
}

echo "Reminder check complete. Sent {$sent} reminder(s).\n";
