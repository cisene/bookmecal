<?php
/**
 * src/classes/BookingService.php
 * Business logic layer for availability calculations, slot generation, 
 * and validation adhering to strict null fallbacks and 24-hour time standards.
 */

require_once __DIR__ . '/BookingRepository.php';
require_once __DIR__ . '/ResourceProvider.php';

class BookingService {
    private BookingRepository $repository;
    private DateTimeZone $timezone;

    public function __construct() {
        $this->repository = new BookingRepository();
        $settings = ResourceProvider::getSettings();
        $tzString = ($settings && isset($settings['timezone'])) ? $settings['timezone'] : 'Europe/Stockholm';
        $this->timezone = new DateTimeZone($tzString);
    }

    /**
     * Calculates available time slots for a specific date and service.
     * Returns NULL if parameters are invalid, or an array of slots (empty if closed/holiday).
     */
    public function getAvailableSlots(string $dateStr, string $serviceId, string $countryCode = 'se'): ?array {
        // Validate strict ISO date format (YYYY-MM-DD)
        $d = DateTime::createFromFormat('Y-m-d', $dateStr);
        if (!$d || $d->format('Y-m-d') !== $dateStr) {
            return null;
        }

        // Check regional holiday status
        if ($this->repository->isHoliday($dateStr, $countryCode)) {
            return [];
        }

        // Get operating hours for the specific day of the week
        $dayOfWeek = strtolower($d->format('l'));
        $hours = $this->repository->getOperatingHours($dayOfWeek);

        // Strict null fallback: if opening hours are unconfigured or explicitly closed
        if ($hours === null || empty($hours['is_open'])) {
            return [];
        }

        $openTimeStr = $hours['open_time'] ?? null;
        $closeTimeStr = $hours['close_time'] ?? null;

        if ($openTimeStr === null || $closeTimeStr === null) {
            return [];
        }

        // Retrieve service details
        $service = $this->repository->getServiceById($serviceId);
        if ($service === null) {
            return null;
        }

        $durationMinutes = isset($service['duration_minutes']) ? (int)$service['duration_minutes'] : null;
        if ($durationMinutes === null || $durationMinutes <= 0) {
            return null; // No valid duration assumed
        }

        // Generate 24-hour time slots
        $slots = [];
        $startTime = DateTime::createFromFormat('Y-m-d H:i', "{$dateStr} {$openTimeStr}", $this->timezone);
        $endTime = DateTime::createFromFormat('Y-m-d H:i', "{$dateStr} {$closeTimeStr}", $this->timezone);

        if (!$startTime || !$endTime) {
            return [];
        }

        while ($startTime < $endTime) {
            $slotEnd = clone $startTime;
            $slotEnd->modify("+{$durationMinutes} minutes");

            if ($slotEnd > $endTime) {
                break;
            }

            $slots[] = [
                'start_time' => $startTime->format('H:i'),
                'end_time' => $slotEnd->format('H:i'),
                'start_iso' => $startTime->format('Y-m-d\TH:i:sP'),
                'end_iso' => $slotEnd->format('Y-m-d\TH:i:sP')
            ];

            $startTime = $slotEnd;
        }

        return $slots;
    }
}