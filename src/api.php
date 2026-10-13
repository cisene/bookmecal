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

$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$repository = new BookingRepository();
$serviceLayer = new BookingService();

switch ($action) {
    case 'get_services':
        $services = $repository->getServices();
        echo json_encode([
            'success' => true,
            'data' => $services !== null ? $services : []
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        break;

    case 'check_availability':
        $date = isset($_GET['date']) ? trim($_GET['date']) : '';
        $serviceId = isset($_GET['service_id']) ? trim($_GET['service_id']) : '';
        $country = isset($_GET['country']) ? trim($_GET['country']) : 'se';

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

        $serviceId = isset($input['service_id']) ? trim($input['service_id']) : '';
        $date = isset($input['date']) ? trim($input['date']) : '';
        $time = isset($input['time']) ? trim($input['time']) : '';
        $customerName = isset($input['customer_name']) ? trim($input['customer_name']) : '';
        $customerEmail = isset($input['customer_email']) ? trim($input['customer_email']) : '';

        if (empty($serviceId) || empty($date) || empty($time) || empty($customerName) || empty($customerEmail)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing mandatory booking fields.']);
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