<?php
/**
 * src/api.php
 * Central REST API endpoint handling service discovery, slot availability, and booking requests.
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/classes/ResourceProvider.php';
require_once __DIR__ . '/classes/BookingRepository.php';
require_once __DIR__ . '/classes/BookingService.php';

ResourceProvider::initTimezone();

/**
 * Returns a trimmed string parameter, or '' when missing or not a scalar string (e.g. ?x[]=1).
 */
function input_string(array $source, string $key): string {
    return (isset($source[$key]) && is_string($source[$key])) ? trim($source[$key]) : '';
}

$action = input_string($_GET, 'action');
$repository = new BookingRepository();
$serviceLayer = new BookingService();

switch ($action) {
    case 'get_services':
        $services = $repository->getServices() ?? [];
        // Hide inactive services from the public booking form
        $services = array_filter($services, function ($srv) {
            return is_array($srv) && !(isset($srv['is_active']) && $srv['is_active'] === false);
        });
        echo json_encode([
            'success' => true,
            'data' => $services
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        break;

    case 'check_availability':
        $date = input_string($_GET, 'date');
        $serviceId = input_string($_GET, 'service_id');
        $country = ResourceProvider::sanitizeCountryCode(input_string($_GET, 'country') ?: 'se');

        if ($country === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid country code.']);
            exit();
        }

        if (empty($date) || empty($serviceId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing required parameters: date and service_id.']);
            exit();
        }

        $slots = $serviceLayer->getAvailableSlots($date, $serviceId, $country);
        if ($slots === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid date format or unknown service ID.']);
            exit();
        }

        echo json_encode([
            'success' => true,
            'date' => $date,
            'slots' => $slots
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        break;

    case 'book':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
            exit();
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $serviceId = input_string($input, 'service_id');
        $date = input_string($input, 'date');
        $time = input_string($input, 'time');
        $customerName = input_string($input, 'customer_name');
        $customerEmail = input_string($input, 'customer_email');

        if (empty($serviceId) || empty($date) || empty($time) || empty($customerName) || empty($customerEmail)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing mandatory booking fields.']);
            exit();
        }

        $parsedDate = DateTime::createFromFormat('Y-m-d', $date);
        $parsedTime = DateTime::createFromFormat('H:i', $time);
        $validationError = null;
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
            $validationError = 'Invalid date format. Use YYYY-MM-DD.';
        } elseif (!$parsedTime || $parsedTime->format('H:i') !== $time) {
            $validationError = 'Invalid time format. Use HH:MM.';
        } elseif (filter_var($customerEmail, FILTER_VALIDATE_EMAIL) === false || strlen($customerEmail) > 254) {
            $validationError = 'Invalid email address.';
        } elseif (mb_strlen($customerName) > 100 || preg_match('/[\x00-\x1F\x7F]/', $customerName)) {
            $validationError = 'Invalid customer name.';
        } elseif (strlen($serviceId) > 100 || $repository->getServiceById($serviceId) === null) {
            $validationError = 'Unknown service ID.';
        }

        if ($validationError === null) {
            // The requested time must be one of the slots we actually offer for that date/service
            $settings = ResourceProvider::getSettings();
            $offeredSlots = $serviceLayer->getAvailableSlots($date, $serviceId, $settings['default_country'] ?? 'se');
            if (!is_array($offeredSlots) || !in_array($time, array_column($offeredSlots, 'start_time'), true)) {
                $validationError = 'The selected time slot is not available.';
            }
        }

        if ($validationError !== null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $validationError]);
            exit();
        }

        $bookingData = [
            'service_id' => $serviceId,
            'date' => $date,
            'time' => $time,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'status' => 'pending'
        ];

        $bookingId = $repository->createPendingRequest($bookingData);
        if ($bookingId === null) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to save booking request.']);
            exit();
        }

        echo json_encode([
            'success' => true,
            'booking_id' => $bookingId,
            'message' => 'Booking request submitted successfully and is pending approval.'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid or unspecified API action.']);
        break;
}