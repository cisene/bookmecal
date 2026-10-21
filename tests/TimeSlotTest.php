<?php
/**
 * tests/TimeSlotTest.php
 * Functional tests for TimeSlot value object.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/TimeSlot.php';

class TimeSlotTest extends TestCase {
    public function testGettersReturnCorrectDateTimeObjects() {
        $start = new DateTime('2026-10-14 09:00:00');
        $end = new DateTime('2026-10-14 10:00:00');
        
        $slot = new TimeSlot($start, $end);
        
        $this->assertEquals($start, $slot->getStart());
        $this->assertEquals($end, $slot->getEnd());
    }

    public function testToArrayOutputsExpectedStructure() {
        $start = new DateTime('2026-10-14 09:00:00');
        $end = new DateTime('2026-10-14 09:30:00');
        
        $slot = new TimeSlot($start, $end);
        $array = $slot->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('start', $array);
        $this->assertArrayHasKey('end', $array);
        $this->assertEquals('2026-10-14 09:00:00', $array['start']);
        $this->assertEquals('2026-10-14 09:30:00', $array['end']);
    }
}