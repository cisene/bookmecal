<?php
/**
 * GoogleSync.php
 * Handles Google Calendar API v3 integration with local JSON file caching.
 */

class GoogleSync {
    private $cacheTtlMinutes;
    private $cacheDir;

    public function __construct($cacheTtlMinutes = 60) {
        $this->cacheTtlMinutes = $cacheTtlMinutes;
        $this->cacheDir = dirname(__DIR__) . '/cache/';
        
        if (!file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
        
        // Ensure .htaccess protects cache directory if using Apache
        $htaccess = $this->cacheDir . '.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Require all denied\n");
        }
    }

    /**
     * Fetch events for a specific date, utilizing cache if valid.
     */
    public function getCachedEventsForDate($date) {
        $cacheFile = $this->cacheDir . "gcal_events_{$date}.json";

        if (file_exists($cacheFile)) {
            $fileAgeMinutes = (time() - filemtime($cacheFile)) / 60;
            if ($fileAgeMinutes < $this->cacheTtlMinutes) {
                $data = json_decode(file_get_contents($cacheFile), true);
                if (is_array($data)) {
                    return $data;
                }
            }
        }

        // If cache is missing or expired, fetch live from Google Calendar
        $events = $this->fetchEventsFromGoogle($date);
        
        // Save to cache
        file_put_contents($cacheFile, json_encode($events));

        return $events;
    }

    private function fetchEventsFromGoogle($date) {
        // Placeholder for Google API client call (v3)
        // In production, this uses Google_Client and Google_Service_Calendar
        // and returns an array of [['start' => 'HH:MM', 'end' => 'HH:MM'], ...]
        
        // For demonstration, returning empty array (no external bookings yet)
        return [];
    }

    public function invalidateCache($date) {
        $cacheFile = $this->cacheDir . "gcal_events_{$date}.json";
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }
}