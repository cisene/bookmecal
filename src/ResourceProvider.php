<?>
/**
 * ResourceProvider.php
 * Transparent abstraction layer matching the file/directory structure specified in the project architecture.
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
        // Matches language/{code}.json per project structure
        $filePath = __DIR__ . "/language/{$languageCode}.json";
        $locale = self::readJsonFile($filePath);
        
        return is_array($locale) ? $locale : [
            'select_service' => 'Select Service:',
            'timeline_title' => 'Available Time Slots:',
            'loading_services' => 'Loading services...',
            'loading_timeline' => 'Loading availability...',
            'closed' => 'Closed',
            'fully_booked' => 'Fully Booked',
            'free_slots' => 'available slots',
            'morning' => 'Morning',
            'afternoon' => 'Afternoon',
            'evening' => 'Evening',
            'customer_details' => 'Your Details',
            'name' => 'Name:',
            'email' => 'Email:',
            'phone' => 'Phone:',
            'confirm_booking' => 'Confirm Booking',
            'success_title' => 'Thank you for your booking!',
            'success_message' => 'A confirmation has been sent to your email.',
            'no_services_found' => 'No active services found.',
            'no_slots_found' => 'No available slots found for the coming week.',
            'booking_failed' => 'Booking failed: '
        ];
    }

    public static function getServices() {
        $filePath = __DIR__ . '/data/services.json';
        $services = self::readJsonFile($filePath);
        return is_array($services) ? $services : [];
    }

    public static function getServiceById($serviceId) {
        $services = self::getServices();
        foreach ($services as $service) {
            $currentId = $service['id'] ?? ($service['service_id'] ?? null);
            if ($currentId !== null && (string)$currentId === (string)$serviceId) {
                return $service;
            }
        }
        return null;
    }
}