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

    private function tempRepository(array $bookings): array {
        $dir = sys_get_temp_dir() . '/bookmecal-pending-' . bin2hex(random_bytes(4));
        mkdir($dir);
        foreach ($bookings as $booking) {
            file_put_contents("{$dir}/booking_{$booking['id']}.json", json_encode($booking));
        }
        return [new BookingRepository($dir), $dir];
    }

    private function removeDir(string $dir): void {
        array_map('unlink', glob($dir . '/*') ?: []);
        @rmdir($dir);
    }

    public function testExpireStalePendingFreesOnlyOldPendingRequests() {
        $now = new DateTimeImmutable('2099-01-10T12:00:00+01:00');
        [$repository, $dir] = $this->tempRepository([
            ['id' => 'book_a1', 'status' => 'pending', 'date' => '2099-01-12', 'time' => '10:00', 'created_at_iso' => '2099-01-08T11:00:00+01:00'],
            ['id' => 'book_b2', 'status' => 'pending', 'date' => '2099-01-12', 'time' => '11:00', 'created_at_iso' => '2099-01-09T13:00:00+01:00'],
            ['id' => 'book_c3', 'status' => 'approved', 'date' => '2099-01-12', 'time' => '12:00', 'created_at_iso' => '2099-01-01T10:00:00+01:00'],
        ]);
        try {
            $this->assertSame(1, $repository->expireStalePending(48, $now));
            $bookings = $repository->getBookings();
            $this->assertSame('expired', $bookings['book_a1']['status']);
            $this->assertSame('pending', $bookings['book_b2']['status']);
            $this->assertSame('approved', $bookings['book_c3']['status']);
            $this->assertSame(0, $repository->expireStalePending(0, $now), '0 hours disables expiry');
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testCountUpcomingPendingForEmail() {
        $now = new DateTimeImmutable('2099-01-10T12:00:00', ResourceProvider::getTimezone());
        [$repository, $dir] = $this->tempRepository([
            ['id' => 'book_a1', 'status' => 'pending', 'date' => '2099-01-12', 'time' => '10:00', 'customer_email' => 'Anna@Example.com'],
            ['id' => 'book_b2', 'status' => 'pending', 'date' => '2099-01-13', 'time' => '10:00', 'customer_email' => 'anna@example.com'],
            ['id' => 'book_c3', 'status' => 'pending', 'date' => '2099-01-09', 'time' => '10:00', 'customer_email' => 'anna@example.com'],
            ['id' => 'book_d4', 'status' => 'approved', 'date' => '2099-01-14', 'time' => '10:00', 'customer_email' => 'anna@example.com'],
            ['id' => 'book_e5', 'status' => 'pending', 'date' => '2099-01-14', 'time' => '10:00', 'customer_email' => 'bo@example.com'],
        ]);
        try {
            $this->assertSame(2, $repository->countUpcomingPendingFor('ANNA@example.com', $now));
        } finally {
            $this->removeDir($dir);
        }
    }
}
