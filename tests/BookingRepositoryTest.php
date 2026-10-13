<?php
/**
 * tests/BookingRepositoryTest.php
 * Unit tests for BookingRepository data access and file operations.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/BookingRepository.php';

class BookingRepositoryTest extends TestCase {
    private BookingRepository $repository;
    private string $testDataDir;

    protected function setUp(): void {
        $this->repository = new BookingRepository();
        $this->testDataDir = dirname(__DIR__) . '/src/data';
    }

    public function testGetServicesReturnsArrayOrNull() {
        $services = $this->repository->getServices();
        $this->assertTrue(is_array($services) || $services === null);
    }

    public function testHolidayDetection() {
        // Test a sample date against holiday logic
        $isHoliday = $this->repository->isHoliday('2026-12-25', 'se');
        $this->assertIsBool($isHoliday);
    }

    public function testOperatingHoursFallbackToNull() {
        $hours = $this->repository->getOperatingHours('nonexistentday');
        $this->assertNull($hours);
    }
}