<?php
/**
 * src/admin/index.php
 * Dashboard: upcoming pending and approved bookings for the next 7 days, grouped by date.
 */

require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/classes/BookingRepository.php';

$repository = new BookingRepository();
$repository->housekeeping();
$timezone = ResourceProvider::getTimezone();
$today = new DateTime('today', $timezone);
$horizon = (clone $today)->modify('+7 days');

$days = [];
foreach ($repository->getBookings() as $booking) {
    $start = $repository->bookingStart($booking);
    if ($start === null || $start < $today || $start >= $horizon
        || !in_array($booking['status'] ?? 'pending', BookingRepository::BLOCKING_STATUSES, true)) {
        continue;
    }
    $service = ResourceProvider::getServiceById((string)($booking['service_id'] ?? ''));
    $days[$start->format('Y-m-d')][] = $booking + ['service_name' => $service['name'] ?? ($booking['service_id'] ?? '')];
}

render_admin('dashboard', [
    'title' => 'admin.dashboard.title',
    'days' => $days,
    'timezone' => $timezone->getName(),
]);
