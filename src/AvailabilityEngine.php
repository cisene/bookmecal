<?php
/**
 * AvailabilityEngine.php
 * Core availability calculation engine using 3-tier checks with correct slot interval spacing.
 */
require_once __DIR__ . '/ResourceProvider.php';

class AvailabilityEngine {
    private $countryCode;

    public function __construct($countryCode = 'se') {
        $this->countryCode = strtolower($countryCode);
    }
    
    public function getAvailableSlots($date, $durationMinutes) {
        // Step 1: Check Holiday Matrix via Abstraction Layer
        if ($this->isHoliday($date)) {
            return []; 
        }

        // Step 2: Load Clinic Opening Hours
        $workingHours = $this->getWorkingHoursForDate($date);
        if (!$workingHours || empty($workingHours['is_open'])) {
            return []; 
        }

        // Step 3: Generate candidate slots (stepping by duration or standard intervals)
        // Here we step by durationMinutes or a clean interval (e.g., matching duration or 30 min)
        // To avoid overlapping 15-minute micro-slots for longer treatments, we step by $durationMinutes 
        // or a sensible default step (like 30 mins). Let's step by durationMinutes to prevent overlapping bookings.
        $candidateSlots = $this->generateTimeSlots(
            $workingHours['open_time'], 
            $workingHours['close_time'], 
            $durationMinutes,
            $durationMinutes // Step forward by full service duration, or change to 30 if preferred
        );

        // Step 4: Fetch Calendar Truth / Booked Blocks
        $bookedIntervals = $this->getBookedIntervals($date);

        // Step 5: Filter collisions
        $availableSlots = array_values(array_filter($candidateSlots, function($slot) use ($durationMinutes, $bookedIntervals) {
            $slotStart = strtotime($slot['start']);
            $slotEnd = $slotStart + ($durationMinutes * 60);

            foreach ($bookedIntervals as $booked) {
                $bookStart = strtotime($booked['start']);
                $bookEnd = strtotime($booked['end']);

                if (max($slotStart, $bookStart) < min($slotEnd, $bookEnd)) {
                    return false; 
                }
            }
            return true;
        }));

        return array_column($availableSlots, 'start');
    }

    private function isHoliday($date) {
        $holidays = ResourceProvider::getHolidays($this->countryCode);
        foreach ($holidays as $holiday) {
            if (isset($holiday['date']) && $holiday['date'] === $date) {
                return true;
            }
        }
        return false;
    }

    private function getWorkingHoursForDate($date) {
        $dayOfWeek = strtolower(date('l', strtotime($date)));
        
        $availabilityFile = __DIR__ . '/data/availability.json';
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

    private function generateTimeSlots($openTime, $closeTime, $durationMinutes, $intervalMinutes = null) {
        // If interval is not explicitly provided, default to stepping by the service duration 
        // to prevent overlapping 15-minute slot crowding.
        if ($intervalMinutes === null) {
            $intervalMinutes = $durationMinutes;
        }

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
        $cacheFile = __DIR__ . "/cache/gcal_events_{$date}.json";
        if (file_exists($cacheFile)) {
            $events = json_decode(file_get_contents($cacheFile), true);
            if (is_array($events)) {
                return $events;
            }
        }
        return [];
    }
}