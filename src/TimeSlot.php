<?php
class TimeSlot {
    public function __construct(
        private DateTime $start,
        private DateTime $end
    ) {}

    public function getStart(): DateTime {
        return $this->start;
    }

    public function getEnd(): DateTime {
        return $this->end;
    }

    public function toArray(): array {
        return [
            'start' => $this->start->format('Y-m-d H:i:s'),
            'end'   => $this->end->format('Y-m-d H:i:s')
        ];
    }
}