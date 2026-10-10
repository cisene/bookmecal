<?php
// api.php - REST API endpoint for Book Me Calendar

header('Content-Type: application/json; charset=utf-8');

// Load configuration and core classes
$config = require_once __DIR__ . '/config.php';
require_once __DIR__ . '/BookingRepository.php';
require_once __DIR__ . '/TimeSlot.php';
require_once __DIR__ . '/BookingEngine.php';

$action = $_GET['action'] ?? '';

try {
    $repository = new BookingRepository($config['storage']['data_dir'] ?? null);
    $slotDuration = $config['app']['slot_duration'] ?? 60;
    $engine = new BookingEngine($repository, $slotDuration);

    switch ($action) {
        case 'get_slots':
            $start = $_GET['start'] ?? date('Y-m-d');
            // Extract the date part (YYYY-MM-DD) from potential ISO string inputs
            $startDate = substr($start, 0, 10);
            
            $slots = $engine->getAvailableSlots($startDate);
            
            // Format slots for frontend calendar consumption (e.g., FullCalendar)
            $formattedSlots = [];
            foreach ($slots as $slot) {
                $formattedSlots[] = [
                    'title' => 'Available',
                    'start' => $slot->getStart()->format('Y-m-d\TH:i:s'),
                    'end'   => $slot->getEnd()->format('Y-m-d\TH:i:s'),
                    'color' => '#28a745'
                ];
            }
            
            echo json_encode([
                'success' => true,
                'data' => $formattedSlots
            ]);
            break;

        case 'book':
            // Only allow POST requests for booking submissions
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
                exit;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['name']) || empty($input['email']) || empty($input['start']) || empty($input['end'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                exit;
            }

            $bookingId = $engine->createPendingRequest(
                $input['name'],
                $input['email'],
                $input['start'],
                $input['end']
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Booking request received successfully!',
                'booking_id' => $bookingId
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid or missing action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}