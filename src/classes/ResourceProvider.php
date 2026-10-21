<?php
/**
 * src/classes/ResourceProvider.php
 * Read access to the JSON data resources. All data paths are resolved through dataPath(),
 * so the storage location can later be switched per customer in one place.
 */

require_once __DIR__ . '/JsonStore.php';

class ResourceProvider {
    private const DATA_DIR = __DIR__ . '/../data';
    private const HOLIDAY_DIR = __DIR__ . '/../holidays';

    /**
     * Absolute path of a file or folder inside the data directory.
     */
    public static function dataPath(string $relative = ''): string {
        return rtrim(self::DATA_DIR . '/' . ltrim($relative, '/'), '/');
    }

    public static function initTimezone(): ?string {
        $tz = self::getSettings()['timezone'] ?? null;
        if (is_string($tz) && in_array($tz, timezone_identifiers_list(), true)) {
            date_default_timezone_set($tz);
            return $tz;
        }
        return null;
    }

    public static function getTimezone(): DateTimeZone {
        $tz = self::getSettings()['timezone'] ?? null;
        return new DateTimeZone(is_string($tz) && in_array($tz, timezone_identifiers_list(), true) ? $tz : 'Europe/Stockholm');
    }

    public static function getSettings(): ?array {
        return JsonStore::read(self::dataPath('settings.json'));
    }

    public static function getConfig(): ?array {
        return JsonStore::read(self::dataPath('config.json'));
    }

    public static function getServices(): ?array {
        return JsonStore::read(self::dataPath('services.json'));
    }

    public static function getOpenHours(): ?array {
        return JsonStore::read(self::dataPath('openhours.json'));
    }

    /**
     * Normalizes a country code; returns null unless it is exactly two letters (blocks path traversal).
     */
    public static function sanitizeCountryCode($countryCode): ?string {
        if (!is_string($countryCode)) {
            return null;
        }
        $countryCode = strtolower(trim($countryCode));
        return preg_match('/^[a-z]{2}$/', $countryCode) ? $countryCode : null;
    }

    public static function holidayPath(string $countryCode): ?string {
        $countryCode = self::sanitizeCountryCode($countryCode);
        return $countryCode === null ? null : self::HOLIDAY_DIR . "/{$countryCode}.json";
    }

    public static function getHolidays($countryCode): ?array {
        $path = is_string($countryCode) ? self::holidayPath($countryCode) : null;
        if ($path === null) {
            return null;
        }
        $holidays = JsonStore::read($path)['holidays'] ?? null;
        return is_array($holidays) ? $holidays : null;
    }

    /**
     * Finds a service by its storage key, SKU or id field.
     */
    public static function getServiceById($serviceId): ?array {
        $services = self::getServices();
        if (!$services) {
            return null;
        }
        $serviceId = trim((string)$serviceId);
        if ($serviceId === '') {
            return null;
        }

        if (isset($services[$serviceId]) && is_array($services[$serviceId])) {
            return $services[$serviceId];
        }

        foreach ($services as $service) {
            if (!is_array($service)) {
                continue;
            }
            if ((isset($service['sku']) && (string)$service['sku'] === $serviceId) ||
                (isset($service['id']) && (string)$service['id'] === $serviceId)) {
                return $service;
            }
        }

        return null;
    }

    /**
     * Treatment length; 'duration' is always in minutes.
     */
    public static function serviceDuration(array $service): ?int {
        $raw = $service['duration'] ?? null;
        return ($raw !== null && (int)$raw > 0) ? (int)$raw : null;
    }

    /**
     * Buffer after a treatment in minutes.
     */
    public static function serviceCleanup(array $service): int {
        return max(0, (int)($service['cleanup'] ?? 0));
    }

    public static function isServiceActive(array $service): bool {
        return !(isset($service['is_active']) && $service['is_active'] === false);
    }

    /**
     * Hours after which an unanswered booking request expires (settings.json pending_expiry_hours,
     * default 48). 0 means requests never expire.
     */
    public static function pendingExpiryHours(): int {
        return max(0, (int)(self::getSettings()['pending_expiry_hours'] ?? 48));
    }

    /**
     * Days after the appointment date after which a booking is deleted (settings.json
     * booking_retention_days, default 365). 0 means bookings are kept until deleted by hand.
     */
    public static function bookingRetentionDays(): int {
        return max(0, (int)(self::getSettings()['booking_retention_days'] ?? 365));
    }
}
