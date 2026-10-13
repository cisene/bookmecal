<?php
/**
 * tests/BookingServiceTest.php
 * Unit tests for BookingService slot calculation and validation logic.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/BookingService.php';

class BookingServiceTest extends TestCase {
    private BookingService $service;

    protected function setUp(): void {
        $this->service = new BookingService();
    }

    public function testGetAvailableSlotsInvalidDateReturnsNull() {
        $slots = $this->service->getAvailableSlots('invalid-date', 'srv_123', 'se');
        $this->assertNull($slots);
    }

    public function testGetAvailableSlotsInvalidFormatReturnsNull() {
        $slots = $this->service->getAvailableSlots('26-10-2026', 'srv_123', 'se');
        $this->assertNull($slots);
    }

    public function testGetAvailableSlotsNonexistentServiceReturnsNull() {
        $slots = $this->service->getAvailableSlots('2026-10-15', 'nonexistent_service_id', 'se');
        $this->assertNull($slots);
    }
}