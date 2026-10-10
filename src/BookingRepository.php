<?php
// BookingRepository.php - Handles thread-safe file operations for pending requests and availability

class BookingRepository {
    private string $dataDir;
    private string $pendingDir;
    private string $availabilityFile;

    public function __construct(?string $dataDir = null) {
        $this->dataDir = $dataDir ?? __DIR__ . '/src/data';
        $this->pendingDir = $this->dataDir . '/pending';
        $this->availabilityFile = $this->dataDir . '/booking/availability.json';

        // Ensure directories exist
        if (!is_dir($this->pendingDir)) {
            mkdir($this->pendingDir, 0755, true);
        }
        $availDir = dirname($this->availabilityFile);
        if (!is_dir($availDir)) {
            mkdir($availDir, 0755, true);
        }
    }

    /**
     * Creates a new pending booking request as an individual JSON file in src/data/pending/
     */
    public function createPendingRequest(string $clientName, string $clientEmail, string $start, string $end): int {
        $bookingId = time(); // Unique ID based on timestamp
        $filename = $this->pendingDir . '/booking_' . $bookingId . '.json';

        $bookingData = [
            'id' => $bookingId,
            'client_name' => $clientName,
            'client_email' => $clientEmail,
            'start_datetime' => $start,
            'end_datetime' => $end,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $fp = fopen($filename, 'c+b');
        if ($fp) {
            if (flock($fp, LOCK_EX)) {
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode($bookingData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                fflush($fp);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }

        return $bookingId;
    }

    /**
     * Retrieves all pending booking requests from src/data/pending/
     */
    public function getPendingRequests(): array {
        $requests = [];
        $files = glob($this->pendingDir . '/booking_*.json');
        
        foreach ($files as $file) {
            $fp = fopen($file, 'rb');
            if ($fp) {
                if (flock($fp, LOCK_SH)) {
                    $content = stream_get_contents($fp);
                    flock($fp, LOCK_UN);
                    $data = json_decode($content, true);
                    if ($data) {
                        $requests[] = $data;
                    }
                }
                fclose($fp);
            }
        }

        usort($requests, fn($a, $b) => $b['id'] <=> $a['id']);
        return $requests;
    }

    /**
     * Loads available booking slots from src/data/booking/availability.json
     */
    public function getAvailability(): array {
        if (!file_exists($this->availabilityFile)) {
            return [];
        }

        $fp = fopen($this->availabilityFile, 'rb');
        if (!$fp) {
            return [];
        }

        $slots = [];
        if (flock($fp, LOCK_SH)) {
            $content = stream_get_contents($fp);
            flock($fp, LOCK_UN);
            $slots = json_decode($content, true) ?: [];
        }
        fclose($fp);

        return $slots;
    }

    /**
     * Saves available booking slots to src/data/booking/availability.json
     */
    public function saveAvailability(array $slots): bool {
        $fp = fopen($this->availabilityFile, 'c+b');
        if (!$fp) {
            return false;
        }

        $success = false;
        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($slots, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            fflush($fp);
            flock($fp, LOCK_UN);
            $success = true;
        }
        fclose($fp);

        return $success;
    }
}