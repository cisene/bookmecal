<?php
/**
 * api.php
 * Central REST API endpoint using ResourceProvider.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/ResourceProvider.php';
require_once __DIR__ . '/AvailabilityEngine.php';

$action = $_GET['action'] ?? '';
$countryCode = $_GET['country'] ?? 'se';

try {
    switch ($action) {
        case 'get_services':
            $services = ResourceProvider::getServices();
            if (!is_array($services)) {
                $services = [];
            }
            if (count($services) > 0 && array_keys($services) !== range(0, count($services) - 1)) {
                $services = array_values($services);
            }

            // Return both services and admin settings
            echo json_encode([
                'services' => $services,
                'settings' => ResourceProvider::getSettings()
            ]);
            break;

        case 'check_availability':
            $startDate = $_GET['date'] ?? date('Y-m-d');
            $daysAhead = isset($_GET['days']) ? (int)$_GET['days'] : 7;
            $serviceId = $_GET['service_id'] ?? '';
            
            if (empty($serviceId)) {
                http_response_code(400);
                echo json_encode(['error' => 'No service selected.']);
                break;
            }

            $service = ResourceProvider::getServiceById($serviceId);
            if (!$service) {
                http_response_code(404);
                echo json_encode(['error' => 'Selected service not found.']);
                break;
            }

            $duration = (int)($service['duration_minutes'] ?? 45);
            $engine = new AvailabilityEngine($countryCode);
            
            $rangeAvailability = [];
            $currentDateObj = new DateTime($startDate);

            for ($i = 0; $i < $daysAhead; $i++) {
                $dateStr = $currentDateObj->format('Y-m-d');
                $slots = $engine->getAvailableSlots($dateStr, $duration);

                $rangeAvailability[$dateStr] = [
                    'date' => $dateStr,
                    'is_closed' => empty($slots),
                    'available_slots' => $slots
                ];

                $currentDateObj->modify('+1 day');
            }

            echo json_encode([
                'service_id' => $serviceId,
                'duration_minutes' => $duration,
                'range' => $rangeAvailability
            ]);
            break;

        case 'book':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($input['service_id']) || empty($input['date']) || empty($input['time']) || empty($input['customer_email'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required booking fields.']);
                break;
            }

            if (!ResourceProvider::getServiceById($input['service_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid service specified for booking.']);
                break;
            }

            echo json_encode([
                'success' => true,
                'message' => 'Booking successfully confirmed!',
                'booking' => $input
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action specified.']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}