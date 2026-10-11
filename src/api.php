<?php
/**
 * api.php
 * Central REST API for the client booking interface.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/AvailabilityEngine.php';

$action = $_GET['action'] ?? '';
$countryCode = $_GET['country'] ?? 'se'; // Default to Sweden

switch ($action) {
    case 'get_services':
        // Load services from file or database
        $servicesFile = __DIR__ . '/data/services.json';
        if (file_exists($servicesFile)) {
            echo file_get_contents($servicesFile);
        } else {
            // Fallback sample service for foot care
            echo json_init_services_fallback();
        }
        break;

    case 'check_availability':
        $date = $_GET['date'] ?? date('Y-m-d');
        $serviceId = $_GET['service_id'] ?? 'standard_foot_care';
        
        // Fetch service duration (default to 45 mins if not found)
        $duration = getServiceDuration($serviceId);

        $engine = new AvailabilityEngine($countryCode);
        $availableSlots = $engine->getAvailableSlots($date, $duration);

        echo json_encode([
            'date' => $date,
            'duration_minutes' => $duration,
            'available_slots' => $availableSlots
        ]);
        break;

    case 'book':
        // Handle incoming POST booking submission
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        // Basic validation
        if (empty($input['date']) || empty($input['time']) || empty($input['customer_email'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required booking fields.']);
            break;
        }

        // Here you would write to database and create a mock/cached calendar block
        // For now, return success response
        echo json_encode([
            'success' => true,
            'message' => 'Booking confirmed successfully!',
            'booking' => [
                'date' => $input['date'],
                'time' => $input['time'],
                'service' => $input['service_id'] ?? 'Foot Care'
            ]
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action specified.']);
        break;
}

function getServiceDuration($serviceId) {
    $servicesFile = __DIR__ . '/data/services.json';
    if (file_exists($servicesFile)) {
        $services = json_decode(file_get_contents($servicesFile), true);
        foreach ($services as $service) {
            if ($service['id'] === $serviceId) {
                return (int)$service['duration_minutes'];
            }
        }
    }
    return 45; // Default fallback duration in minutes
}

function json_init_services_fallback() {
    return json_encode([
        [
            'id' => 'standard_foot_care',
            'name' => 'Standard Foot Care (Medicinsk fotvård)',
            'duration_minutes' => 45,
            'price' => 650,
            'currency' => 'SEK'
        ]
    ]);
}