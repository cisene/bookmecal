<?php
/**
 * src/api.php
 * JSON API: service and staff discovery, slot availability (per day or per week, for anyone or one
 * employee) and booking requests.
 * Public endpoint: rate limited per client, bookings need the page's form token, JSON bodies only.
 * Errors carry an English 'error' (for logs/debugging) and a stable 'code' that clients translate.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/classes/BookingRepository.php';
require_once __DIR__ . '/classes/BookingService.php';
require_once __DIR__ . '/classes/RateLimiter.php';
require_once __DIR__ . '/classes/FormToken.php';
require_once __DIR__ . '/classes/SecurityLog.php';
require_once __DIR__ . '/classes/BookingEmails.php';

/** Requests per client: [limit, window in seconds]. */
const LIMIT_READ = [120, 300];
const LIMIT_BOOK = [10, 3600];
/** Upcoming pending bookings allowed per email address. */
const MAX_PENDING_PER_EMAIL = 3;
/** Largest accepted booking request body in bytes. */
const MAX_BODY_BYTES = 8192;
/** Longest accepted customer name and service ID in characters. */
const MAX_NAME_LENGTH = 100;
const MAX_ID_LENGTH = 100;

SecurityHeaders::send('api');
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function respond(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

function fail(int $status, string $code, string $message): never {
    respond(['success' => false, 'code' => $code, 'error' => $message], $status);
}

function enforce_limit(RateLimiter $limiter, string $bucket, array $limit): void {
    [$max, $window] = $limit;
    if (!$limiter->hit($bucket, RateLimiter::clientKey(), $max, $window)) {
        SecurityLog::record('rate_limited', ['bucket' => $bucket]);
        header('Retry-After: ' . $window);
        fail(429, 'rate_limited', 'Too many requests. Please wait and try again.');
    }
}

/**
 * Validated employee choice: null for "anyone", otherwise an employee who offers the service.
 */
function employee_choice(BookingService $serviceLayer, string $employeeId, string $serviceId): ?string {
    if ($employeeId === '') {
        return null;
    }
    if (!EmployeeRepository::isValidId($employeeId) || !isset($serviceLayer->staffFor($serviceId)[$employeeId])) {
        fail(400, 'unknown_employee', 'Unknown employee or the employee does not offer this service.');
    }
    return $employeeId;
}

/**
 * Validated [date, service_id, country, employee_id] from the query string of the availability actions.
 */
function plan_query(BookingService $serviceLayer, string $defaultCountry): array {
    $date = request_string($_GET, 'date');
    $serviceId = request_string($_GET, 'service_id');
    $country = ResourceProvider::sanitizeCountryCode(request_string($_GET, 'country', $defaultCountry));

    if ($date === '' || $serviceId === '') {
        fail(400, 'missing_fields', 'Missing required parameters: date and service_id.');
    }
    if (Input::date($date) === null) {
        fail(400, 'invalid_date', 'Invalid date format. Use YYYY-MM-DD.');
    }
    if (Input::text($serviceId, MAX_ID_LENGTH) === null) {
        fail(400, 'unknown_service', 'Unknown service ID.');
    }
    if ($country === null) {
        fail(400, 'invalid_country', 'Invalid country code.');
    }
    return [$date, $serviceId, $country, employee_choice($serviceLayer, request_string($_GET, 'employee_id'), $serviceId)];
}

$settings = ResourceProvider::getSettings();
$repository = new BookingRepository();
$serviceLayer = new BookingService($repository);
$limiter = new RateLimiter();
$defaultCountry = $settings['default_country'] ?? 'se';

// Cross-origin use only from origins listed in settings.json (e.g. a future landing page)
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
$allowedOrigins = array_filter((array)($settings['allowed_origins'] ?? []), 'is_string');
if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Methods: GET, POST');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Max-Age: 600');
        http_response_code(204);
        exit();
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    fail(403, 'origin_not_allowed', 'Cross-origin requests are not allowed from this origin.');
}

$action = request_string($_GET, 'action');
enforce_limit($limiter, $action === 'book' ? 'book' : 'read', $action === 'book' ? LIMIT_BOOK : LIMIT_READ);

// Unanswered requests stop blocking time; old bookings are deleted after the retention period
$repository->housekeeping();

switch ($action) {
    case 'get_services':
        $all = $repository->getServices() ?? [];
        // A set's duration is the sum of its treatments; a set with a missing treatment is left out
        $services = [];
        foreach ($all as $key => $service) {
            $duration = is_array($service) ? ResourceProvider::serviceDuration($service, $all) : null;
            if ($duration !== null && ResourceProvider::isServiceActive($service)) {
                $services[$key] = ['duration' => $duration] + $service;
            }
        }
        // Staff customers can choose from; each service lists who offers it. Empty without staff.
        $staff = [];
        foreach ($services as $key => $service) {
            $services[$key]['employee_ids'] = [];
            foreach ($serviceLayer->staffFor((string)$key) as $id => $employee) {
                $services[$key]['employee_ids'][] = $id;
                $staff[$id] = ['name' => Staff::name($employee), 'title' => (string)($employee['jobTitle'] ?? '')];
            }
        }
        respond(['success' => true, 'data' => $services, 'staff' => $staff]);
        break;

    case 'check_availability':
        [$date, $serviceId, $country, $employeeId] = plan_query($serviceLayer, $defaultCountry);
        $slots = $serviceLayer->getAvailableSlots($date, $serviceId, $country, $employeeId);
        if ($slots === null) {
            fail(400, 'invalid_request', 'Invalid date format or unknown service ID.');
        }
        respond(['success' => true, 'date' => $date, 'slots' => $slots]);
        break;

    case 'check_week':
        [$date, $serviceId, $country, $employeeId] = plan_query($serviceLayer, $defaultCountry);
        $week = $serviceLayer->getWeekPlan($date, $serviceId, $country, $employeeId);
        if ($week === null) {
            fail(400, 'invalid_request', 'Invalid date format or unknown service ID.');
        }
        respond(['success' => true] + $week);
        break;

    case 'book':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            fail(405, 'method_not_allowed', 'Method not allowed. Use POST.');
        }

        // JSON only: a cross-site form cannot send it without a CORS preflight, which is refused above
        if (stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== 0) {
            fail(415, 'unsupported_media_type', 'Send the booking as application/json.');
        }
        $body = (string)file_get_contents('php://input', false, null, 0, MAX_BODY_BYTES + 1);
        if (strlen($body) > MAX_BODY_BYTES) {
            fail(413, 'payload_too_large', 'Request body too large.');
        }
        $input = json_decode($body, true);
        if (!is_array($input)) {
            fail(400, 'invalid_request', 'Request body must be a JSON object.');
        }

        // Honeypot: the "website" field is invisible to people, so only bots fill it in.
        // Answer like a success so the bot has nothing to adjust to, but save nothing.
        if (request_string($input, 'website') !== '') {
            SecurityLog::record('honeypot');
            respond(['success' => true, 'booking_id' => 'book_' . bin2hex(random_bytes(6))]);
        }
        switch (FormToken::check(request_string($input, 'form_token'))) {
            case FormToken::VALID:
                break;
            case FormToken::TOO_FAST:
                SecurityLog::record('form_too_fast');
                fail(400, 'too_fast', 'The form was sent too quickly.');
            case FormToken::EXPIRED:
                fail(400, 'form_expired', 'The booking page has expired. Reload it and try again.');
            default:
                SecurityLog::record('form_token_invalid');
                fail(400, 'invalid_request', 'Missing or invalid form token.');
        }

        $serviceId = request_string($input, 'service_id');
        $date = request_string($input, 'date');
        $time = request_string($input, 'time');
        $customerName = request_string($input, 'customer_name');
        $customerEmail = request_string($input, 'customer_email');

        if ($serviceId === '' || $date === '' || $time === '' || $customerName === '' || $customerEmail === '') {
            fail(400, 'missing_fields', 'Missing mandatory booking fields.');
        }
        if (Input::date($date) === null) {
            fail(400, 'invalid_date', 'Invalid date format. Use YYYY-MM-DD.');
        }
        if (Input::time($time) === null) {
            fail(400, 'invalid_time', 'Invalid time format. Use HH:MM.');
        }
        if (Input::email($customerEmail) === null) {
            fail(400, 'invalid_email', 'Invalid email address.');
        }
        if (Input::text($customerName, MAX_NAME_LENGTH) === null) {
            fail(400, 'invalid_name', 'Invalid customer name.');
        }
        if (Input::text($serviceId, MAX_ID_LENGTH) === null || $repository->getServiceById($serviceId) === null) {
            fail(400, 'unknown_service', 'Unknown service ID.');
        }
        $employeeId = employee_choice($serviceLayer, request_string($input, 'employee_id'), $serviceId);

        // Check availability, pick the employee and save under one lock so two concurrent requests cannot take the same slot
        $bookingId = $repository->withBookingLock(function () use ($repository, $serviceLayer, $serviceId, $date, $time, $employeeId, $customerName, $customerEmail, $defaultCountry) {
            if ($repository->countUpcomingPendingFor($customerEmail) >= MAX_PENDING_PER_EMAIL) {
                return 'too_many_pending';
            }
            $assigned = $serviceLayer->assign($date, $serviceId, $time, $employeeId, $defaultCountry);
            if ($assigned === null) {
                return null;
            }
            return $repository->createPendingRequest([
                'service_id' => $serviceId,
                'employee_id' => $assigned['employee_id'],
                'employee_requested' => $employeeId !== null,
                'date' => $date,
                'time' => $time,
                'end_time' => $assigned['end_time'],
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'language' => Localization::getInstance()->getCurrentLang(),
                'status' => 'pending',
            ]) ?? false;
        });

        if ($bookingId === 'too_many_pending') {
            SecurityLog::record('pending_limit');
            fail(429, 'too_many_pending', 'Too many unconfirmed bookings for this email address.');
        }
        if ($bookingId === null) {
            fail(409, 'slot_unavailable', 'The selected time slot is no longer available.');
        }
        if ($bookingId === false) {
            fail(500, 'save_failed', 'Failed to save booking request.');
        }
        // Receipt to the customer and alert to the business; a mail failure does not undo the booking
        $saved = $repository->getBookings()[$bookingId] ?? null;
        if ($saved !== null) {
            (new BookingEmails())->requestReceived($saved);
        }
        respond(['success' => true, 'booking_id' => $bookingId]);
        break;

    default:
        fail(400, 'invalid_action', 'Invalid or unspecified API action.');
}
