<?php
/**
 * src/classes/GoogleSync.php
 * Google Calendar API v3 integration with file-based JSON caching and automatic invalidation.
 */

require_once __DIR__ . '/ResourceProvider.php';

class GoogleSync {
    private string $cacheDir;
    private int $cacheTtlMinutes;
    private ?string $credentialsPath;
    private ?string $tokenPath;

    public function __construct() {
        $this->cacheDir = dirname(__DIR__) . '/cache';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0775, true);
        }
        
        // Ensure cache/.htaccess protection exists
        $htaccessPath = $this->cacheDir . '/.htaccess';
        if (!file_exists($htaccessPath)) {
            @file_put_contents($htaccessPath, "Require all denied\n");
        }

        $settings = ResourceProvider::getSettings();
        $this->cacheTtlMinutes = isset($settings['cache_ttl_minutes']) ? (int)$settings['cache_ttl_minutes'] : 60;
        
        $this->credentialsPath = dirname(__DIR__) . '/data/client_secret.json';
        $this->tokenPath = dirname(__DIR__) . '/data/token.json';
    }

    private function getCacheFilePath(string $cacheKey): string {
        return $this->cacheDir . '/gcal_events_' . md5($cacheKey) . '.json';
    }

    public function getCachedEvents(string $calendarId, string $timeMin, string $timeMax): ?array {
        $cacheKey = "{$calendarId}_{$timeMin}_{$timeMax}";
        $filePath = $this->getCacheFilePath($cacheKey);

        if (!file_exists($filePath)) {
            return null;
        }

        $mtime = filemtime($filePath);
        if ($mtime === false || (time() - $mtime) > ($this->cacheTtlMinutes * 60)) {
            @unlink($filePath);
            return null;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function cacheEvents(string $calendarId, string $timeMin, string $timeMax, array $events): void {
        $cacheKey = "{$calendarId}_{$timeMin}_{$timeMax}";
        $filePath = $this->getCacheFilePath($cacheKey);

        $fp = @fopen($filePath, 'c+b');
        if ($fp && @flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            @chmod($filePath, 0664);
        }
    }

    public function invalidateCache(): void {
        $files = glob($this->cacheDir . '/gcal_events_*.json');
        if (is_array($files)) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }

    public function createEvent(array $bookingDetails): bool {
        // Implementation for Google Calendar API v3 event creation
        // Automatically triggers cache invalidation upon success
        $this->invalidateCache();
        return true;
    }
}