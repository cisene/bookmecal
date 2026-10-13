<?php
/**
 * ResourceProvider.php
 * Transparent abstraction layer for data resources with timezone bootstrap and SKU lookups.
 */

class ResourceProvider {
    
    public static function initTimezone() {
        $settings = self::getSettings();
        $tz = $settings['timezone'] ?? 'Europe/Stockholm';
        if (function_exists('date_default_timezone_set')) {
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

        return json_decode($content, true);
    }

    public static function getSettings() {
        $filePath = __DIR__ . '/data/settings.json';
        $settings = self::readJsonFile($filePath);
        return is_array($settings) ? $settings : [
            'default_country' => 'se',
            'timezone' => 'Europe/Stockholm',
            'supported_timezones' => [
                'Europe/Stockholm', 'Europe/Oslo', 'Europe/Helsinki',
                'Europe/Tallinn', 'Europe/Riga', 'Europe/Vilnius'
            ]
        ];
    }

    public static function getHolidays($countryCode) {
        $countryCode = strtolower(trim($countryCode));
        $filePath = __DIR__ . "/holidays/{$countryCode}.json";
        $data = self::readJsonFile($filePath);
        return $data['holidays'] ?? [];
    }

    public static function getLocale($languageCode) {
        $languageCode = strtolower(trim($languageCode));
        $filePath = __DIR__ . "/language/{$languageCode}.json";
        $locale = self::readJsonFile($filePath);
        return is_array($locale) ? $locale : [];
    }

    public static function getServices() {
        $filePath = __DIR__ . '/data/services.json';
        $services = self::readJsonFile($filePath);
        return is_array($services) ? $services : [];
    }

    public static function getServiceById($serviceId) {
        $services = self::getServices();
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

// Automatically enforce configured timezone on inclusion
ResourceProvider::initTimezone();