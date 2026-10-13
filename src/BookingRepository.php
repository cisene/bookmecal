<?php
/**
 * BookingRepository.php
 * Data Access Layer with flock() concurrency control and timezone-bound ISO datetimes.
 */

require_once __DIR__ . '/ResourceProvider.php';

class BookingRepository {
    private string $dataDir;
    private DateTimeZone $timezone;

    public function __construct() {
        $this->dataDir = __DIR__ . '/data';
        if (!is_dir($this->dataDir)) {
            @mkdir($this->dataDir, 0775, true);
        }
        
        $settings = ResourceProvider::getSettings();
        $tzString = $settings['timezone'] ?? 'Europe/Stockholm';
        $this->timezone = new DateTimeZone($tzString);
    }

    private function readJson(string $filePath): array {
        if (!file_exists($filePath)) {
            return [];
        }
        $content = file_get_contents($filePath);
        if ($content === false) {
            return [];
        }
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }
        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function writeJson(string $filePath, array $data): bool {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
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

    public function getServices(): array {
        return $this->readJson($this->dataDir . '/services.json');
    }

    public function getServiceById(string $serviceId) {
        return ResourceProvider::getServiceById($serviceId);
    }

    public function isHoliday(string $dateStr, string $countryCode = 'se'): bool {
        $holidays = ResourceProvider::getHolidays($countryCode);
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
        if (isset($hours[$dayOfWeek]) && !empty($hours[$dayOfWeek]['is_open'])) {
            return $hours[$dayOfWeek];
        }
        return null;
    }

    public function createPendingRequest(array $bookingData): string {
        $pendingDir = $this->dataDir . '/pending';
        if (!is_dir($pendingDir)) {
            @mkdir($pendingDir, 0775, true);
        }

        $bookingId = uniqid('book_');
        $bookingData['id'] = $bookingId;
        
        // Attach timezone-aware ISO timestamp
        $now = new DateTime('now', $this->timezone);
        $bookingData['created_at_iso'] = $now->format('Y-m-d\TH:i:sP');

        $filePath = $pendingDir . '/booking_' . $bookingId . '.json';
        $this->writeJson($filePath, $bookingData);
        return $bookingId;
    }
}