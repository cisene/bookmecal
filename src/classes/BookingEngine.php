<?php
// BookingEngine.php - Core business logic for slot generation and request validation

class BookingEngine {
    private int $slotDurationMinutes;
    private BookingRepository $repository;

    public function __construct(BookingRepository $repository, int $slotDurationMinutes = 60) {
        $this->repository = $repository;
        $this->slotDurationMinutes = $slotDurationMinutes;
    }

    /**
     * Generates available bookable time slots for a specific date.
     * @return TimeSlot[]
     */
    public function getAvailableSlots(string $dateStr): array {
        if ($this->repository->isHoliday($dateStr)) {
            return []; // Closed on holidays
        }

        $date = new DateTime($dateStr);
        $dayOfWeek = (int)$date->format('w');
        
        $matrix = $this->repository->getOperatingHours($dayOfWeek);
        if (!$matrix) {
            return []; // Closed on this day of the week
        }

        $startTime = new DateTime($dateStr . ' ' . $matrix['start_time']);
        $endTime   = new DateTime($dateStr . ' ' . $matrix['end_time']);
        
        $slots = [];
        $interval = new DateInterval("PT{$this->slotDurationMinutes}M");
        $current = clone $startTime;

        while ($current < $endTime) {
            $slotEnd = clone $current;
            $slotEnd->add($interval);

            if ($slotEnd > $endTime) break;

            $slot = new TimeSlot(clone $current, clone $slotEnd);

            if (!$this->repository->isSlotOccupied($slot->getStart()->format('Y-m-d H:i:s'), $slot->getEnd()->format('Y-m-d H:i:s'))) {
                $slots[] = $slot;
            }

            $current->add($interval);
        }

        return $slots;
    }

    public function createPendingRequest(string $name, string $email, string $start, string $end): int {
        if ($this->repository->isSlotOccupied($start, $end)) {
            throw new Exception("The selected time slot is no longer available.");
        }

        return $this->repository->createRequest($name, $email, $start, $end);
    }
}