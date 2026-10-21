<?php
/**
 * src/classes/BookingService.php
 * Business logic for slot generation: opening hours, holidays, service duration and
 * cleanup buffer, minus slots already taken by pending/approved bookings.
 */

require_once __DIR__ . '/BookingRepository.php';
require_once __DIR__ . '/ResourceProvider.php';

class BookingService {
    private BookingRepository $repository;
    private DateTimeZone $timezone;

    public function __construct(?BookingRepository $repository = null) {
        $this->repository = $repository ?? new BookingRepository();
        $this->timezone = ResourceProvider::getTimezone();
    }

    /**
     * Available slots for a date and service. Returns null when the date is invalid or the
     * service is unknown/inactive/without duration; an empty array when closed or fully booked.
     */
    public function getAvailableSlots(string $dateStr, string $serviceId, string $countryCode = 'se'): ?array {
        $date = DateTime::createFromFormat('Y-m-d', $dateStr);
        if (!$date || $date->format('Y-m-d') !== $dateStr) {
            return null;
        }

        $service = $this->repository->getServiceById($serviceId);
        if ($service === null || !ResourceProvider::isServiceActive($service)) {
            return null;
        }
        $duration = ResourceProvider::serviceDuration($service);
        if ($duration === null) {
            return null;
        }
        $blockMinutes = $duration + ResourceProvider::serviceCleanup($service);

        if ($this->repository->isHoliday($dateStr, $countryCode)) {
            return [];
        }

        $hours = $this->repository->getOperatingHours(strtolower($date->format('l')));
        if ($hours === null || empty($hours['is_open']) || empty($hours['open_time']) || empty($hours['close_time'])) {
            return [];
        }

        $current = DateTime::createFromFormat('Y-m-d H:i', "{$dateStr} {$hours['open_time']}", $this->timezone);
        $closing = DateTime::createFromFormat('Y-m-d H:i', "{$dateStr} {$hours['close_time']}", $this->timezone);
        if (!$current || !$closing) {
            return [];
        }

        $occupied = $this->repository->getBlockingIntervals($dateStr);
        $slots = [];

        while (true) {
            $treatmentEnd = (clone $current)->modify("+{$duration} minutes");
            $blockEnd = (clone $current)->modify("+{$blockMinutes} minutes");
            if ($treatmentEnd > $closing) {
                break;
            }

            if (!$this->overlaps($current, $blockEnd, $occupied)) {
                $slots[] = [
                    'start_time' => $current->format('H:i'),
                    'end_time' => $treatmentEnd->format('H:i'),
                    'start_iso' => $current->format(DATE_ATOM),
                    'end_iso' => $treatmentEnd->format(DATE_ATOM),
                ];
            }
            $current = $blockEnd;
        }

        return $slots;
    }

    private function overlaps(DateTime $start, DateTime $end, array $intervals): bool {
        foreach ($intervals as $interval) {
            if ($start < $interval['end'] && $end > $interval['start']) {
                return true;
            }
        }
        return false;
    }
}
