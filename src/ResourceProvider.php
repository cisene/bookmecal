<?php
/**
 * ResourceProvider.php
 * Transparent abstraction layer for data resources with stderr debugging logs.
 */

class ResourceProvider {
    
    private static function readJsonFile($filePath) {
        if (!file_exists($filePath)) {
            error_log("[ResourceProvider] ERROR: Resource file not found at path: " . $filePath);
            return null;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            error_log("[ResourceProvider] ERROR: Failed to read resource file contents at path: " . $filePath);
            return null;
        }
        
        // Strip UTF-8 BOM transparently if present
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("[ResourceProvider] ERROR: JSON decode failed for file " . $filePath . " - Error: " . json_last_error_msg());
            return null;
        }

        return $data;
    }

    public static function getSettings() {
        $filePath = __DIR__ . '/data/settings.json';
        $settings = self::readJsonFile($filePath);
        
        // Default settings if settings.json does not exist yet
        if (!is_array($settings)) {
            return [
                'show_past_slots' => true
            ];
        }
        
        return $settings;
    }

    public static function getHolidays($countryCode) {
        $countryCode = strtolower(trim($countryCode));
        $filePath = __DIR__ . "/holidays/{$countryCode}.json";
        $data = self::readJsonFile($filePath);
        
        if ($data === null && !file_exists($filePath)) {
            return [];
        }
        
        return $data['holidays'] ?? [];
    }

    public static function getLocale($countryCode) {
        $countryCode = strtolower(trim($countryCode));
        $filePath = __DIR__ . "/locale/{$countryCode}.json";
        return self::readJsonFile($filePath);
    }

    public static function getServices() {
        $filePath = __DIR__ . '/data/services.json';
        $services = self::readJsonFile($filePath);
        
        if (!is_array($services)) {
            error_log("[ResourceProvider] WARNING: Services catalog at " . $filePath . " is empty or not a valid array.");
            return [];
        }
        
        return $services;
    }

    public static function getServiceById($serviceId) {
        $services = self::getServices();
        foreach ($services as $service) {
            $currentId = $service['id'] ?? ($service['service_id'] ?? null);
            if ($currentId !== null && (string)$currentId === (string)$serviceId) {
                return $service;
            }
        }
        
        error_log("[ResourceProvider] WARNING: Service ID '" . $serviceId . "' was requested but not found in catalog.");
        return null;
    }
}