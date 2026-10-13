<?php
/**
 * AvailabilityEngine.php
 * Reads hourly slot map data directly from data/booking/availability.json.
 */
require_once __DIR__ . '/ResourceProvider.php';

class AvailabilityEngine {
    private $countryCode;

    public function __construct($countryCode = 'se') {
        $this->countryCode = strtolower($countryCode);
    }

    public function __destruct() {}
    
    public function getAvailableSlots($date, $serviceId = null) {
        $duration = 45;
        $cleanup = 15;

        if (!empty($serviceId)) {
            $service = ResourceProvider::getServiceById($serviceId);
            if ($service) {
                $duration = (int)($service['duration'] ?? 45);
                $cleanup = (int)($service['cleanup'] ?? 15);
            }
        }

        $totalBlock = $duration + $cleanup;

        if ($this->isHoliday($date)) {
            return []; 
        }

        $dayConfig = $this->getDayConfigForDate($date);
        if (!$dayConfig || empty($dayConfig['slots'])) {
            return []; 
        }

        $candidateSlots = [];
        $slotsMap = $dayConfig['slots'];

        foreach ($slotsMap as $timeStr => $isActive) {
            if ($isActive == 1) {
                $candidateSlots[] = [
                    'start' => $timeStr,
                    'end' => date('H:i', strtotime("$timeStr +{$duration} minutes"))
                ];
            }
        }

        $bookedIntervals = $this->getBookedIntervals($date);

        $availableSlots = array_values(array_filter($candidateSlots, function($slot) use ($totalBlock, $bookedIntervals) {
            $slotStart = strtotime($slot['start']);
            $slotEnd = $slotStart + ($totalBlock * 60);

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

    private function getDayConfigForDate($date) {
        $dayOfWeek = strtolower(date('l', strtotime($date)));
        $availabilityFile = __DIR__ . '/data/booking/availability.json';
        
        if (file_exists($availabilityFile)) {
            $data = json_decode(file_get_contents($availabilityFile), true);
            if (isset($data[$dayOfWeek])) {
                return $data[$dayOfWeek];
            }
        }

        return null;
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