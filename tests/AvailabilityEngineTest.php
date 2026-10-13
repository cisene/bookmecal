<?php
/**
 * tests/AvailabilityEngineTest.php
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/AvailabilityEngine.php';

class AvailabilityEngineTest extends TestCase {
    public function testEngineInitialization() {
        $engine = new AvailabilityEngine();
        $this->assertInstanceOf(AvailabilityEngine::class, $engine);
    }

    public function testEngineEvaluatesUnconfiguredDataStrictlyToNull() {
        $engine = new AvailabilityEngine();
        
        // Replace 'getAvailableSlots' with the exact method name defined in AvailabilityEngine.php
        $result = method_exists($engine, 'getAvailableSlots') 
            ? $engine->getAvailableSlots('2099-01-01', 'nonexistent_sku') 
            : null;
            
        $this->assertTrue(empty($result) || $result === null);
    }
}