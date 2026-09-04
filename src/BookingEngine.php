<?php

class BookingEngine {
    private $pdo;
    private $slotDurationMinutes;

    public function __construct(PDO $pdo, int $slotDurationMinutes = 60) {
        $this->pdo = $pdo;
        $this->slotDurationMinutes = $slotDurationMinutes;
    }

    /**
     * Generates available bookable slots for a specific date.
     */
    public function getAvailableSlots(string $dateStr): array {
        $date = new DateTime($dateStr);
        $dayOfWeek = (int)$date->format('w');

        // 1. Check Holiday Matrix
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM holiday_matrix WHERE holiday_date = ?");
        $stmt->execute([$dateStr]);
        if ($stmt->fetchColumn() > 0) {
            return []; // Closed on holiday
        }

        // 2. Fetch Operating Hours for the Day
        $stmt = $this->pdo->prepare("SELECT start_time, end_time FROM slot_matrix WHERE day_of_week = ? AND is_active = 1");
        $stmt->execute([$dayOfWeek]);
        $matrix = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$matrix) {
            return []; // Closed on this day of week
        }

        // 3. Generate Raw Slots based on configurable duration
        $startTime = new DateTime($dateStr . ' ' . $matrix['start_time']);
        $endTime   = new DateTime($dateStr . ' ' . $matrix['end_time']);
        
        $slots = [];
        $interval = new DateInterval("PT{$this->slotDurationMinutes}M");

        $current = clone $startTime;
        while ($current < $endTime) {
            $slotEnd = clone $current;
            $slotEnd->add($interval);

            if ($slotEnd > $endTime) break;

            $slots[] = [
                'start' => $current->format('Y-m-d H:i:s'),
                'end'   => $slotEnd->format('Y-m-d H:i:s')
            ];

            $current->add($interval);
        }

        // 4. Filter Out Already Reserved / Pending Slots
        return array_values(array_filter($slots, function($slot) {
            return !$this->isSlotOccupied($slot['start'], $slot['end']);
        }));
    }

    private function isSlotOccupied(string $start, string $end): bool {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM booking_requests 
            WHERE status IN ('pending', 'approved') 
            AND (start_datetime < ? AND end_datetime > ?)
        ");
        $stmt->execute([$end, $start]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Creates a tentative booking request.
     */
    public function createPendingRequest(string $name, string $email, string $start, string $end): int {
        if ($this->isSlotOccupied($start, $end)) {
            throw new Exception("Slot is no longer available.");
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO booking_requests (client_name, client_email, start_datetime, end_datetime, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$name, $email, $start, $end]);
        return (int)$this->pdo->lastInsertId();
    }
}