<?php
/**
 * src/classes/ResourceProvider.php
 * Transparent abstraction layer for data resources without hardcoded assumptions or fallback defaults.
 */

class ResourceProvider {
    
    public static function initTimezone() {
        $settings = self::getSettings();
        $tz = $settings['timezone'] ?? null;
        if ($tz && function_exists('date_default_timezone_set')) {
            @date_default_timezone_set($tz);
        }
        return $tz;
    }

    private static function readJsonFile($filePath) {
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

    public static function getSettings() {
        $filePath = dirname(__DIR__) . '/data/settings.json';
        return self::readJsonFile($filePath);
    }

    /**
     * Normalizes a country code; returns null unless it is exactly two letters (blocks path traversal).
     */
    public static function sanitizeCountryCode($countryCode) {
        if (!is_string($countryCode)) {
            return null;
        }
        $countryCode = strtolower(trim($countryCode));
        return preg_match('/^[a-z]{2}$/', $countryCode) ? $countryCode : null;
    }

    public static function getHolidays($countryCode) {
        $countryCode = self::sanitizeCountryCode($countryCode);
        if ($countryCode === null) {
            return null;
        }
        $filePath = dirname(__DIR__) . "/holidays/{$countryCode}.json";
        $data = self::readJsonFile($filePath);
        return $data['holidays'] ?? null;
    }

    public static function getServices() {
        $filePath = dirname(__DIR__) . '/data/services.json';
        return self::readJsonFile($filePath);
    }

    public static function getServiceById($serviceId) {
        $services = self::getServices();
        if (!$services) {
            return null;
        }
        $serviceId = trim(urldecode((string)$serviceId));
        
        if (isset($services[$serviceId])) {
            return $services[$serviceId];
        }

        foreach ($services as $key => $service) {
            $decodedKey = trim(urldecode((string)$key));
            if ($decodedKey === $serviceId || (string)$key === (string)$serviceId) {
                return $service;
            }
            
            $currentSku = isset($service['sku']) ? trim(urldecode((string)$service['sku'])) : null;
            $currentId = isset($service['id']) ? trim((string)$service['id']) : null;
            
            if (($currentSku !== null && $currentSku === $serviceId) ||
                ($currentId !== null && $currentId === $serviceId)) {
                return $service;
            }
        }
        
        return null;
    }
}

ResourceProvider::initTimezone();