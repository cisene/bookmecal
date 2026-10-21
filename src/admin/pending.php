<?php
/**
 * src/admin/pending.php
 * Booking requests: approve or reject.
 */

require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/classes/BookingRepository.php';

$repository = new BookingRepository();
$repository->expireStalePending(ResourceProvider::pendingExpiryHours());
$decisions = ['approve' => 'approved', 'reject' => 'rejected'];
$flash = [];

if (is_post()) {
    require_csrf();
    $bookingId = request_string($_POST, 'id');
    $action = request_string($_POST, 'action');
    $current = $repository->getBookings()[$bookingId] ?? null;

    // Only pending requests can be decided; an expired one may have lost its time to another booking
    if (($current['status'] ?? '') !== 'pending') {
        $flash[] = flash_error('This booking is no longer pending.');
    } elseif (isset($decisions[$action]) && $repository->updateBooking($bookingId, [
        'status' => $decisions[$action],
        'processed_by' => $currentAdmin,
        'processed_at' => date(DATE_ATOM),
    ])) {
        log_audit_action($currentAdmin, 'BOOKING_' . strtoupper($action), "Booking ID {$bookingId} status set to {$decisions[$action]}");
        header('Location: pending.php?done=' . $action);
        exit;
    } else {
        $flash[] = flash_error('Booking not found or invalid action.');
    }
}

$done = request_string($_GET, 'done');
if (isset($decisions[$done])) {
    $flash[] = flash_success('admin.pending.done.' . $done);
}

$bookings = [];
foreach ($repository->getBookings() as $id => $booking) {
    $service = ResourceProvider::getServiceById((string)($booking['service_id'] ?? ''));
    $bookings[$id] = $booking + ['service_name' => $service['name'] ?? ($booking['service_id'] ?? '')];
}

render_admin('pending', [
    'title' => 'admin.pending.title',
    'flash' => $flash,
    'bookings' => $bookings,
]);
