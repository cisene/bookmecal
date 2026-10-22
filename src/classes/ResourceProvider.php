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
     * Treatments of a set (services.json keys, in the order they are done), or [] for a single
     * treatment. A set is done back to back by one employee.
     */
    public static function serviceItems(array $service): array {
        $items = $service['items'] ?? [];
        return is_array($items) ? array_values(array_filter($items, fn ($k) => is_string($k) && $k !== '')) : [];
    }

    /**
     * The set's treatments as records, or null if one is missing, is itself a set or has no duration.
     * $services: all services keyed by key (default services.json).
     */
    private static function setParts(array $service, ?array $services): ?array {
        $services ??= self::getServices() ?? [];
        $parts = [];
        foreach (self::serviceItems($service) as $key) {
            $part = $services[$key] ?? null;
            if (!is_array($part) || self::serviceItems($part) !== [] || self::serviceDuration($part) === null) {
                return null;
            }
            $parts[] = $part;
        }
        return $parts;
    }

    /**
     * Treatment length in minutes ('duration' is always in minutes). For a set: the sum of its
     * treatments, or null when the set is broken (see setParts()).
     */
    public static function serviceDuration(array $service, ?array $services = null): ?int {
        if (self::serviceItems($service) !== []) {
            $parts = self::setParts($service, $services);
            return $parts ? array_sum(array_map(fn ($p) => self::serviceDuration($p), $parts)) : null;
        }
        $raw = $service['duration'] ?? null;
        return ($raw !== null && (int)$raw > 0) ? (int)$raw : null;
    }

    /**
     * Buffer after a treatment in minutes. For a set: the last treatment's buffer, as the others
     * run back to back.
     */
    public static function serviceCleanup(array $service, ?array $services = null): int {
        if (self::serviceItems($service) !== []) {
            $parts = self::setParts($service, $services);
            return $parts ? self::serviceCleanup(end($parts)) : 0;
        }
        return max(0, (int)($service['cleanup'] ?? 0));
    }

    /**
     * Whether nothing else may run at the same time as this treatment (its own 'exclusive' flag,
     * or for a set, any of its treatments).
     */
    public static function isServiceExclusive(array $service, ?array $services = null): bool {
        if (($service['exclusive'] ?? false) === true) {
            return true;
        }
        foreach (self::setParts($service, $services) ?? [] as $part) {
            if (($part['exclusive'] ?? false) === true) {
                return true;
            }
        }
        return false;
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

    /**
     * Customers can cancel online until this many hours before the appointment (settings.json
     * cancel_cutoff_hours, default 24). 0 allows cancelling until the start.
     */
    public static function cancelCutoffHours(): int {
        return max(0, (int)(self::getSettings()['cancel_cutoff_hours'] ?? 24));
    }
}
