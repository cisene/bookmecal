<?php
/**
 * tests/TimeSlotTest.php
 * Unit tests for TimeSlot value object.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/TimeSlot.php';

class TimeSlotTest extends TestCase {
    public function testTimeSlotInitialization() {
        $start = new DateTime('09:00');
        $end = new DateTime('09:30');
        
        $slot = new TimeSlot($start, $end);
        
        // Assert that the object is instantiated successfully
        $this->assertInstanceOf(TimeSlot::class, $slot);
    }
}