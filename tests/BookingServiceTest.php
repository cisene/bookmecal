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

    /** Repository with one 45+15 min service, Monday 08:00-11:00 and one booking blocking 08:30-09:30. */
    private function stubbedService(): BookingService {
        $repository = new class extends BookingRepository {
            public function getServiceById(string $serviceId): ?array {
                return $serviceId === 'srv' ? ['name' => 'Test', 'duration' => 45, 'cleanup' => 15, 'is_active' => true] : null;
            }
            public function isHoliday(string $dateStr, string $countryCode = 'se'): bool {
                return false;
            }
            public function getOperatingHours(string $dayOfWeek): ?array {
                return $dayOfWeek === 'monday' ? ['is_open' => true, 'open_time' => '08:00', 'close_time' => '11:00'] : ['is_open' => false];
            }
            public function getBlockingIntervals(string $dateStr): array {
                $tz = ResourceProvider::getTimezone();
                return [[
                    'start' => DateTime::createFromFormat('Y-m-d H:i', "{$dateStr} 08:30", $tz),
                    'end' => DateTime::createFromFormat('Y-m-d H:i', "{$dateStr} 09:30", $tz),
                ]];
            }
        };
        return new BookingService($repository);
    }

    public function testSoftSlotsStartEveryStepAroundBookings() {
        $slots = $this->stubbedService()->getAvailableSlots('2099-01-05', 'srv');
        $starts = array_column($slots, 'start_time');
        // 08:00 would run into the booking; after it, starts until 10:15 still end by closing
        $this->assertSame(['09:30', '09:35', '09:40', '09:45', '09:50', '09:55', '10:00', '10:05', '10:10', '10:15'], $starts);
        $this->assertSame('10:15', $slots[0]['end_time']);
    }

    public function testWeekPlanCoversMondayToSunday() {
        $week = $this->stubbedService()->getWeekPlan('2099-01-07', 'srv');
        $this->assertSame('2099-01-05', $week['week_start']);
        $this->assertCount(7, $week['days']);
        $this->assertSame(BookingService::SLOT_STEP, $week['step']);
        $this->assertSame(['08:00', '11:00'], [$week['days'][0]['open'], $week['days'][0]['close']]);
        $this->assertSame([['start' => '08:30', 'end' => '09:30', 'kind' => 'booked']], $week['days'][0]['busy']);
        $this->assertNull($week['days'][1]['open']);
        $this->assertSame([], $week['days'][1]['slots']);
    }

    public function testPastDaysHaveNoSlots() {
        $week = $this->stubbedService()->getWeekPlan('2000-01-03', 'srv');
        $this->assertSame([], $week['days'][0]['slots']);
        $this->assertSame('past', $week['days'][0]['busy'][0]['kind']);
    }

    public function testWeekPlanRejectsUnknownService() {
        $this->assertNull($this->stubbedService()->getWeekPlan('2099-01-05', 'nope'));
    }
}
