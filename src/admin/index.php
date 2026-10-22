<?php
/**
 * src/admin/index.php
 * Dashboard: setup items still open (SetupCheck) and upcoming pending and approved bookings for
 * the next 7 days, grouped by date, with the employee taking each one.
 */

require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/classes/BookingRepository.php';
require_once dirname(__DIR__) . '/classes/SetupCheck.php';
require_once dirname(__DIR__) . '/classes/EmployeeRepository.php';
require_once dirname(__DIR__) . '/classes/Staff.php';

$repository = new BookingRepository();
$repository->housekeeping();
$timezone = ResourceProvider::getTimezone();
$today = new DateTime('today', $timezone);
$horizon = (clone $today)->modify('+7 days');

$days = [];
$employees = (new EmployeeRepository())->getAll();
foreach ($repository->getBookings() as $booking) {
    $start = $repository->bookingStart($booking);
    if ($start === null || $start < $today || $start >= $horizon
        || !in_array($booking['status'] ?? 'pending', BookingRepository::BLOCKING_STATUSES, true)) {
        continue;
    }
    $service = ResourceProvider::getServiceById((string)($booking['service_id'] ?? ''));
    $days[$start->format('Y-m-d')][] = $booking + [
        'service_name' => $service['name'] ?? ($booking['service_id'] ?? ''),
        'employee_name' => Staff::nameOf($employees, $booking['employee_id'] ?? null),
    ];
}

$setupProblems = SetupCheck::problems(
    ResourceProvider::getSettings() ?? [],
    ResourceProvider::getConfig() ?? [],
    ResourceProvider::getServices() ?? [],
    ResourceProvider::getOpenHours() ?? [],
    SecurityHeaders::isHttps()
);

render_admin('dashboard', [
    'title' => 'admin.dashboard.title',
    'setupProblems' => $setupProblems,
    'days' => $days,
    'timezone' => $timezone->getName(),
]);
