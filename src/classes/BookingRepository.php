<?php
/**
 * src/classes/BookingRepository.php
 * Data access for bookings (one JSON file per booking in data/pending/) plus the
 * opening-hours and holiday lookups the booking logic needs.
 */

require_once __DIR__ . '/JsonStore.php';
require_once __DIR__ . '/ResourceProvider.php';

class BookingRepository {
    /** Statuses that keep a slot occupied. Rejected/cancelled bookings free their slot again. */
    public const BLOCKING_STATUSES = ['pending', 'approved', 'confirmed'];

    /** Results of cancelBooking() / cancelState(). */
    public const CANCEL_OK = 'cancelled';
    public const CANCEL_INACTIVE = 'inactive';
    public const CANCEL_TOO_LATE = 'too_late';
    public const CANCEL_NOT_FOUND = 'not_found';

    /** Booking IDs come from uniqid('book_'); anything else could escape the bookings directory. */
    private const ID_PATTERN = '/^book_[0-9a-f]+$/';

    private string $bookingDir;
    private DateTimeZone $timezone;

    public function __construct(?string $bookingDir = null) {
        $this->bookingDir = $bookingDir ?? ResourceProvider::dataPath('pending');
        $this->timezone = ResourceProvider::getTimezone();
    }

    public function getServices(): ?array {
        return ResourceProvider::getServices();
    }

    public function getServiceById(string $serviceId): ?array {
        return ResourceProvider::getServiceById($serviceId);
    }

    public function isHoliday(string $dateStr, string $countryCode = 'se'): bool {
        foreach (ResourceProvider::getHolidays($countryCode) ?? [] as $holiday) {
            if (($holiday['date'] ?? null) === $dateStr) {
                return true;
            }
        }
        return false;
    }

    public function getOperatingHours(string $dayOfWeek): ?array {
        $hours = ResourceProvider::getOpenHours();
        $day = $hours[strtolower($dayOfWeek)] ?? null;
        return is_array($day) ? $day : null;
    }

    public static function isValidId(string $bookingId): bool {
        return preg_match(self::ID_PATTERN, $bookingId) === 1;
    }

    private function bookingPath(string $bookingId): string {
        return $this->bookingDir . '/booking_' . $bookingId . '.json';
    }

    /**
     * All bookings keyed by ID, sorted by date and time.
     */
    public function getBookings(): array {
        $bookings = [];
        foreach (glob($this->bookingDir . '/booking_*.json') ?: [] as $file) {
            $booking = JsonStore::read($file);
            if ($booking !== null && isset($booking['id']) && self::isValidId((string)$booking['id'])) {
                $bookings[$booking['id']] = $booking;
            }
        }
        uasort($bookings, function (array $a, array $b) {
            return strcmp(($a['date'] ?? '') . ' ' . ($a['time'] ?? ''), ($b['date'] ?? '') . ' ' . ($b['time'] ?? ''));
        });
        return $bookings;
    }

    /**
     * Applies $changes to a stored booking. Returns false for unknown or invalid IDs.
     */
    public function updateBooking(string $bookingId, array $changes): bool {
        if (!self::isValidId($bookingId) || !is_file($this->bookingPath($bookingId))) {
            return false;
        }
        return JsonStore::update($this->bookingPath($bookingId), function (array $booking) use ($changes) {
            return array_merge($booking, $changes);
        });
    }

    /**
     * Marks pending bookings created more than $hours ago as 'expired', which frees their time.
     * Keeps unanswered (or spam) requests from blocking the calendar. Returns the number expired.
     */
    public function expireStalePending(int $hours, ?DateTimeInterface $now = null): int {
        if ($hours <= 0) {
            return 0;
        }
        $now = $now ?? new DateTimeImmutable('now', $this->timezone);
        $cutoff = DateTimeImmutable::createFromInterface($now)->modify("-{$hours} hours");
        $expired = 0;
        foreach ($this->getBookings() as $id => $booking) {
            $created = DateTimeImmutable::createFromFormat(DATE_ATOM, (string)($booking['created_at_iso'] ?? ''));
            if (($booking['status'] ?? '') !== 'pending' || !$created || $created > $cutoff) {
                continue;
            }
            // Re-check under the file lock so an approval made meanwhile is not overwritten
            JsonStore::update($this->bookingPath($id), function (array $current) use ($now, &$expired) {
                if (($current['status'] ?? '') === 'pending') {
                    $current['status'] = 'expired';
                    $current['expired_at_iso'] = $now->format(DATE_ATOM);
                    $expired++;
                }
                return $current;
            });
        }
        return $expired;
    }

    /**
     * Deletes bookings whose appointment date is more than $days days ago (data minimisation).
     * Returns the number deleted. $days <= 0 keeps everything.
     */
    public function purgeEndedBefore(int $days, ?DateTimeInterface $now = null): int {
        if ($days <= 0) {
            return 0;
        }
        $now = $now ?? new DateTimeImmutable('now', $this->timezone);
        $cutoff = DateTimeImmutable::createFromInterface($now)->setTimezone($this->timezone)->modify("-{$days} days")->format('Y-m-d');
        $deleted = 0;
        foreach ($this->getBookings() as $id => $booking) {
            $date = (string)($booking['date'] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $date < $cutoff && $this->deleteBooking($id)) {
                $deleted++;
            }
        }
        return $deleted;
    }

    /**
     * Routine upkeep before bookings are read: expires unanswered requests and deletes
     * bookings past the retention period, as configured in settings.json.
     */
    public function housekeeping(): void {
        $this->expireStalePending(ResourceProvider::pendingExpiryHours());
        $this->purgeEndedBefore(ResourceProvider::bookingRetentionDays());
    }

    /**
     * Permanently removes one booking file. Returns false for unknown or invalid IDs.
     */
    public function deleteBooking(string $bookingId): bool {
        return self::isValidId($bookingId) && JsonStore::delete($this->bookingPath($bookingId));
    }

    /**
     * Customer cancellation: sets a pending or approved booking to 'cancelled' if it starts more than
     * $cutoffHours from $now. Checked under the file lock. Returns one of the CANCEL_* constants.
     */
    public function cancelBooking(string $bookingId, int $cutoffHours, ?DateTimeInterface $now = null): string {
        if (!self::isValidId($bookingId) || !is_file($this->bookingPath($bookingId))) {
            return self::CANCEL_NOT_FOUND;
        }
        $now = DateTimeImmutable::createFromInterface($now ?? new DateTimeImmutable('now', $this->timezone));
        $result = self::CANCEL_NOT_FOUND;
        JsonStore::update($this->bookingPath($bookingId), function (array $booking) use ($cutoffHours, $now, &$result) {
            $result = $this->cancelState($booking, $cutoffHours, $now);
            if ($result === self::CANCEL_OK) {
                $booking['status'] = 'cancelled';
                $booking['cancelled_at_iso'] = $now->format(DATE_ATOM);
                $booking['cancelled_by'] = 'customer';
            }
            return $booking;
        });
        return $result;
    }

    /**
     * Whether a booking can be cancelled by the customer now: CANCEL_OK, CANCEL_INACTIVE
     * (already cancelled, rejected, expired) or CANCEL_TOO_LATE.
     */
    public function cancelState(array $booking, int $cutoffHours, ?DateTimeInterface $now = null): string {
        if (!in_array($booking['status'] ?? '', self::BLOCKING_STATUSES, true)) {
            return self::CANCEL_INACTIVE;
        }
        $start = $this->bookingStart($booking);
        $now = DateTimeImmutable::createFromInterface($now ?? new DateTimeImmutable('now', $this->timezone));
        if ($start === null || $now->modify('+' . max(0, $cutoffHours) . ' hours') >= $start) {
            return self::CANCEL_TOO_LATE;
        }
        return self::CANCEL_OK;
    }

    /**
     * Bookings for an email address (case-insensitive), keyed by ID, sorted by date.
     */
    public function getBookingsFor(string $email): array {
        $email = mb_strtolower(trim($email));
        return array_filter($this->getBookings(), function (array $booking) use ($email) {
            return mb_strtolower((string)($booking['customer_email'] ?? '')) === $email;
        });
    }

    /**
     * Pending bookings for an email address (case-insensitive) that have not started yet.
     */
    public function countUpcomingPendingFor(string $email, ?DateTimeInterface $now = null): int {
        $now = $now ?? new DateTimeImmutable('now', $this->timezone);
        $email = mb_strtolower($email);
        $count = 0;
        foreach ($this->getBookings() as $booking) {
            $start = $this->bookingStart($booking);
            if (($booking['status'] ?? '') === 'pending' && $start !== null && $start >= $now
                && mb_strtolower((string)($booking['customer_email'] ?? '')) === $email) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Start of a booking as a DateTime in the configured timezone, or null if the record is malformed.
     */
    public function bookingStart(array $booking): ?DateTime {
        $start = DateTime::createFromFormat('Y-m-d H:i', ($booking['date'] ?? '') . ' ' . ($booking['time'] ?? ''), $this->timezone);
        return $start ?: null;
    }

    /**
     * Occupied intervals for a date as [['start' => DateTime, 'end' => DateTime, 'employee_id' => ?string, 'exclusive' => bool], ...],
     * where 'end' includes the booked service's cleanup buffer. employee_id is null for bookings
     * made before employees were assigned; those block every employee, as do bookings of an
     * exclusive treatment.
     */
    public function getBlockingIntervals(string $dateStr): array {
        $services = $this->getServices() ?? [];
        $intervals = [];
        foreach ($this->getBookings() as $booking) {
            if (($booking['date'] ?? null) !== $dateStr || !in_array($booking['status'] ?? 'pending', self::BLOCKING_STATUSES, true)) {
                continue;
            }
            $start = $this->bookingStart($booking);
            if ($start === null) {
                continue;
            }

            $service = isset($booking['service_id']) ? $this->getServiceById((string)$booking['service_id']) : null;
            $end = !empty($booking['end_time'])
                ? DateTime::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $booking['end_time'], $this->timezone)
                : false;
            if (!$end) {
                // Older records have no end_time: derive it from the booked service's duration
                $duration = $service ? (ResourceProvider::serviceDuration($service, $services) ?? 1) : 1;
                $end = (clone $start)->modify("+{$duration} minutes");
            }
            if ($service) {
                $end->modify('+' . ResourceProvider::serviceCleanup($service, $services) . ' minutes');
            }

            $employeeId = $booking['employee_id'] ?? null;
            $intervals[] = [
                'start' => $start,
                'end' => $end,
                'employee_id' => is_string($employeeId) && $employeeId !== '' ? $employeeId : null,
                'exclusive' => $service !== null && ResourceProvider::isServiceExclusive($service, $services),
            ];
        }
        return $intervals;
    }

    /**
     * Runs $callback while holding an exclusive booking lock, so check-then-create cannot race.
     */
    public function withBookingLock(callable $callback) {
        if (!is_dir($this->bookingDir)) {
            @mkdir($this->bookingDir, 0775, true);
        }
        $fp = @fopen($this->bookingDir . '/.booking.lock', 'c');
        if (!$fp || !flock($fp, LOCK_EX)) {
            throw new RuntimeException('Could not acquire booking lock.');
        }
        try {
            return $callback();
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    public function createPendingRequest(array $bookingData): ?string {
        $bookingId = uniqid('book_');
        $bookingData['id'] = $bookingId;
        $bookingData['created_at_iso'] = (new DateTime('now', $this->timezone))->format(DATE_ATOM);

        return JsonStore::write($this->bookingPath($bookingId), $bookingData) ? $bookingId : null;
    }
}
