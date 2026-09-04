<?php
// submit_booking.php - Secure Booking Submission Handler

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/gcal_helper.php';
require_once __DIR__ . '/i18n.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Ensure request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// 2. Validate CSRF Token
if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Säkerhetsverifieringen misslyckades (CSRF-fel). Vänligen uppdatera sidan och försök igen.');
}

$errors = [];

// 3. Sanitize and validate inputs
$serviceId     = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
$clientName    = trim(filter_input(INPUT_POST, 'client_name', FILTER_DEFAULT) ?? '');
$clientEmail   = filter_input(INPUT_POST, 'client_email', FILTER_VALIDATE_EMAIL);
$clientPhone   = trim(filter_input(INPUT_POST, 'client_phone', FILTER_DEFAULT) ?? '');
$startDatetime = trim(filter_input(INPUT_POST, 'start_datetime', FILTER_DEFAULT) ?? '');

// Service Validation
$services = getActiveServices($pdo);
$selectedService = null;

if (!$serviceId) {
    $errors[] = 'Vänligen välj en giltig tjänst.';
} else {
    foreach ($services as $s) {
        if ((int)$s['id'] === $serviceId) {
            $selectedService = $s;
            break;
        }
    }
    if (!$selectedService) {
        $errors[] = 'Den valda tjänsten existerar inte.';
    }
}

// Name Validation
if (empty($clientName) || mb_strlen($clientName) < 2 || mb_strlen($clientName) > 100) {
    $errors[] = 'Vänligen ange ett giltigt namn (2–100 tecken).';
}

// Email Validation
if (!$clientEmail) {
    $errors[] = 'Vänligen ange en giltig e-postadress.';
}

// Phone Validation (Allows international, spaces, hyphens, plus)
if (empty($clientPhone) || !preg_match('/^[0-9\+\-\s\(\)]{6,25}$/', $clientPhone)) {
    $errors[] = 'Vänligen ange ett giltigt telefonnummer.';
}

// DateTime ISO Validation (Format: YYYY-MM-DD HH:MM)
$dtObject = DateTime::createFromFormat('Y-m-d H:i', $startDatetime);
if (!$dtObject || $dtObject->format('Y-m-d H:i') !== $startDatetime) {
    $errors[] = 'Ogiltigt datum- eller tidsformat.';
} else {
    // Prevent booking in the past
    if ($dtObject < new DateTime('now')) {
        $errors[] = 'Det går inte att boka en tid som redan passerat.';
    }
}

// 4. Double-Booking Prevention Check
if (empty($errors) && isSlotBooked($pdo, $startDatetime)) {
    $errors[] = 'Den valda tiden har tyvärr hunnit bokas av någon annan. Vänligen välj en annan tid.';
}

// 5. Handle Validation Failures
if (!empty($errors)) {
    $_SESSION['booking_errors'] = $errors;
    $_SESSION['form_data'] = [
        'service_id'   => $serviceId,
        'client_name'  => $clientName,
        'client_email' => $clientEmail,
        'client_phone' => $clientPhone,
    ];
    header('Location: booking.php?error=1');
    exit;
}

// 6. Save Booking to Database with Transaction
try {
    $pdo->beginTransaction();

    $bookingData = [
        'service_id'         => $serviceId,
        'client_name'        => $clientName,
        'client_email'       => $clientEmail,
        'client_phone'       => $clientPhone,
        'start_datetime'     => $startDatetime,
        'preferred_language' => CURRENT_LANG
    ];

    $bookingId = createBookingRecord($pdo, $bookingData);

    // 7. Sync with Google Calendar
    try {
        $gcalData = array_merge($bookingData, [
            'service_name'     => $selectedService['name'],
            'duration_minutes' => $selectedService['duration_minutes']
        ]);

        $eventId = createGoogleCalendarEvent($gcalData);
        updateBookingGcalEventId($pdo, $bookingId, $eventId);
    } catch (Exception $e) {
        // Log GCal sync error without failing DB booking
        error_log("Google Calendar Sync Error: " . $e->getMessage());
    }

    $pdo->commit();

    // Clear saved session data on success
    unset($_SESSION['form_data'], $_SESSION['booking_errors']);

    // Redirect to confirmation screen
    header("Location: confirmation.php?id=" . $bookingId);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Booking Insertion Failure: " . $e->getMessage());
    $_SESSION['booking_errors'] = ['Ett internt fel uppstod vid bokningen. Vänligen försök igen.'];
    header('Location: booking.php?error=1');
    exit;
}