<?php
/**
 * BookingRepository.php
 * Data Access Layer (DAL) for managing flat-file JSON datasets 
 * (services, operating hours, holidays, and pending bookings) with thread-safe flock() locking.
 */

class BookingRepository {
    private string $dataDir;

    public function __construct() {
        $this->dataDir = __DIR__ . '/data';
        if (!is_dir($this->dataDir)) {
            @mkdir($this->dataDir, 0775, true);
        }
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
        $services = $this->getServices();
        $serviceId = trim(urldecode($serviceId));

        if (isset($services[$serviceId])) {
            return $services[$serviceId];
        }

        foreach ($services as $key => $service) {
            if (trim(urldecode((string)$key)) === $serviceId || (string)$key === $serviceId) {
                return $service;
            }
            if (isset($service['sku']) && trim(urldecode((string)$service['sku'])) === $serviceId) {
                return $service;
            }
            if (isset($service['id']) && (string)$service['id'] === $serviceId) {
                return $service;
            }
        }
        return null;
    }

    public function isHoliday(string $dateStr, string $countryCode = 'se'): bool {
        $holidayFile = __DIR__ . "/holidays/" . strtolower(trim($countryCode)) . ".json";
        $data = $this->readJson($holidayFile);
        $holidays = $data['holidays'] ?? [];

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

    public function isSlotOccupied(string $startDatetime, string $endDatetime): bool {
        $pendingDir = $this->dataDir . '/pending';
        if (!is_dir($pendingDir)) {
            return false;
        }

        $files = glob($pendingDir . '/booking_*.json');
        if (empty($files)) {
            return false;
        }

        $startTs = strtotime($startDatetime);
        $endTs = strtotime($endDatetime);

        foreach ($files as $file) {
            $booking = $this->readJson($file);
            if (empty($booking) || isset($booking['status']) && $booking['status'] === 'rejected') {
                continue;
            }

            $bStart = strtotime($booking['start_datetime'] ?? '');
            $bEnd = strtotime($booking['end_datetime'] ?? '');

            if ($bStart && $bEnd) {
                if (max($startTs, $bStart) < min($endTs, $bEnd)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function createPendingRequest(array $bookingData): string {
        $pendingDir = $this->dataDir . '/pending';
        if (!is_dir($pendingDir)) {
            @mkdir($pendingDir, 0775, true);
        }

        $bookingId = uniqid('book_');
        $bookingData['id'] = $bookingId;
        $filePath = $pendingDir . '/booking_' . $bookingId . '.json';

        $this->writeJson($filePath, $bookingData);
        return $bookingId;
    }
}