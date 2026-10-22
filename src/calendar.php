<?php
/**
 * src/calendar.php
 * Calendar subscription feed (iCalendar) of upcoming and recent bookings: calendar.php?token=...
 * The token is created on the admin Config page. Wrong tokens get 404 and are logged.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/classes/BookingRepository.php';
require_once __DIR__ . '/classes/CalendarFeed.php';
require_once __DIR__ . '/classes/EmployeeRepository.php';
require_once __DIR__ . '/classes/Staff.php';
require_once __DIR__ . '/classes/RateLimiter.php';
require_once __DIR__ . '/classes/SecurityLog.php';

/** Requests per client: calendar apps poll every 15 minutes or so. */
const LIMIT_FEED = [30, 300];

SecurityHeaders::send('api');
header('Cache-Control: no-store');

[$max, $window] = LIMIT_FEED;
if (!(new RateLimiter())->hit('feed', RateLimiter::clientKey(), $max, $window)) {
    SecurityLog::record('rate_limited', ['bucket' => 'feed']);
    header('Retry-After: ' . $window);
    http_response_code(429);
    exit;
}

$feed = new CalendarFeed();
if (!$feed->accepts(request_string($_GET, 'token'))) {
    SecurityLog::record('feed_token_invalid');
    http_response_code(404);
    exit;
}

$repository = new BookingRepository();
$repository->housekeeping();

$serviceNames = [];
// Bookings refer to a service by its storage key, SKU or id (see ResourceProvider::getServiceById)
foreach (ResourceProvider::getServices() ?? [] as $key => $service) {
    if (is_array($service) && isset($service['name'])) {
        foreach ([$key, $service['sku'] ?? null, $service['id'] ?? null] as $ref) {
            if ($ref !== null) {
                $serviceNames[(string)$ref] ??= (string)$service['name'];
            }
        }
    }
}
$businessName = trim((string)(ResourceProvider::getSettings()['business_name'] ?? ''));

header('Content-Type: text/calendar; charset=UTF-8');
header('Content-Disposition: inline; filename="bookings.ics"');
$employeeNames = array_map([Staff::class, 'name'], (new EmployeeRepository())->getAll());

echo CalendarFeed::render($repository->getBookings(), $serviceNames, $businessName !== '' ? $businessName : 'Book Me Calendar', ResourceProvider::getTimezone(), null, $employeeNames);
