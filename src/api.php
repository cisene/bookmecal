<?php
/**
 * api.php
 * Central REST API endpoint with robust error handling and output buffering.
 */
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Start output buffering to catch any stray warnings, notices, or whitespace
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require_once __DIR__ . '/ResourceProvider.php';
    require_once __DIR__ . '/AvailabilityEngine.php';

    $action = $_GET['action'] ?? '';
    $countryCode = $_GET['country'] ?? 'se';
    $langCode = $_GET['lang'] ?? 'sv';

    switch ($action) {
        case 'get_services':
            $services = ResourceProvider::getServices();
            if (!is_array($services)) {
                $services = [];
            }
            // Normalize associative arrays (SKU keys) to a clean sequential array for JS iteration
            $services = array_values($services);

            ob_clean();
            echo json_encode([
                'success' => true,
                'services' => $services,
                'settings' => ResourceProvider::getSettings(),
                'locale'   => ResourceProvider::getLocale($langCode)
            ]);
            exit;

        case 'check_availability':
        case 'get_slots':
            $startDate = $_GET['date'] ?? ($_GET['start'] ?? date('Y-m-d'));
            $startDate = substr($startDate, 0, 10);
            $daysAhead = isset($_GET['days']) ? (int)$_GET['days'] : 7;
            $serviceId = $_GET['service_id'] ?? ($_GET['service'] ?? null);
            
            if (empty($serviceId)) {
                ob_clean();
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Missing required service_id parameter.'
                ]);
                exit;
            }

            $service = ResourceProvider::getServiceById($serviceId);
            if (!$service) {
                ob_clean();
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Specified service not found for ID/SKU: ' . $serviceId
                ]);
                exit;
            }

            $duration = (int)($service['duration'] ?? ($service['duration_minutes'] ?? 45));
            $engine = new AvailabilityEngine($countryCode);
            
            $rangeAvailability = [];
            $currentDateObj = new DateTime($startDate);

            for ($i = 0; $i < $daysAhead; $i++) {
                $dateStr = $currentDateObj->format('Y-m-d');
                $slots = [];
                
                if (method_exists($engine, 'getAvailableSlots')) {
                    $slots = $engine->getAvailableSlots($dateStr, $serviceId);
                }

                $rangeAvailability[$dateStr] = [
                    'date' => $dateStr,
                    'is_closed' => empty($slots),
                    'available_slots' => is_array($slots) ? $slots : []
                ];

                $currentDateObj->modify('+1 day');
            }

            ob_clean();
            echo json_encode([
                'success' => true,
                'service_id' => $serviceId,
                'duration_minutes' => $duration,
                'range' => $rangeAvailability
            ]);
            exit;

        case 'book':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                ob_clean();
                http_response_code(405);
                echo json_encode([
                    'success' => false, 
                    'error' => 'Method not allowed. Use POST for booking submissions.'
                ]);
                exit;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['name']) || empty($input['email']) || empty($input['service_id']) || empty($input['date']) || empty($input['time'])) {
                ob_clean();
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'error' => 'Missing required booking details.'
                ]);
                exit;
            }

            $bookingId = uniqid('book_');

            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Booking request received successfully!',
                'booking_id' => $bookingId
            ]);
            exit;

        default:
            ob_clean();
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'error' => 'Invalid or missing action parameter.'
            ]);
            exit;
    }

} catch (Throwable $e) {
    ob_clean();
    http_response_code(500);
    error_log("[API Error] " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
    exit;
}