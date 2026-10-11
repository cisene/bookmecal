<?php
/**
 * AvailabilityEngine.php
 * Core engine reading venue hours from data/openhours.json and factoring in treatment duration and cleanup buffers.
 */
require_once __DIR__ . '/ResourceProvider.php';

class AvailabilityEngine {
    private $countryCode;

    public function __construct($countryCode = 'se') {
        $this->countryCode = strtolower($countryCode);
    }
    
    public function getAvailableSlots($date, $serviceId) {
        $service = ResourceProvider::getServiceById($serviceId);
        if (!$service) {
            return [];
        }

        $duration = (int)($service['duration'] ?? 45);
        $cleanup = (int)($service['cleanup'] ?? 15);
        $totalBlock = $duration + $cleanup;

        if ($this->isHoliday($date)) {
            return []; 
        }

        $workingHours = $this->getWorkingHoursForDate($date);
        if (!$workingHours || empty($workingHours['is_open'])) {
            return []; 
        }

        $candidateSlots = $this->generateTimeSlots(
            $workingHours['open_time'], 
            $workingHours['close_time'], 
            $duration,
            $totalBlock
        );

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

    private function getWorkingHoursForDate($date) {
        $dayOfWeek = strtolower(date('l', strtotime($date)));
        $openHoursFile = __DIR__ . '/data/openhours.json';
        
        if (file_exists($openHoursFile)) {
            $data = json_decode(file_get_contents($openHoursFile), true);
            if (isset($data[$dayOfWeek])) {
                return $data[$dayOfWeek];
            }
        }

        return [
            'is_open' => true,
            'open_time' => '08:00',
            'close_time' => '17:00'
        ];
    }

    private function generateTimeSlots($openTime, $closeTime, $duration, $totalBlock) {
        $slots = [];
        $baseDate = date('Y-m-d');
        $current = strtotime("$baseDate $openTime");
        $closing = strtotime("$baseDate $closeTime");

        while (($current + ($totalBlock * 60)) <= $closing) {
            $startStr = date('H:i', $current);
            $endStr = date('H:i', $current + ($duration * 60));
            
            $slots[] = [
                'start' => $startStr,
                'end' => $endStr
            ];

            $current += ($totalBlock * 60);
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