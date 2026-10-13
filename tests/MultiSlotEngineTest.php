<?php
/**
 * tests/MultiSlotEngineTest.php
 * Unit tests for MultiSlotBookingEngine.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/MultiSlotEngine.php';

class MultiSlotEngineTest extends TestCase {
    public function testMultiSlotEngineInstantiation() {
        $this->assertTrue(class_exists('MultiSlotBookingEngine'), 'MultiSlotBookingEngine class must exist.');

        // Create a driver-free PDO stub using an anonymous class
        $pdoStub = new class extends PDO {
            public function __construct() {}
        };

        $engine = new MultiSlotBookingEngine($pdoStub);
        $this->assertInstanceOf('MultiSlotBookingEngine', $engine);
    }
}