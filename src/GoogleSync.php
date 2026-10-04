<?php

class GoogleSync {
    private array $config;

    public function __construct(array $config) {
        $this->config = $config;
    }

    /**
     * Hämtar events från Google Calendar med filbaserad cachning.
     */
    public function getEvents(string $timeMin, string $timeMax, bool $forceRefresh = false): array {
        $cacheConfig = $config['cache'] ?? [];
        $ttlSeconds = ($this->config['cache_ttl_minutes'] ?? 60) * 60;
        $cacheDir = __DIR__ . '/../cache';
        $cacheFile = $cacheDir . '/gcal_events_' . md5($timeMin . '_' . $timeMax) . '.json';

        // 1. Skapa cache-katalog och .htaccess om det saknas
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $htaccessFile = $cacheDir . '/.htaccess';
        if (!file_exists($htaccessFile)) {
            file_put_contents($htaccessFile, "Require all denied
");
        }

        // 2. Kontrollera om giltig cache finns
        if (!$forceRefresh && file_exists($cacheFile)) {
            $fileAge = time() - filemtime($cacheFile);
            if ($fileAge < $ttlSeconds) {
                $cachedData = json_decode(file_get_contents($cacheFile), true);
                if (is_array($cachedData)) {
                    return $cachedData;
                }
            }
        }

        // 3. Om cachen saknas, är för gammal eller har invaliderats: hämta från Google API
        $tokenFile = $this->config['token_file'] ?? __DIR__ . '/token.json';
        if (!file_exists($tokenFile)) {
            throw new RuntimeException("Google Calendar OAuth-token saknas på sökvägen: {$tokenFile}");
        }

        $tokenData = json_decode(file_get_contents($tokenFile), true);
        $accessToken = $tokenData['access_token'] ?? null;

        if (!$accessToken) {
            throw new RuntimeException("Giltig access token kunde inte hittas i token-filen.");
        }

        $calendarId = urlencode($this->config['calendar_id'] ?? 'primary');
        $url = sprintf(
            "https://www.googleapis.com/calendar/v3/calendars/%s/events?timeMin=%s&timeMax=%s&singleEvents=true",
            $calendarId,
            urlencode($timeMin),
            urlencode($timeMax)
        );

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new RuntimeException("Misslyckades att hämta event från Google Calendar (HTTP {$httpCode}): {$response}");
        }

        $events = json_decode($response, true)['items'] ?? [];

        // 4. Spara till cachen
        file_put_contents($cacheFile, json_encode($events, JSON_PRETTY_PRINT));

        return $events;
    }

    /**
     * Rensar/invaliderar alla sparade cache-filer i cache-katalogen.
     */
    public function invalidateCache(): void {
        $cacheDir = __DIR__ . '/../cache';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/gcal_events_*.json');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Skapar ett event i Google Calendar baserat på bokningsdata och invaliderar cachen.
     */
    public function createEvent(array $bookingData): string {
        $tokenFile = $this->config['token_file'] ?? __DIR__ . '/token.json';
        
        if (!file_exists($tokenFile)) {
            throw new RuntimeException("Google Calendar OAuth-token saknas på sökvägen: {$tokenFile}");
        }

        $tokenData = json_decode(file_get_contents($tokenFile), true);
        $accessToken = $tokenData['access_token'] ?? null;

        if (!$accessToken) {
            throw new RuntimeException("Giltig access token kunde inte hittas i token-filen.");
        }

        $calendarId = urlencode($this->config['calendar_id'] ?? 'primary');
        $url = "https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events";

        $startTime = new DateTime($bookingData['start_datetime']);
        $duration = (int)($bookingData['duration_minutes'] ?? 60);
        $endTime = clone $startTime;
        $endTime->modify("+{$duration} minutes");

        $eventPayload = [
            'summary'     => $bookingData['service_name'] ?? 'Bokning',
            'description' => sprintf(
                "Kund: %s
E-post: %s
Telefon: %s",
                $bookingData['client_name'] ?? 'Okänd',
                $bookingData['client_email'] ?? 'Ej angiven',
                $bookingData['client_phone'] ?? 'Ej angiven'
            ),
            'start' => [
                'dateTime' => $startTime->format(DateTime::RFC3339),
                'timeZone' => $bookingData['timezone'] ?? 'Europe/Stockholm',
            ],
            'end' => [
                'dateTime' => $endTime->format(DateTime::RFC3339),
                'timeZone' => $bookingData['timezone'] ?? 'Europe/Stockholm',
            ],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($eventPayload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 && $httpCode !== 201) {
            throw new RuntimeException("Misslyckades att skapa event i Google Calendar (HTTP {$httpCode}): {$response}");
        }

        // Töm cachen automatiskt när en ny bokning genomförs
        $this->invalidateCache();

        $responseData = json_decode($response, true);
        return $responseData['id'] ?? '';
    }
}
