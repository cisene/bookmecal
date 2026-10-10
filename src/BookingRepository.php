<?php
// BookingRepository.php - JSON-baserad lagring för servreanvisningar utan databas

class BookingRepository {
    private string $dataDir;
    private string $bookingsFile;
    private string $holidaysFile;
    private string $operatingHoursFile;

    public function __construct(?string $dataDir = null) {
        $this->dataDir = $dataDir ?? __DIR__ . '/data';

        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }

        $this->bookingsFile       = $this->dataDir . '/bookings.json';
        $this->holidaysFile       = $this->dataDir . '/holidays.json';
        $this->operatingHoursFile = $this->dataDir . '/operating_hours.json';

        $this->initDefaultFiles();
    }

    private function initDefaultFiles(): void {
        if (!file_exists($this->bookingsFile)) {
            $this->writeFile($this->bookingsFile, []);
        }

        if (!file_exists($this->holidaysFile)) {
            $this->writeFile($this->holidaysFile, []);
        }

        if (!file_exists($this->operatingHoursFile)) {
            $defaultHours = [
                1 => ['start_time' => '08:00', 'end_time' => '17:00'], // Måndag
                2 => ['start_time' => '08:00', 'end_time' => '17:00'], // Tisdag
                3 => ['start_time' => '08:00', 'end_time' => '17:00'], // Onsdag
                4 => ['start_time' => '08:00', 'end_time' => '17:00'], // Torsdag
                5 => ['start_time' => '08:00', 'end_time' => '17:00'], // Fredag
            ];
            $this->writeFile($this->operatingHoursFile, $defaultHours);
        }
    }

    private function readFile(string $filePath): array {
        if (!file_exists($filePath)) {
            return [];
        }

        $fp = fopen($filePath, 'rb');
        if (!$fp) {
            return [];
        }

        flock($fp, LOCK_SH);
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return json_decode($content, true) ?: [];
    }

    private function writeFile(string $filePath, array $data): bool {
        $fp = fopen($filePath, 'c+b');
        if (!$fp) {
            return false;
        }

        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            return true;
        }

        fclose($fp);
        return false;
    }

    public function isHoliday(string $dateStr): bool {
        $holidays = $this->readFile($this->holidaysFile);
        return in_array($dateStr, $holidays, true);
    }

    public function getOperatingHours(int $dayOfWeek): ?array {
        $hours = $this->readFile($this->operatingHoursFile);
        return $hours[$dayOfWeek] ?? null;
    }

    public function isSlotOccupied(string $start, string $end): bool {
        $bookings = $this->readFile($this->bookingsFile);

        foreach ($bookings as $b) {
            if (in_array($b['status'], ['pending', 'approved'], true)) {
                if ($b['start_datetime'] < $end && $b['end_datetime'] > $start) {
                    return true;
                }
            }
        }

        return false;
    }

    public function createRequest(string $name, string $email, string $start, string $end): int {
        $bookings = $this->readFile($this->bookingsFile);

        $newId = count($bookings) > 0 ? (max(array_column($bookings, 'id')) + 1) : 1;

        $newRequest = [
            'id'             => $newId,
            'client_name'    => $name,
            'client_email'   => $email,
            'start_datetime' => $start,
            'end_datetime'   => $end,
            'status'         => 'pending',
            'created_at'     => date('Y-m-d H:i:s')
        ];

        $bookings[] = $newRequest;
        $this->writeFile($this->bookingsFile, $bookings);

        return $newId;
    }
}