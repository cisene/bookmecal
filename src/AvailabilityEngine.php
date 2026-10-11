<?php
/**
 * AvailabilityEngine.php
 * Implements the 3-tier availability filter with externalized regional holiday loading.
 */

class AvailabilityEngine {
    private $countryCode;

    public function __construct($countryCode = 'se') {
        $this->countryCode = strtolower($countryCode);
    }
    
    /**
     * Get available booking slots for a specific date and service duration.
     * 
     * @param string $date YYYY-MM-DD
     * @param int $durationMinutes Duration of the service in minutes
     * @return array List of available start times (e.g., ['09:00', '09:30', ...])
     */
    public function getAvailableSlots($date, $durationMinutes) {
        // Step 1: Check Holiday Matrix (Layer 2)
        if ($this->isHoliday($date)) {
            return []; // Clinic is closed on public holidays / red days
        }

        // Step 2: Load Clinic Opening Hours for this weekday (Layer 3)
        $workingHours = $this->getWorkingHoursForDate($date);
        if (!$workingHours || empty($workingHours['is_open'])) {
            return []; // Closed on this day of the week
        }

        // Step 3: Generate candidate slots based on service duration & interval
        $candidateSlots = $this->generateTimeSlots(
            $workingHours['open_time'], 
            $workingHours['close_time'], 
            $durationMinutes
        );

        // Step 4: Fetch Calendar Truth / Booked Blocks (Layer 1)
        $bookedIntervals = $this->getBookedIntervals($date);

        // Step 5: Filter out candidate slots that collide with booked intervals
        $availableSlots = array_values(array_filter($candidateSlots, function($slot) use ($durationMinutes, $bookedIntervals) {
            $slotStart = strtotime($slot['start']);
            $slotEnd = $slotStart + ($durationMinutes * 60);

            foreach ($bookedIntervals as $booked) {
                $bookStart = strtotime($booked['start']);
                $bookEnd = strtotime($booked['end']);

                if (max($slotStart, $bookStart) < min($slotEnd, $bookEnd)) {
                    return false; // Collision detected
                }
            }
            return true;
        }));

        return array_column($availableSlots, 'start');
    }

    private function isHoliday($date) {
        $holidayFile = dirname(__DIR__) . "/holidays/{$this->countryCode}.json";
        if (file_exists($holidayFile)) {
            $data = json_decode(file_get_contents($holidayFile), true);
            if (isset($data['holidays']) && is_array($data['holidays'])) {
                foreach ($data['holidays'] as $holiday) {
                    if (isset($holiday['date']) && $holiday['date'] === $date) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function getWorkingHoursForDate($date) {
        $dayOfWeek = strtolower(date('l', strtotime($date)));
        
        $availabilityFile = dirname(__DIR__) . '/data/availability.json';
        if (file_exists($availabilityFile)) {
            $data = json_decode(file_get_contents($availabilityFile), true);
            if (isset($data[$dayOfWeek])) {
                return $data[$dayOfWeek];
            }
        }

        return [
            'is_open' => true,
            'open_time' => '09:00',
            'close_time' => '17:00'
        ];
    }

    private function generateTimeSlots($openTime, $closeTime, $durationMinutes, $intervalMinutes = 15) {
        $slots = [];
        $current = strtotime($openTime);
        $closing = strtotime($closeTime);

        while (($current + ($durationMinutes * 60)) <= $closing) {
            $startStr = date('H:i', $current);
            $endStr = date('H:i', $current + ($durationMinutes * 60));
            
            $slots[] = [
                'start' => $startStr,
                'end' => $endStr
            ];

            $current += ($intervalMinutes * 60);
        }

        return $slots;
    }

    private function getBookedIntervals($date) {
        $cacheFile = dirname(__DIR__) . "/cache/gcal_events_{$date}.json";
        if (file_exists($cacheFile)) {
            $events = json_decode(file_get_contents($cacheFile), true);
            if (is_array($events)) {
                return $events;
            }
        }
        return [];
    }
}