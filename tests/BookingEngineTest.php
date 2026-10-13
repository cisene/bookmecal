<?php
/**
 * tests/BookingEngineTest.php
 * Unit tests for BookingEngine.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/BookingEngine.php';
require_once __DIR__ . '/../src/classes/BookingRepository.php';

class BookingEngineTest extends TestCase {
    public function testBookingEngineInstantiation() {
        $repository = new BookingRepository();
        $engine = new BookingEngine($repository);
        $this->assertInstanceOf(BookingEngine::class, $engine);
    }
}