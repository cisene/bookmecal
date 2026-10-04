<?php

class BookingRepository {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Kontrollerar om ett visst datum är en blockerande helgdag.
     */
    public function isHoliday(string $dateStr): bool {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM holiday_matrix WHERE holiday_date = ?");
        $stmt->execute([$dateStr]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Hämtar öppettider för en specifik veckodag (0 = söndag, 1 = måndag, etc.).
     */
    public function getOperatingHours(int $dayOfWeek): ?array {
        $stmt = $this->pdo->prepare("SELECT start_time, end_time FROM slot_matrix WHERE day_of_week = ? AND is_active = 1");
        $stmt->execute([$dayOfWeek]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Kontrollerar om en viss tidsintervall krockar med existerande bokningar.
     */
    public function isSlotOccupied(string $start, string $end): bool {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM booking_requests 
            WHERE status IN ('pending', 'approved') 
            AND (start_datetime < ? AND end_datetime > ?)
        ");
        $stmt->execute([$end, $start]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Kontrollerar om en specifik starttid redan är bokad.
     */
    public function isSlotBooked(string $startDatetime): bool {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM booking_requests 
            WHERE status IN ('pending', 'approved') 
            AND start_datetime = ?
        ");
        $stmt->execute([$startDatetime]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Hämtar alla aktiva tjänster.
     */
    public function getActiveServices(): array {
        $stmt = $this->pdo->prepare("SELECT * FROM services WHERE is_active = 1 ORDER BY id ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Skapar en bokningspost i databasen.
     */
    public function createBookingRecord(array $data): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO booking_requests (
                service_id, client_name, client_email, client_phone, 
                start_datetime, preferred_language, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([
            $data['service_id'] ?? null,
            $data['client_name'],
            $data['client_email'],
            $data['client_phone'] ?? '',
            $data['start_datetime'],
            $data['preferred_language'] ?? 'sv'
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Uppdaterar en bokning med ett Google Calendar Event-ID.
     */
    public function updateGcalEventId(int $bookingId, string $eventId): void {
        $stmt = $this->pdo->prepare("
            UPDATE booking_requests 
            SET gcal_event_id = ? 
            WHERE id = ?
        ");
        $stmt->execute([$eventId, $bookingId]);
    }

    /**
     * Hämtar en specifik bokning via ID.
     */
    public function getBookingById(int $bookingId): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM booking_requests WHERE id = ?");
        $stmt->execute([$bookingId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}
