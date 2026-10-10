<?php
// api.php - REST API endpoint for Book Me Calendar with rate limiting

header('Content-Type: application/json; charset=utf-8');

// Load configuration and core classes
$config = require_once __DIR__ . '/config.php';
require_once __DIR__ . '/BookingRepository.php';
require_once __DIR__ . '/TimeSlot.php';
require_once __DIR__ . '/BookingEngine.php';
require_once __DIR__ . '/EmailNotifier.php';

$action = $_GET['action'] ?? '';

// Simple IP-based Rate Limiting helper for booking submissions
function checkRateLimit(): void {
    $cacheDir = __DIR__ . '/cache';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateFile = $cacheDir . '/rate_' . md5($ip) . '.json';
    
    $now = time();
    $window = 300; // 5 minutes window
    $maxRequests = 5; // Max 5 requests per window
    
    $data = ['count' => 0, 'reset' => $now + $window];
    if (file_exists($rateFile)) {
        $content = @file_get_contents($rateFile);
        $saved = json_decode($content, true);
        if ($saved && isset($saved['count'], $saved['reset'])) {
            if ($now < $saved['reset']) {
                $data = $saved;
            }
        }
    }
    
    if ($data['count'] >= $maxRequests) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Too many booking requests from this IP address. Please try again later.'
        ]);
        exit;
    }
    
    $data['count']++;
    @file_put_contents($rateFile, json_encode($data));
}

try {
    $repository = new BookingRepository($config['storage']['data_dir'] ?? null);
    $slotDuration = $config['app']['slot_duration'] ?? 60;
    $engine = new BookingEngine($repository, $slotDuration);

    switch ($action) {
        case 'get_slots':
            $start = $_GET['start'] ?? date('Y-m-d');
            $startDate = substr($start, 0, 10);
            
            $slots = $engine->getAvailableSlots($startDate);
            
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
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
                exit;
            }

            // Enforce rate limiting on booking submissions
            checkRateLimit();

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

            // Send email notifications
            $notifier = new EmailNotifier($config);
            $notifier->sendBookingConfirmation($input['email'], $input['name'], $input['start'], $input['end']);
            
            $adminEmail = $config['app']['admin_email'] ?? $config['smtp']['from_email'] ?? '';
            if (!empty($adminEmail)) {
                $notifier->sendAdminAlert($adminEmail, $input['name'], $input['email'], $input['start'], $input['end']);
            }
            
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