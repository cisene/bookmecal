<?php
// db.php - Database Connection, Session Management, Security & Data Access Layer

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host    = '127.0.0.1';
$db      = 'booking_db';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

/* ==========================================================================
   SECURITY & CSRF HELPERS
   ========================================================================== */

/**
 * Generates or retrieves a CSRF token stored in the user's session.
 */
function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates the submitted CSRF token against the session token.
 */
function verifyCsrfToken(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/* ==========================================================================
   DATA ACCESS FUNCTIONS
   ========================================================================== */

/**
 * Fetches all active treatment services available for booking.
 */
function getActiveServices(PDO $pdo): array {
    $stmt = $pdo->query("SELECT id, name, price, duration_minutes FROM services ORDER BY name ASC");
    return $stmt->fetchAll();
}

/**
 * Fetches booked appointment timestamps within a date range to check availability.
 *
 * @return array List of start_datetime strings ('Y-m-d H:i:s')
 */
function getBookedSlotsInRange(PDO $pdo, DateTime $startDate, DateTime $endDate): array {
    $stmt = $pdo->prepare("
        SELECT start_datetime 
        FROM booking_requests 
        WHERE appointment_status IN ('approved', 'scheduled') 
          AND start_datetime BETWEEN ? AND ?
    ");
    $stmt->execute([
        $startDate->format('Y-m-d 00:00:00'),
        $endDate->format('Y-m-d 23:59:59')
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Checks if a specific datetime slot is already booked in the DB.
 */
function isSlotBooked(PDO $pdo, string $startDatetime): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM booking_requests 
        WHERE start_datetime = ? 
          AND appointment_status IN ('approved', 'scheduled')
    ");
    $stmt->execute([$startDatetime]);
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * Inserts a new booking request into the database.
 */
function createBookingRecord(PDO $pdo, array $data): int {
    $stmt = $pdo->prepare("
        INSERT INTO booking_requests (
            service_id, 
            client_name, 
            client_email, 
            client_phone, 
            start_datetime, 
            appointment_status, 
            cancellation_token, 
            preferred_language
        ) VALUES (
            :service_id, 
            :client_name, 
            :client_email, 
            :client_phone, 
            :start_datetime, 
            'scheduled', 
            :cancellation_token, 
            :preferred_language
        )
    ");

    $stmt->execute([
        ':service_id'         => $data['service_id'],
        ':client_name'        => $data['client_name'],
        ':client_email'       => $data['client_email'],
        ':client_phone'       => $data['client_phone'],
        ':start_datetime'     => $data['start_datetime'],
        ':cancellation_token' => bin2hex(random_bytes(16)),
        ':preferred_language' => $data['preferred_language'] ?? 'sv'
    ]);

    return (int)$pdo->lastInsertId();
}

/**
 * Attaches the created Google Calendar Event ID to a booking.
 */
function updateBookingGcalEventId(PDO $pdo, int $bookingId, string $eventId): bool {
    $stmt = $pdo->prepare("UPDATE booking_requests SET gcal_event_id = ? WHERE id = ?");
    return $stmt->execute([$eventId, $bookingId]);
}