<?php
// api.php - Central API-endpoint för bokningssystemet

header('Content-Type: application/json; charset=utf-8');

// 1. Ladda in beroenden
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/BookingRepository.php';
require_once __DIR__ . '/GoogleSync.php';
require_once __DIR__ . '/BookingService.php';

$config = require __DIR__ . '/config.php';

// CORS-hantering (om API anropas från en separat frontend-app)
if (!empty($config['cors']['enabled'])) {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    header("Access-Control-Allow-Origin: {$origin}");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

try {
    // Anslut till databasen
    $pdo = Database::getConnection($config['db']);
    $repository = new BookingRepository($pdo);
    
    // Initiera GoogleSync om det är aktiverat i config
    $googleSync = null;
    if (!empty($config['google_calendar']['enabled'])) {
        $googleSync = new GoogleSync($config['google_calendar']);
    }

    $service = new BookingService($repository, $googleSync, $config);

    // Identifiera metod och åtgärd
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // Läs in JSON-payload om det finns
    $inputJSON = file_get_contents('php://input');
    $inputData = json_decode($inputJSON, true) ?? [];
    $requestData = array_merge($_REQUEST, $inputData);

    switch ($action) {
        case 'get_services':
            if ($method !== 'GET') {
                http_response_code(405);
                echo json_encode(['error' => 'Metoden tillåts inte. Använd GET.']);
                exit;
            }

            $services = $repository->getActiveServices();
            echo json_encode([
                'success' => true,
                'data' => $services
            ]);
            break;

        case 'check_availability':
            if ($method !== 'GET') {
                http_response_code(405);
                echo json_encode(['error' => 'Metoden tillåts inte. Använd GET.']);
                exit;
            }

            $date = $_GET['date'] ?? null;
            if (!$date) {
                http_response_code(400);
                echo json_encode(['error' => 'Parametern "date" (YYYY-MM-DD) krävs.']);
                exit;
            }

            $isHoliday = $repository->isHoliday($date);
            $dayOfWeek = (int)date('w', strtotime($date));
            $operatingHours = $repository->getOperatingHours($dayOfWeek);

            echo json_encode([
                'success' => true,
                'date' => $date,
                'is_holiday' => $isHoliday,
                'operating_hours' => $operatingHours
            ]);
            break;

        case 'book':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Metoden tillåts inte. Använd POST.']);
                exit;
            }

            try {
                $result = $service->bookSlot($requestData);
                http_response_code(201); // Created
                echo json_encode($result);
            } catch (InvalidArgumentException $e) {
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            } catch (RuntimeException $e) {
                http_response_code(409); // Conflict (upptagen tid/helgdag)
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'error' => 'Ogiltig eller saknad åtgärd (action). Tillgängliga åtgärder: get_services, check_availability, book.'
            ]);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internt serverfel: ' . $e->getMessage()
    ]);
}
