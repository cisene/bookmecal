<?php
/**
 * src/classes/BookingService.php
 * Business logic for soft slots: a treatment may start at any step (SLOT_STEP minutes) within
 * opening hours, as long as treatment plus cleanup buffer does not overlap a pending/approved
 * booking. Also builds day and week plans (opening hours, busy intervals, free starts) for the
 * booking page's timeline.
 */

require_once __DIR__ . '/BookingRepository.php';
require_once __DIR__ . '/ResourceProvider.php';

class BookingService {
    /** Minutes between possible start times. */
    public const SLOT_STEP = 5;

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
        $date = $this->parseDate($dateStr);
        $service = $this->bookableService($serviceId);
        if ($date === null || $service === null) {
            return null;
        }
        return $this->planDay($date, $service, $countryCode)['slots'];
    }

    /**
     * Monday-to-Sunday plan for the week containing $dateStr: per day the opening hours,
     * busy intervals (bookings and time already passed) and free start times.
     * Returns null for an invalid date or a service that cannot be booked.
     */
    public function getWeekPlan(string $dateStr, string $serviceId, string $countryCode = 'se'): ?array {
        $date = $this->parseDate($dateStr);
        $service = $this->bookableService($serviceId);
        if ($date === null || $service === null) {
            return null;
        }

        $day = $date->modify('monday this week');
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = $this->planDay($day, $service, $countryCode);
            $day = $day->modify('+1 day');
        }

        return [
            'week_start' => $days[0]['date'],
            'step' => self::SLOT_STEP,
            'duration' => ResourceProvider::serviceDuration($service),
            'cleanup' => ResourceProvider::serviceCleanup($service),
            'days' => $days,
        ];
    }

    private function parseDate(string $dateStr): ?DateTimeImmutable {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateStr, $this->timezone);
        return ($date && $date->format('Y-m-d') === $dateStr) ? $date : null;
    }

    private function bookableService(string $serviceId): ?array {
        $service = $this->repository->getServiceById($serviceId);
        if ($service === null || !ResourceProvider::isServiceActive($service) || ResourceProvider::serviceDuration($service) === null) {
            return null;
        }
        return $service;
    }

    private function planDay(DateTimeImmutable $date, array $service, string $countryCode): array {
        $dateStr = $date->format('Y-m-d');
        $plan = ['date' => $dateStr, 'open' => null, 'close' => null, 'busy' => [], 'slots' => []];

        if ($this->repository->isHoliday($dateStr, $countryCode)) {
            return $plan;
        }
        $hours = $this->repository->getOperatingHours(strtolower($date->format('l')));
        if ($hours === null || empty($hours['is_open']) || empty($hours['open_time']) || empty($hours['close_time'])) {
            return $plan;
        }
        $opening = DateTime::createFromFormat('Y-m-d H:i', "{$dateStr} {$hours['open_time']}", $this->timezone);
        $closing = DateTime::createFromFormat('Y-m-d H:i', "{$dateStr} {$hours['close_time']}", $this->timezone);
        if (!$opening || !$closing || $closing <= $opening) {
            return $plan;
        }
        $plan['open'] = $opening->format('H:i');
        $plan['close'] = $closing->format('H:i');

        $occupied = $this->repository->getBlockingIntervals($dateStr);
        $now = new DateTime('now', $this->timezone);
        $busy = [];
        if ($now > $opening) {
            $busy[] = ['start' => $opening, 'end' => min($now, $closing), 'kind' => 'past'];
        }
        foreach ($occupied as $interval) {
            $start = max($interval['start'], $opening);
            $end = min($interval['end'], $closing);
            if ($start < $end) {
                $busy[] = ['start' => $start, 'end' => $end, 'kind' => 'booked'];
            }
        }
        usort($busy, fn ($a, $b) => $a['start'] <=> $b['start']);
        // Only times are exposed, never who booked
        $plan['busy'] = array_map(fn ($b) => ['start' => $b['start']->format('H:i'), 'end' => $b['end']->format('H:i'), 'kind' => $b['kind']], $busy);

        $duration = ResourceProvider::serviceDuration($service);
        $blockMinutes = $duration + ResourceProvider::serviceCleanup($service);
        for ($current = clone $opening; ; $current->modify('+' . self::SLOT_STEP . ' minutes')) {
            $treatmentEnd = (clone $current)->modify("+{$duration} minutes");
            if ($treatmentEnd > $closing) {
                break;
            }
            $blockEnd = (clone $current)->modify("+{$blockMinutes} minutes");
            if ($current >= $now && !$this->overlaps($current, $blockEnd, $occupied)) {
                $plan['slots'][] = [
                    'start_time' => $current->format('H:i'),
                    'end_time' => $treatmentEnd->format('H:i'),
                    'start_iso' => $current->format(DATE_ATOM),
                    'end_iso' => $treatmentEnd->format(DATE_ATOM),
                ];
            }
        }

        return $plan;
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
