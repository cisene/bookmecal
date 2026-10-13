<?php
/**
 * ResourceProvider.php
 * Transparent abstraction layer for data resources with robust SKU/ID lookups and URL-decoding.
 */

class ResourceProvider {
    
    private static function readJsonFile($filePath) {
        if (!file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }
        
        // Strip UTF-8 BOM transparently if present
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }

        return json_decode($content, true);
    }

    public static function getSettings() {
        $filePath = __DIR__ . '/data/settings.json';
        $settings = self::readJsonFile($filePath);
        return is_array($settings) ? $settings : ['show_past_slots' => true];
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
        
        // 1. Direct check if decoded serviceId matches an associative array key (SKU)
        if (isset($services[$serviceId])) {
            return $services[$serviceId];
        }

        // 2. Fallback scan matching keys, SKUs, or legacy internal IDs safely with url-decoding
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