<?php
/**
 * src/admin/pending.php
 * Booking requests: approve or reject, move a booking to another employee who is free at that
 * time (BookingService::canMoveTo), find all bookings of one customer by email,
 * export them (right of access) or delete them (right to erasure).
 */

require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/classes/BookingRepository.php';
require_once dirname(__DIR__) . '/classes/BookingEmails.php';
require_once dirname(__DIR__) . '/classes/BookingService.php';
require_once dirname(__DIR__) . '/classes/EmployeeRepository.php';
require_once dirname(__DIR__) . '/classes/Staff.php';

$repository = new BookingRepository();
$repository->housekeeping();
$decisions = ['approve' => 'approved', 'reject' => 'rejected'];
$flash = [];

// Optional filter: one customer's bookings
$emailFilter = Input::text(request_string($_GET, 'email'), Input::EMAIL_MAX) ?? '';
$filterQuery = $emailFilter !== '' ? '&email=' . rawurlencode($emailFilter) : '';

if (is_post()) {
    require_csrf();
    $bookingId = request_string($_POST, 'id');
    $action = request_string($_POST, 'action');
    $current = $repository->getBookings()[$bookingId] ?? null;

    if ($action === 'delete') {
        if ($current !== null && $repository->deleteBooking($bookingId)) {
            log_audit_action($currentAdmin, 'BOOKING_DELETE', "Booking ID {$bookingId} deleted");
            header('Location: pending.php?done=delete' . $filterQuery);
            exit;
        }
        $flash[] = flash_error('Booking not found.');
    } elseif ($action === 'delete_all') {
        // Only with an active filter, so one click can never wipe every booking
        if ($emailFilter === '') {
            $flash[] = flash_error('Search for an email address first.');
        } else {
            $deleted = 0;
            foreach (array_keys($repository->getBookingsFor($emailFilter)) as $id) {
                $deleted += $repository->deleteBooking($id) ? 1 : 0;
            }
            log_audit_action($currentAdmin, 'BOOKING_DELETE_ALL', "{$deleted} bookings deleted for one customer (erasure request)");
            header('Location: pending.php?done=deleteAll&count=' . $deleted);
            exit;
        }
    } elseif ($action === 'move') {
        $employeeId = request_string($_POST, 'employee_id');
        $service = new BookingService($repository);
        $country = (string)(ResourceProvider::getSettings()['default_country'] ?? 'se');
        // Check and save under the booking lock, so a new booking cannot take the time meanwhile
        $moved = $current !== null && $repository->withBookingLock(fn () => $service->canMoveTo($current, $employeeId, $country)
            && $repository->updateBooking($bookingId, [
                'employee_id' => $employeeId,
                'employee_requested' => false,
                'moved_by' => $currentAdmin,
                'moved_at' => date(DATE_ATOM),
                // Calendar files sent again carry a higher SEQUENCE, so the customer's calendar updates
                'sequence' => (int)($current['sequence'] ?? 0) + 1,
            ]));
        if ($moved) {
            $from = (string)($current['employee_id'] ?? '');
            log_audit_action($currentAdmin, 'BOOKING_MOVE', "Booking ID {$bookingId} moved from employee {$from} to {$employeeId}");
            // An approved customer already has the old employee in their confirmation; a pending one gets the new name on approval
            $mail = '';
            if (($current['status'] ?? '') === 'approved' && request_string($_POST, 'notify') === '1') {
                $emails = new BookingEmails();
                $updated = $repository->getBookings()[$bookingId] ?? $current;
                $mail = '&mail=' . (!$emails->isEnabled() ? 'off' : ($emails->moved($updated, $from !== '' ? $from : null) ? 'sent' : 'failed'));
            }
            header('Location: pending.php?done=move' . $mail . $filterQuery);
            exit;
        }
        $flash[] = flash_error('The booking could not be moved: the employee does not offer this treatment, is not working or is booked at that time, or the booking has passed.');
    } elseif (($current['status'] ?? '') !== 'pending') {
        // Only pending requests can be decided; an expired one may have lost its time to another booking
        $flash[] = flash_error('This booking is no longer pending.');
    } elseif (isset($decisions[$action]) && $repository->updateBooking($bookingId, [
        'status' => $decisions[$action],
        'processed_by' => $currentAdmin,
        'processed_at' => date(DATE_ATOM),
    ])) {
        log_audit_action($currentAdmin, 'BOOKING_' . strtoupper($action), "Booking ID {$bookingId} status set to {$decisions[$action]}");
        // Tell the customer; the result is shown with the confirmation and kept on the booking
        $emails = new BookingEmails();
        $mailed = $emails->isEnabled() && $emails->decided($current, $action === 'approve');
        $repository->updateBooking($bookingId, ['decision_emailed' => $mailed]);
        $mail = !$emails->isEnabled() ? 'off' : ($mailed ? 'sent' : 'failed');
        header('Location: pending.php?done=' . $action . '&mail=' . $mail . $filterQuery);
        exit;
    } else {
        $flash[] = flash_error('Booking not found or invalid action.');
    }
}

$found = $emailFilter !== '' ? $repository->getBookingsFor($emailFilter) : $repository->getBookings();

// Right of access: everything stored about this customer as a JSON download
if ($emailFilter !== '' && request_string($_GET, 'export') === '1') {
    log_audit_action($currentAdmin, 'BOOKING_EXPORT', count($found) . ' bookings exported for one customer (access request)');
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="bookings.json"');
    echo json_encode(array_values($found), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$done = request_string($_GET, 'done');
if (isset($decisions[$done]) || $done === 'delete' || $done === 'move') {
    $flash[] = flash_success('admin.pending.done.' . $done);
    $mail = Input::oneOf(request_string($_GET, 'mail'), ['sent', 'failed', 'off']);
    if ($mail === 'sent') {
        $flash[] = flash_success('admin.pending.mail.sent');
    } elseif ($mail !== null) {
        $flash[] = ['type' => 'error', 'text' => __t('admin.pending.mail.' . $mail)];
    }
} elseif ($done === 'deleteAll') {
    $flash[] = flash_success('admin.pending.done.deleteAll', ['count' => (int)request_string($_GET, 'count')]);
}

$bookings = [];
$employees = (new EmployeeRepository())->getAll();
$bookingService = new BookingService($repository, $employees);
$staffFor = [];
$today = (new DateTimeImmutable('now', ResourceProvider::getTimezone()))->format('Y-m-d');
foreach ($found as $id => $booking) {
    $serviceId = (string)($booking['service_id'] ?? '');
    $service = ResourceProvider::getServiceById($serviceId);
    // Other employees offering the treatment, for upcoming bookings that still hold their time
    $moveTo = [];
    if (in_array($booking['status'] ?? '', BookingRepository::BLOCKING_STATUSES, true) && ($booking['date'] ?? '') >= $today) {
        $staffFor[$serviceId] ??= $bookingService->staffFor($serviceId);
        foreach ($staffFor[$serviceId] as $employeeId => $employee) {
            if ($employeeId !== ($booking['employee_id'] ?? null)) {
                $moveTo[$employeeId] = Staff::name($employee);
            }
        }
    }
    $bookings[$id] = $booking + [
        'service_name' => $service['name'] ?? ($booking['service_id'] ?? ''),
        'employee_name' => Staff::nameOf($employees, $booking['employee_id'] ?? null),
        'move_to' => $moveTo,
    ];
}

render_admin('pending', [
    'title' => 'admin.pending.title',
    'flash' => $flash,
    'bookings' => $bookings,
    'emailFilter' => $emailFilter,
    'retentionDays' => ResourceProvider::bookingRetentionDays(),
]);
