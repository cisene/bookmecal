<?php
/**
 * src/classes/BookingService.php
 * Business logic for soft slots: a treatment may start at any step (SLOT_STEP minutes) where some
 * bookable employee offering it works for the whole treatment, within opening hours, and has no
 * pending/approved booking overlapping treatment plus cleanup buffer. Opening hours are the outer
 * limit, each employee's weekly hours (Staff) the inner one; several employees can be booked at
 * the same time, except around an exclusive treatment, which needs the venue to itself. A set
 * (a service with 'items') is done back to back by one employee offering all its treatments.
 * Without any bookable employee the venue itself is the one bookable resource.
 * With settings.json avoid_small_gaps, starts that would leave a gap too short for any treatment
 * are not offered to customers.
 * Also builds day and week plans (opening hours, busy intervals, free starts) for the booking
 * page's timeline.
 */

require_once __DIR__ . '/BookingRepository.php';
require_once __DIR__ . '/EmployeeRepository.php';
require_once __DIR__ . '/ResourceProvider.php';
require_once __DIR__ . '/Staff.php';

class BookingService {
    /** Minutes between possible start times. */
    public const SLOT_STEP = 5;

    private BookingRepository $repository;
    private DateTimeZone $timezone;
    private ?array $employees;
    private bool $avoidSmallGaps;

    /**
     * $employees: employee records keyed by ID; loaded from data/employees/ when null.
     * $avoidSmallGaps: settings.json avoid_small_gaps when null.
     */
    public function __construct(?BookingRepository $repository = null, ?array $employees = null, ?bool $avoidSmallGaps = null) {
        $this->repository = $repository ?? new BookingRepository();
        $this->timezone = ResourceProvider::getTimezone();
        $this->employees = $employees;
        $this->avoidSmallGaps = $avoidSmallGaps ?? (ResourceProvider::getSettings()['avoid_small_gaps'] ?? false) === true;
    }

    /** All employee records keyed by ID. */
    public function employees(): array {
        return $this->employees ??= (new EmployeeRepository())->getAll();
    }

    /** Whether bookings go to employees (true) or to the venue as a whole (no bookable employee). */
    public function hasStaff(): bool {
        return array_filter($this->employees(), fn ($e) => is_array($e) && Staff::isBookable($e)) !== [];
    }

    /** Bookable employees offering the service, keyed by ID; empty for an unknown service. */
    public function staffFor(string $serviceId): array {
        $service = $this->repository->getServiceById($serviceId);
        return $service === null ? [] : $this->eligibleStaff($serviceId, $service);
    }

    /**
     * Available slots for a date and service, optionally for one employee. Returns null when the
     * date is invalid, the service is unknown/inactive/without duration or the employee does not
     * offer it; an empty array when closed or fully booked.
     */
    public function getAvailableSlots(string $dateStr, string $serviceId, string $countryCode = 'se', ?string $employeeId = null): ?array {
        $date = $this->parseDate($dateStr);
        $service = $this->bookableService($serviceId);
        $resources = $service === null ? null : $this->resources($serviceId, $service, $employeeId);
        if ($date === null || $resources === null) {
            return null;
        }
        return self::publicSlots($this->planDay($date, $service, $resources, $countryCode)['slots']);
    }

    /**
     * Monday-to-Sunday plan for the week containing $dateStr: per day the opening hours,
     * busy intervals (bookings, time already passed, nobody working) and free start times.
     * Returns null for an invalid date, a service that cannot be booked or an employee who does not offer it.
     */
    public function getWeekPlan(string $dateStr, string $serviceId, string $countryCode = 'se', ?string $employeeId = null): ?array {
        $date = $this->parseDate($dateStr);
        $service = $this->bookableService($serviceId);
        $resources = $service === null ? null : $this->resources($serviceId, $service, $employeeId);
        if ($date === null || $resources === null) {
            return null;
        }

        $day = $date->modify('monday this week');
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $plan = $this->planDay($day, $service, $resources, $countryCode);
            $plan['slots'] = self::publicSlots($plan['slots']);
            $days[] = $plan;
            $day = $day->modify('+1 day');
        }

        return [
            'week_start' => $days[0]['date'],
            'step' => self::SLOT_STEP,
            'duration' => ResourceProvider::serviceDuration($service, $this->services()),
            'cleanup' => ResourceProvider::serviceCleanup($service, $this->services()),
            'days' => $days,
        ];
    }

    /**
     * Who takes a booking at $time: the chosen employee if free, otherwise (no choice) the free
     * employee with the fewest bookings that day. Returns ['employee_id' => ?string, 'end_time' => 'HH:MM'],
     * employee_id null when there is no staff, or null when the time is not free.
     * Call inside BookingRepository::withBookingLock() together with saving the booking.
     */
    public function assign(string $dateStr, string $serviceId, string $time, ?string $employeeId = null, string $countryCode = 'se'): ?array {
        $date = $this->parseDate($dateStr);
        $service = $this->bookableService($serviceId);
        $resources = $service === null ? null : $this->resources($serviceId, $service, $employeeId);
        if ($date === null || $resources === null) {
            return null;
        }
        foreach ($this->planDay($date, $service, $resources, $countryCode)['slots'] as $slot) {
            if ($slot['start_time'] !== $time) {
                continue;
            }
            $load = array_count_values(array_filter(array_column($this->repository->getBlockingIntervals($dateStr), 'employee_id'), 'is_string'));
            $best = null;
            foreach ($slot['employees'] as $id) {
                if ($best === null || ($load[$id] ?? 0) < ($load[$best] ?? 0)) {
                    $best = $id;
                }
            }
            return ['employee_id' => $best, 'end_time' => $slot['end_time']];
        }
        return null;
    }

    /**
     * Whether a pending or approved booking can move to employee $employeeId: the employee offers
     * the treatment and is free for it (the booking's own time does not count against them).
     * Call inside BookingRepository::withBookingLock() together with saving the change.
     */
    public function canMoveTo(array $booking, string $employeeId, string $countryCode = 'se'): bool {
        $serviceId = (string)($booking['service_id'] ?? '');
        $date = $this->parseDate((string)($booking['date'] ?? ''));
        $service = $this->bookableService($serviceId);
        $resources = $service === null || $employeeId === '' ? null : $this->resources($serviceId, $service, $employeeId);
        if ($date === null || $resources === null || ($booking['employee_id'] ?? null) === $employeeId
            || !in_array($booking['status'] ?? '', BookingRepository::BLOCKING_STATUSES, true)) {
            return false;
        }
        // The admin may move a booking to any free time, gaps or not
        $plan = $this->planDay($date, $service, $resources, $countryCode, (string)($booking['id'] ?? ''), false);
        return in_array((string)($booking['time'] ?? ''), array_column($plan['slots'], 'start_time'), true);
    }

    private function parseDate(string $dateStr): ?DateTimeImmutable {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateStr, $this->timezone);
        return ($date && $date->format('Y-m-d') === $dateStr) ? $date : null;
    }

    private function bookableService(string $serviceId): ?array {
        $service = $this->repository->getServiceById($serviceId);
        if ($service === null || !ResourceProvider::isServiceActive($service) || ResourceProvider::serviceDuration($service, $this->services()) === null) {
            return null;
        }
        return $service;
    }

    /** All services keyed by key, for resolving sets. */
    private function services(): array {
        return $this->repository->getServices() ?? [];
    }

    /**
     * Bookable employees who can do the service: for a set, those offering every treatment in it.
     */
    private function eligibleStaff(string $serviceId, array $service): array {
        $items = ResourceProvider::serviceItems($service);
        if ($items === []) {
            return Staff::forService($this->employees(), $this->serviceIds($serviceId, $service));
        }
        $services = $this->services();
        $staff = Staff::forService($this->employees(), []);
        foreach ($items as $key) {
            $ids = $this->serviceIds($key, is_array($services[$key] ?? null) ? $services[$key] : []);
            $staff = array_filter($staff, fn ($e) => Staff::offers($e, $ids));
        }
        return $staff;
    }

    /** Names the service goes by, for matching an employee's treatment list. */
    private function serviceIds(string $serviceId, array $service): array {
        return array_unique(array_filter([$serviceId, (string)($service['sku'] ?? ''), (string)($service['id'] ?? '')], 'strlen'));
    }

    /**
     * Bookable resources as employee ID => record. Without staff: [null-keyed venue] as ['' => null].
     * Returns null when $employeeId is given but cannot be booked for this service.
     */
    private function resources(string $serviceId, array $service, ?string $employeeId): ?array {
        if (!$this->hasStaff()) {
            return $employeeId === null || $employeeId === '' ? ['' => null] : null;
        }
        $staff = $this->eligibleStaff($serviceId, $service);
        if ($employeeId === null || $employeeId === '') {
            return $staff;
        }
        return isset($staff[$employeeId]) ? [$employeeId => $staff[$employeeId]] : null;
    }

    /** Slots without the internal list of free employees. */
    private static function publicSlots(array $slots): array {
        return array_map(function (array $slot) {
            unset($slot['employees']);
            return $slot;
        }, $slots);
    }

    private static function minuteOfDay(DateTimeInterface $time): int {
        return (int)$time->format('G') * 60 + (int)$time->format('i');
    }

    /**
     * $ignoreId: a booking left out of the busy times, when checking whether it can move.
     * $avoidGaps: leave out starts that strand a short gap (null = the setting).
     */
    private function planDay(DateTimeImmutable $date, array $service, array $resources, string $countryCode, string $ignoreId = '', ?bool $avoidGaps = null): array {
        $dateStr = $date->format('Y-m-d');
        $plan = ['date' => $dateStr, 'open' => null, 'close' => null, 'busy' => [], 'slots' => []];

        if ($this->repository->isHoliday($dateStr, $countryCode)) {
            return $plan;
        }
        $weekday = strtolower($date->format('l'));
        $hours = $this->repository->getOperatingHours($weekday);
        if ($hours === null || empty($hours['is_open']) || empty($hours['open_time']) || empty($hours['close_time'])) {
            return $plan;
        }
        $opening = DateTime::createFromFormat('Y-m-d H:i', "{$dateStr} {$hours['open_time']}", $this->timezone);
        $closing = DateTime::createFromFormat('Y-m-d H:i', "{$dateStr} {$hours['close_time']}", $this->timezone);
        if (!$opening || !$closing || $closing <= $opening || $closing->format('Y-m-d') !== $dateStr) {
            return $plan;
        }
        $plan['open'] = $opening->format('H:i');
        $plan['close'] = $closing->format('H:i');
        $open = self::minuteOfDay($opening);
        $close = self::minuteOfDay($closing);

        // Everything before $now (in minutes of this day) has passed
        $now = new DateTimeImmutable('now', $this->timezone);
        $today = $now->format('Y-m-d');
        $nowMinute = $dateStr < $today ? 24 * 60 : ($dateStr > $today ? 0 : self::minuteOfDay($now) + ((int)$now->format('s') > 0 ? 1 : 0));

        $bookings = [];
        foreach ($this->repository->getBlockingIntervals($dateStr) as $interval) {
            if ($interval['start']->format('Y-m-d') !== $dateStr || ($ignoreId !== '' && ($interval['id'] ?? '') === $ignoreId)) {
                continue;
            }
            $end = $interval['end']->format('Y-m-d') > $dateStr ? 24 * 60 : self::minuteOfDay($interval['end']);
            $bookings[] = [
                'from' => self::minuteOfDay($interval['start']),
                'to' => $end,
                'employee_id' => $interval['employee_id'] ?? null,
                'exclusive' => !empty($interval['exclusive']),
            ];
        }
        // An exclusive treatment needs the venue to itself: every booking blocks it
        $exclusive = ResourceProvider::isServiceExclusive($service, $this->services());

        // Per resource: working periods within opening hours, and the bookings that block it
        $staff = [];
        foreach ($resources as $id => $employee) {
            $id = (string)$id;
            $staff[$id] = [
                'windows' => Staff::windows($employee === null ? null : Staff::hours($employee, $weekday, $dateStr), $open, $close),
                // Bookings without an employee (older records, or no staff) and exclusive ones block everyone
                'booked' => array_values(array_filter($bookings, fn ($b) => $exclusive || $b['exclusive']
                    || $id === '' || $b['employee_id'] === null || $b['employee_id'] === $id)),
            ];
        }

        $plan['busy'] = $this->busyIntervals($staff, $open, $close, $nowMinute);

        $duration = ResourceProvider::serviceDuration($service, $this->services());
        $blockMinutes = $duration + ResourceProvider::serviceCleanup($service, $this->services());
        $smallestGap = ($avoidGaps ?? $this->avoidSmallGaps) ? $this->shortestTreatment() : null;
        $firstStart = $open + (int)ceil(max(0, $nowMinute - $open) / self::SLOT_STEP) * self::SLOT_STEP;
        foreach ($staff as $id => $resource) {
            $staff[$id]['free'] = $smallestGap === null ? [] : self::freePeriods($resource['windows'], $resource['booked']);
        }
        for ($start = $open; $start + $duration <= $close; $start += self::SLOT_STEP) {
            if ($start < $nowMinute) {
                continue;
            }
            $free = [];
            foreach ($staff as $id => $resource) {
                if (self::within($start, $start + $duration, $resource['windows'])
                    && !self::overlaps($start, $start + $blockMinutes, $resource['booked'])
                    && ($smallestGap === null || self::leavesUsableGaps($start, $duration, $blockMinutes, $resource['free'], $firstStart, $smallestGap))) {
                    $free[] = $id === '' ? null : $id;
                }
            }
            if ($free) {
                $startTime = (clone $opening)->setTime(intdiv($start, 60), $start % 60);
                $endTime = (clone $startTime)->modify("+{$duration} minutes");
                $plan['slots'][] = [
                    'start_time' => $startTime->format('H:i'),
                    'end_time' => $endTime->format('H:i'),
                    'start_iso' => $startTime->format(DATE_ATOM),
                    'end_iso' => $endTime->format(DATE_ATOM),
                    'employees' => $free,
                ];
            }
        }

        return $plan;
    }

    /**
     * Times within opening hours when nobody can take a customer, as [['start', 'end', 'kind'], ...]:
     * 'past', 'booked' (someone works but is booked) or 'off' (nobody works). Only times are
     * exposed, never who booked.
     */
    private function busyIntervals(array $staff, int $open, int $close, int $nowMinute): array {
        $busy = [];
        $current = null;
        for ($minute = $open; $minute < $close; $minute++) {
            if ($minute < $nowMinute) {
                $kind = 'past';
            } else {
                $kind = 'off';
                foreach ($staff as $resource) {
                    if (!self::within($minute, $minute + 1, $resource['windows'])) {
                        continue;
                    }
                    if (!self::overlaps($minute, $minute + 1, $resource['booked'])) {
                        $kind = null;
                        break;
                    }
                    $kind = 'booked';
                }
            }
            if ($current !== null && $current['kind'] === $kind) {
                $current['end'] = $minute + 1;
                continue;
            }
            if ($current !== null && $current['kind'] !== null) {
                $busy[] = $current;
            }
            $current = ['start' => $minute, 'end' => $minute + 1, 'kind' => $kind];
        }
        if ($current !== null && $current['kind'] !== null) {
            $busy[] = $current;
        }
        $format = fn (int $m) => sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        return array_map(fn ($b) => ['start' => $format($b['start']), 'end' => $format($b['end']), 'kind' => $b['kind']], $busy);
    }

    /** Minutes the shortest bookable treatment needs including cleanup, or null when there is none. */
    private function shortestTreatment(): ?int {
        $shortest = null;
        foreach ($this->services() as $service) {
            $duration = is_array($service) && ResourceProvider::isServiceActive($service) ? ResourceProvider::serviceDuration($service, $this->services()) : null;
            if ($duration !== null && $duration > 0) {
                $minutes = $duration + ResourceProvider::serviceCleanup($service, $this->services());
                $shortest = $shortest === null ? $minutes : min($shortest, $minutes);
            }
        }
        return $shortest;
    }

    /**
     * Working periods minus the bookings: [[from, to, endsAtBooking], ...] in minutes;
     * endsAtBooking is false where the period runs to the end of the working period.
     */
    private static function freePeriods(array $windows, array $booked): array {
        usort($booked, fn ($a, $b) => $a['from'] <=> $b['from']);
        $free = [];
        foreach ($windows as [$from, $to]) {
            foreach ($booked as $booking) {
                if ($booking['to'] <= $from || $booking['from'] >= $to) {
                    continue;
                }
                if ($booking['from'] > $from) {
                    $free[] = [$from, $booking['from'], true];
                }
                $from = max($from, $booking['to']);
            }
            if ($from < $to) {
                $free[] = [$from, $to, false];
            }
        }
        return $free;
    }

    /**
     * Whether a treatment of $duration minutes plus cleanup ($block in all) starting at $start
     * leaves no gap shorter than $smallest minutes in its free period. A gap of 0 is fine; before
     * the end of the working period only the treatment counts, as cleanup may run past it. Time
     * before $firstStart (the first start not yet passed) is no gap. When the period leaves less
     * than $smallest whatever the start, only starts at either edge are kept, so a period never
     * loses all its starts.
     */
    private static function leavesUsableGaps(int $start, int $duration, int $block, array $free, int $firstStart, int $smallest): bool {
        foreach ($free as [$from, $to, $endsAtBooking]) {
            if ($start < $from || $start >= $to) {
                continue;
            }
            $before = $start - max($from, $firstStart);
            $after = max(0, $to - $start - ($endsAtBooking ? $block : $duration));
            if ($before + $after < $smallest) {
                return $before === 0 || $after === 0;
            }
            return ($before === 0 || $before >= $smallest) && ($after === 0 || $after >= $smallest);
        }
        return true;
    }

    /** Whether [$from, $to) lies inside one of the periods. */
    private static function within(int $from, int $to, array $windows): bool {
        foreach ($windows as [$start, $end]) {
            if ($from >= $start && $to <= $end) {
                return true;
            }
        }
        return false;
    }

    private static function overlaps(int $from, int $to, array $bookings): bool {
        foreach ($bookings as $booking) {
            if ($from < $booking['to'] && $to > $booking['from']) {
                return true;
            }
        }
        return false;
    }
}
