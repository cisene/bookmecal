<?php
/**
 * src/classes/BookingRepository.php
 * Data Access Layer using flock() concurrency control, IANA timezones, 
 * and strict null fallbacks for unconfigured records.
 */

require_once __DIR__ . '/ResourceProvider.php';

class BookingRepository {
    private string $dataDir;
    private ?DateTimeZone $timezone;

    public function __construct() {
        $this->dataDir = dirname(__DIR__) . '/data';
        if (!is_dir($this->dataDir)) {
            @mkdir($this->dataDir, 0775, true);
        }
        
        $settings = ResourceProvider::getSettings();
        $tzString = $settings['timezone'] ?? 'Europe/Stockholm';
        $this->timezone = new DateTimeZone($tzString);
    }

    private function readJson(string $filePath): ?array {
        if (!file_exists($filePath)) {
            return null;
        }
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }
        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function writeJson(string $filePath, array $data): bool {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        if (!file_exists($filePath)) {
            @file_put_contents($filePath, '{}');
            @chmod($filePath, 0664);
        }

        if (!is_writable($filePath)) {
            return false;
        }

        $fp = @fopen($filePath, 'c+b');
        if (!$fp || !@flock($fp, LOCK_EX)) {
            return false;
        }

        ftruncate($fp, 0);
        rewind($fp);
        $written = fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return $written !== false;
    }

    public function getServices(): ?array {
        return $this->readJson($this->dataDir . '/services.json');
    }

    public function getServiceById(string $serviceId) {
        return ResourceProvider::getServiceById($serviceId);
    }

    public function isHoliday(string $dateStr, string $countryCode = 'se'): bool {
        $holidays = ResourceProvider::getHolidays($countryCode);
        if (!$holidays) {
            return false;
        }
        foreach ($holidays as $h) {
            if (isset($h['date']) && $h['date'] === $dateStr) {
                return true;
            }
        }
        return false;
    }

    public function getOperatingHours(string $dayOfWeek): ?array {
        $openHoursFile = $this->dataDir . '/openhours.json';
        $hours = $this->readJson($openHoursFile);
        
        $dayOfWeek = strtolower($dayOfWeek);
        if (isset($hours[$dayOfWeek]) && is_array($hours[$dayOfWeek])) {
            return $hours[$dayOfWeek];
        }
        return null;
    }

    /**
     * Statuses that keep a slot occupied. Rejected/cancelled bookings free their slot again.
     */
    private const BLOCKING_STATUSES = ['pending', 'approved', 'confirmed'];

    /**
     * Returns the occupied intervals for a date as [['start' => DateTime, 'end' => DateTime], ...].
     */
    public function getBlockingIntervals(string $dateStr): array {
        $files = glob($this->dataDir . '/pending/booking_*.json');
        if (!is_array($files)) {
            return [];
        }

        $intervals = [];
        foreach ($files as $file) {
            $booking = $this->readJson($file);
            if ($booking === null || ($booking['date'] ?? null) !== $dateStr) {
                continue;
            }

            $status = $booking['status'] ?? 'pending';
            if (!in_array($status, self::BLOCKING_STATUSES, true)) {
                continue;
            }

            $start = DateTime::createFromFormat('Y-m-d H:i', $dateStr . ' ' . ($booking['time'] ?? ''), $this->timezone);
            if (!$start) {
                continue;
            }

            $end = null;
            if (!empty($booking['end_time'])) {
                $end = DateTime::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $booking['end_time'], $this->timezone) ?: null;
            }
            if ($end === null) {
                // Older records have no end_time: derive it from the booked service's duration
                $service = isset($booking['service_id']) ? ResourceProvider::getServiceById($booking['service_id']) : null;
                $duration = (int)($service['duration_minutes'] ?? $service['duration'] ?? 1);
                $end = (clone $start)->modify('+' . max(1, $duration) . ' minutes');
            }

            $intervals[] = ['start' => $start, 'end' => $end];
        }

        return $intervals;
    }

    /**
     * Runs $callback while holding an exclusive booking lock, so check-then-create cannot race.
     */
    public function withBookingLock(callable $callback) {
        $pendingDir = $this->dataDir . '/pending';
        if (!is_dir($pendingDir)) {
            @mkdir($pendingDir, 0775, true);
        }

        $fp = @fopen($pendingDir . '/.booking.lock', 'c');
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
        $pendingDir = $this->dataDir . '/pending';
        if (!is_dir($pendingDir)) {
            @mkdir($pendingDir, 0775, true);
        }

        $bookingId = uniqid('book_');
        $bookingData['id'] = $bookingId;
        
        $now = new DateTime('now', $this->timezone);
        $bookingData['created_at_iso'] = $now->format('Y-m-d\TH:i:sP');

        $filePath = $pendingDir . '/booking_' . $bookingId . '.json';
        $success = $this->writeJson($filePath, $bookingData);
        
        return $success ? $bookingId : null;
    }
}