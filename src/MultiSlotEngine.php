<?php

class MultiSlotBookingEngine {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Calculates combined duration and total price for a multi-stage service
     */
    public function getServicePipelineSummary(int $serviceId): array {
        $stmt = $this->pdo->prepare("SELECT * FROM service_stages WHERE service_id = ? ORDER BY stage_order ASC");
        $stmt->execute([$serviceId]);
        $stages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalMinutes = 0;
        $totalCost = 0.00;

        foreach ($stages as $stage) {
            $totalMinutes += (int)$stage['duration_minutes'];
            $totalCost += (float)$stage['cost'];
        }

        return [
            'stages' => $stages,
            'total_duration_minutes' => $totalMinutes,
            'total_cost' => $totalCost
        ];
    }

    /**
     * Finds start times where the entire multi-stage sequence can fit without overlaps
     */
    public function getAvailableMultiSlotTimes(string $dateStr, int $serviceId): array {
        $pipeline = $this->getServicePipelineSummary($serviceId);
        $totalRequiredMinutes = $pipeline['total_duration_minutes'];

        if (empty($pipeline['stages'])) {
            return [];
        }

        // Fetch day limits
        $date = new DateTime($dateStr);
        $dayOfWeek = (int)$date->format('w');
        
        $stmt = $this->pdo->prepare("SELECT start_time, end_time FROM slot_matrix WHERE day_of_week = ? AND is_active = 1");
        $stmt->execute([$dayOfWeek]);
        $matrix = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$matrix) return [];

        $dayStart = new DateTime($dateStr . ' ' . $matrix['start_time']);
        $dayEnd   = new DateTime($dateStr . ' ' . $matrix['end_time']);

        // Check slots every 20 or 30 minute incremental step
        $stepInterval = new DateInterval("PT20M"); 
        $current = clone $dayStart;
        $validStartTimes = [];

        while ($current < $dayEnd) {
            $sequenceEnd = clone $current;
            $sequenceEnd->add(new DateInterval("PT{$totalRequiredMinutes}M"));

            if ($sequenceEnd > $dayEnd) break;

            // Check if the FULL required block is free of existing bookings
            if (!$this->isTimeBlockOccupied($current->format('Y-m-d H:i:s'), $sequenceEnd->format('Y-m-d H:i:s'))) {
                
                // Build stage breakdown with precise start/end and cost
                $stageTimeline = [];
                $stagePointer = clone $current;

                foreach ($pipeline['stages'] as $stage) {
                    $stageStart = clone $stagePointer;
                    $stageEnd = clone $stagePointer;
                    $stageEnd->add(new DateInterval("PT{$stage['duration_minutes']}M"));

                    $stageTimeline[] = [
                        'stage_name' => $stage['stage_name'],
                        'start'      => $stageStart->format('H:i'),
                        'end'        => $stageEnd->format('H:i'),
                        'cost'       => (float)$stage['cost'],
                        'is_prep'    => (bool)$stage['is_prep_stage']
                    ];

                    $stagePointer->add(new DateInterval("PT{$stage['duration_minutes']}M"));
                }

                $validStartTimes[] = [
                    'start_time' => $current->format('H:i'),
                    'end_time'   => $sequenceEnd->format('H:i'),
                    'total_cost' => $pipeline['total_cost'],
                    'timeline'   => $stageTimeline
                ];
            }

            $current->add($stepInterval);
        }

        return $validStartTimes;
    }

    private function isTimeBlockOccupied(string $start, string $end): bool {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM booking_requests 
            WHERE status IN ('pending', 'approved') 
            AND (start_datetime < ? AND end_datetime > ?)
        ");
        $stmt->execute([$end, $start]);
        return $stmt->fetchColumn() > 0;
    }
}