<?php
/**
 * src/api.php
 * JSON API: service discovery, slot availability and booking requests.
 * Errors carry an English 'error' (for logs/debugging) and a stable 'code' that clients translate.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/classes/BookingRepository.php';
require_once __DIR__ . '/classes/BookingService.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

function respond(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

function fail(int $status, string $code, string $message): void {
    respond(['success' => false, 'code' => $code, 'error' => $message], $status);
}

function is_valid_date(string $date): bool {
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
}

function is_valid_time(string $time): bool {
    $parsed = DateTime::createFromFormat('H:i', $time);
    return $parsed && $parsed->format('H:i') === $time;
}

$repository = new BookingRepository();
$serviceLayer = new BookingService($repository);
$defaultCountry = ResourceProvider::getSettings()['default_country'] ?? 'se';

switch (request_string($_GET, 'action')) {
    case 'get_services':
        $services = array_filter($repository->getServices() ?? [], function ($service) {
            return is_array($service) && ResourceProvider::isServiceActive($service);
        });
        respond(['success' => true, 'data' => $services]);
        break;

    case 'check_availability':
        $date = request_string($_GET, 'date');
        $serviceId = request_string($_GET, 'service_id');
        $country = ResourceProvider::sanitizeCountryCode(request_string($_GET, 'country', $defaultCountry));

        if ($date === '' || $serviceId === '') {
            fail(400, 'missing_fields', 'Missing required parameters: date and service_id.');
        }
        if ($country === null) {
            fail(400, 'invalid_country', 'Invalid country code.');
        }

        $slots = $serviceLayer->getAvailableSlots($date, $serviceId, $country);
        if ($slots === null) {
            fail(400, 'invalid_request', 'Invalid date format or unknown service ID.');
        }
        respond(['success' => true, 'date' => $date, 'slots' => $slots]);
        break;

    case 'book':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            fail(405, 'method_not_allowed', 'Method not allowed. Use POST.');
        }

        $input = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $serviceId = request_string($input, 'service_id');
        $date = request_string($input, 'date');
        $time = request_string($input, 'time');
        $customerName = request_string($input, 'customer_name');
        $customerEmail = request_string($input, 'customer_email');

        if ($serviceId === '' || $date === '' || $time === '' || $customerName === '' || $customerEmail === '') {
            fail(400, 'missing_fields', 'Missing mandatory booking fields.');
        }
        if (!is_valid_date($date)) {
            fail(400, 'invalid_date', 'Invalid date format. Use YYYY-MM-DD.');
        }
        if (!is_valid_time($time)) {
            fail(400, 'invalid_time', 'Invalid time format. Use HH:MM.');
        }
        if (strlen($customerEmail) > 254 || filter_var($customerEmail, FILTER_VALIDATE_EMAIL) === false) {
            fail(400, 'invalid_email', 'Invalid email address.');
        }
        if (mb_strlen($customerName) > 100 || preg_match('/[\x00-\x1F\x7F]/', $customerName)) {
            fail(400, 'invalid_name', 'Invalid customer name.');
        }
        if (strlen($serviceId) > 100 || $repository->getServiceById($serviceId) === null) {
            fail(400, 'unknown_service', 'Unknown service ID.');
        }

        // Check availability and save under one lock so two concurrent requests cannot take the same slot
        $bookingId = $repository->withBookingLock(function () use ($repository, $serviceLayer, $serviceId, $date, $time, $customerName, $customerEmail, $defaultCountry) {
            foreach ($serviceLayer->getAvailableSlots($date, $serviceId, $defaultCountry) ?? [] as $slot) {
                if ($slot['start_time'] === $time) {
                    return $repository->createPendingRequest([
                        'service_id' => $serviceId,
                        'date' => $date,
                        'time' => $time,
                        'end_time' => $slot['end_time'],
                        'customer_name' => $customerName,
                        'customer_email' => $customerEmail,
                        'language' => Localization::getInstance()->getCurrentLang(),
                        'status' => 'pending',
                    ]) ?? false;
                }
            }
            return null;
        });

        if ($bookingId === null) {
            fail(409, 'slot_unavailable', 'The selected time slot is no longer available.');
        }
        if ($bookingId === false) {
            fail(500, 'save_failed', 'Failed to save booking request.');
        }
        respond(['success' => true, 'booking_id' => $bookingId]);
        break;

    default:
        fail(400, 'invalid_action', 'Invalid or unspecified API action.');
}
