<?php
/**
 * src/cancel.php
 * Customer cancellation through the secret link in the booking emails: cancel.php?id=...&token=...
 * GET shows the booking and a button; only the POST cancels, so mail scanners that open links
 * cannot cancel anything. Online cancellation closes cancel_cutoff_hours before the appointment.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/classes/BookingRepository.php';
require_once __DIR__ . '/classes/BookingEmails.php';
require_once __DIR__ . '/classes/CancelLink.php';
require_once __DIR__ . '/classes/RateLimiter.php';
require_once __DIR__ . '/classes/SecurityLog.php';

/** Requests per client: [limit, window in seconds]. */
const LIMIT_CANCEL = [30, 300];

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$localization = Localization::getInstance();
$source = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$bookingId = request_string($source, 'id');
$token = request_string($source, 'token');
$repository = new BookingRepository();
$cutoffHours = ResourceProvider::cancelCutoffHours();

/**
 * Renders the page in one of the states: invalid, confirm, done, inactive, tooLate, rateLimited.
 */
function render_cancel(string $state, array $vars = [], int $status = 200): never {
    global $localization, $bookingId, $token;
    http_response_code($status);
    View::render('public/cancel', $vars + [
        'state' => $state,
        'lang' => $localization->getCurrentLang(),
        'languages' => $localization->availableLanguages(),
        // The language switch must keep the link working
        'keepQuery' => $state === 'confirm' ? ['id' => $bookingId, 'token' => $token] : [],
        'booking' => null,
    ], 'public');
    exit;
}

[$max, $window] = LIMIT_CANCEL;
if (!(new RateLimiter())->hit('cancel', RateLimiter::clientKey(), $max, $window)) {
    SecurityLog::record('rate_limited', ['bucket' => 'cancel']);
    header('Retry-After: ' . $window);
    render_cancel('rateLimited', [], 429);
}

$booking = BookingRepository::isValidId($bookingId) ? ($repository->getBookings()[$bookingId] ?? null) : null;
if ($booking === null || !CancelLink::check($bookingId, $token)) {
    SecurityLog::record('cancel_token_invalid');
    render_cancel('invalid', [], 404);
}

$service = ResourceProvider::getServiceById((string)($booking['service_id'] ?? '')) ?? [];
$settings = ResourceProvider::getSettings() ?? [];
$smtp = (ResourceProvider::getConfig() ?? [])['smtp'] ?? [];
$contact = Input::email(trim((string)($settings['privacy_contact'] ?? ''))) ?? Input::email((string)($smtp['from_email'] ?? ''));
$view = [
    'booking' => [
        'service' => (string)($service['name'] ?? $booking['service_id'] ?? ''),
        'date' => (string)($booking['date'] ?? ''),
        'time' => (string)($booking['time'] ?? ''),
        'end' => (string)($booking['end_time'] ?? ''),
    ],
    'hours' => $cutoffHours,
    'contact' => $contact !== null && $contact !== 'noreply@example.com' ? $contact : null,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $repository->cancelBooking($bookingId, $cutoffHours);
    if ($result === BookingRepository::CANCEL_OK) {
        (new BookingEmails())->cancelledByCustomer($booking);
        render_cancel('done', $view);
    }
    render_cancel($result === BookingRepository::CANCEL_TOO_LATE ? 'tooLate' : 'inactive', $view);
}

$state = $repository->cancelState($booking, $cutoffHours);
render_cancel($state === BookingRepository::CANCEL_OK ? 'confirm' : ($state === BookingRepository::CANCEL_TOO_LATE ? 'tooLate' : 'inactive'), $view);
