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
        $this->service = new BookingService(null, null, false);
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
        return new BookingService($repository, null, false);
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

    /**
     * Monday 08:00-12:00, 60 min treatment without cleanup. A works 08-10 and is booked 08-09,
     * B follows the opening hours and is booked 09-10, C only offers another treatment, D is inactive.
     */
    private function staffedService(array $extraBookings = [], ?array $employees = null): BookingService {
        $repository = new class($extraBookings) extends BookingRepository {
            public function __construct(private array $extra) {
                parent::__construct();
            }
            public function getServiceById(string $serviceId): ?array {
                return $serviceId === 'srv' ? ['name' => 'Test', 'duration' => 60, 'cleanup' => 0, 'is_active' => true] : null;
            }
            public function isHoliday(string $dateStr, string $countryCode = 'se'): bool {
                return false;
            }
            public function getOperatingHours(string $dayOfWeek): ?array {
                return $dayOfWeek === 'monday' ? ['is_open' => true, 'open_time' => '08:00', 'close_time' => '12:00'] : ['is_open' => false];
            }
            public function getBlockingIntervals(string $dateStr): array {
                $tz = ResourceProvider::getTimezone();
                $at = fn ($time) => DateTime::createFromFormat('Y-m-d H:i', "{$dateStr} {$time}", $tz);
                $intervals = [];
                foreach (array_merge([['08:00', '09:00', 'emp_a', 'bk_a'], ['09:00', '10:00', 'emp_b', 'bk_b']], $this->extra) as $booking) {
                    [$start, $end, $employee, $id] = array_pad($booking, 4, '');
                    $intervals[] = ['id' => $id, 'start' => $at($start), 'end' => $at($end), 'employee_id' => $employee];
                }
                return $intervals;
            }
        };
        return new BookingService($repository, $employees ?? [
            'emp_a' => ['givenName' => 'Anna', 'isBookable' => true, 'schedule' => ['monday' => ['08:00', '09:00']]],
            'emp_b' => ['givenName' => 'Bea', 'isBookable' => true, 'services' => ['srv']],
            'emp_c' => ['givenName' => 'Cleo', 'isBookable' => true, 'services' => ['other']],
            'emp_d' => ['givenName' => 'Dina', 'isBookable' => true, 'inactive' => true],
        ], false);
    }

    public function testAnyEmployeeCombinesSchedules() {
        $starts = array_column($this->staffedService()->getAvailableSlots('2099-01-05', 'srv'), 'start_time');
        // 08:00 Bea, 09:00 Anna, from 10:00 Bea again
        $this->assertSame(['08:00', '09:00', '10:00', '10:05'], array_slice($starts, 0, 4));
        $this->assertSame('11:00', end($starts));
        $this->assertNotContains('08:05', $starts);
    }

    public function testOneEmployeeShowsOwnHoursAndBookings() {
        $service = $this->staffedService();
        $this->assertSame(['09:00'], array_column($service->getAvailableSlots('2099-01-05', 'srv', 'se', 'emp_a'), 'start_time'));
        $day = $service->getWeekPlan('2099-01-05', 'srv', 'se', 'emp_a')['days'][0];
        $this->assertSame([
            ['start' => '08:00', 'end' => '09:00', 'kind' => 'booked'],
            ['start' => '10:00', 'end' => '12:00', 'kind' => 'off'],
        ], $day['busy']);
        $this->assertArrayNotHasKey('employees', $day['slots'][0]);
    }

    public function testEmployeeWhoDoesNotOfferTheServiceIsRejected() {
        $service = $this->staffedService();
        $this->assertNull($service->getAvailableSlots('2099-01-05', 'srv', 'se', 'emp_c'));
        $this->assertNull($service->getWeekPlan('2099-01-05', 'srv', 'se', 'emp_d'));
        $this->assertNull($service->getWeekPlan('2099-01-05', 'srv', 'se', 'emp_nobody'));
        $this->assertSame(['emp_a', 'emp_b'], array_keys($service->staffFor('srv')));
    }

    public function testAssignPicksAFreeEmployee() {
        $service = $this->staffedService();
        $this->assertSame(['employee_id' => 'emp_b', 'end_time' => '09:00'], $service->assign('2099-01-05', 'srv', '08:00'));
        $this->assertSame(['employee_id' => 'emp_a', 'end_time' => '10:00'], $service->assign('2099-01-05', 'srv', '09:00'));
        $this->assertNull($service->assign('2099-01-05', 'srv', '09:00', 'emp_b'));
        $this->assertNull($service->assign('2099-01-05', 'srv', '08:05'));
    }

    public function testAssignPrefersTheLeastBookedEmployee() {
        // Eva and Frida both follow the opening hours; Eva already has a booking that day
        $staff = ['emp_e' => ['givenName' => 'Eva'], 'emp_f' => ['givenName' => 'Frida']];
        $this->assertSame('emp_e', $this->staffedService([], $staff)->assign('2099-01-05', 'srv', '08:00')['employee_id']);
        $busyEva = $this->staffedService([['11:00', '12:00', 'emp_e']], $staff);
        $this->assertSame('emp_f', $busyEva->assign('2099-01-05', 'srv', '08:00')['employee_id']);
    }

    public function testBookingWithoutEmployeeBlocksEveryone() {
        $starts = array_column($this->staffedService([['10:00', '12:00', null]])->getAvailableSlots('2099-01-05', 'srv'), 'start_time');
        $this->assertSame(['08:00', '09:00'], $starts);
    }

    public function testDateExceptionReplacesWeeklyHours() {
        $staff = [
            'emp_a' => ['givenName' => 'Anna', 'schedule' => ['monday' => ['08:00', '09:00']], 'exceptions' => ['2099-01-05' => ['hours' => ['10:00'], 'note' => 'Late start']]],
            'emp_b' => ['givenName' => 'Bea', 'exceptions' => ['2099-01-05' => ['hours' => [], 'note' => 'Sick']]],
        ];
        $service = $this->staffedService([], $staff);
        $this->assertSame(['10:00'], array_column($service->getAvailableSlots('2099-01-05', 'srv'), 'start_time'));
        // The exception is for one date only: the next Monday follows the weekly hours again
        $this->assertSame(['09:00'], array_column($service->getAvailableSlots('2099-01-12', 'srv', 'se', 'emp_a'), 'start_time'));
    }

    public function testBookingMovesOnlyToAFreeEmployeeOfferingIt() {
        $service = $this->staffedService([['11:00', '12:00', null, 'bk_n']]);
        $booking = fn (string $id, string $time, ?string $employee, string $status = 'approved') => [
            'id' => $id, 'service_id' => 'srv', 'date' => '2099-01-05', 'time' => $time, 'status' => $status, 'employee_id' => $employee,
        ];
        // Anna works 08-10 and is free 09-10; Bea is free 08-09
        $this->assertTrue($service->canMoveTo($booking('bk_b', '09:00', 'emp_b'), 'emp_a'));
        $this->assertTrue($service->canMoveTo($booking('bk_a', '08:00', 'emp_a', 'pending'), 'emp_b'));
        // A booking without employee blocks everyone, but not itself
        $this->assertTrue($service->canMoveTo($booking('bk_n', '11:00', null), 'emp_b'));
        $this->assertFalse($service->canMoveTo($booking('bk_n', '11:00', null), 'emp_a'), 'Anna does not work at 11');
        $this->assertFalse($service->canMoveTo($booking('bk_a', '08:00', 'emp_a'), 'emp_a'), 'already hers');
        $this->assertFalse($service->canMoveTo($booking('bk_a', '08:00', 'emp_a'), 'emp_c'), 'does not offer it');
        $this->assertFalse($service->canMoveTo($booking('bk_a', '08:00', 'emp_a'), 'emp_d'), 'inactive');
        $this->assertFalse($service->canMoveTo($booking('bk_x', '09:00', 'emp_b', 'rejected'), 'emp_a'), 'no longer holds the time');
        $this->assertFalse($service->canMoveTo($booking('bk_x', '09:00', null), 'emp_b'), 'Bea is booked at 09');
    }

    /** Monday 08:00-12:00, 45+15 min treatment, a 20+10 min one, a booking 10:00-11:00. */
    private function gapService(bool $avoidSmallGaps): BookingService {
        $repository = new class extends BookingRepository {
            public function getServices(): ?array {
                return ['srv' => ['name' => 'Test', 'duration' => 45, 'cleanup' => 15, 'is_active' => true],
                    'short' => ['name' => 'Short', 'duration' => 20, 'cleanup' => 10, 'is_active' => true],
                    'off' => ['name' => 'Inactive', 'duration' => 5, 'is_active' => false]];
            }
            public function getServiceById(string $serviceId): ?array {
                return $this->getServices()[$serviceId] ?? null;
            }
            public function isHoliday(string $dateStr, string $countryCode = 'se'): bool {
                return false;
            }
            public function getOperatingHours(string $dayOfWeek): ?array {
                return $dayOfWeek === 'monday' ? ['is_open' => true, 'open_time' => '08:00', 'close_time' => '12:00'] : ['is_open' => false];
            }
            public function getBlockingIntervals(string $dateStr): array {
                $tz = ResourceProvider::getTimezone();
                return [['id' => 'book_1', 'start' => DateTime::createFromFormat('Y-m-d H:i', "{$dateStr} 10:00", $tz),
                    'end' => DateTime::createFromFormat('Y-m-d H:i', "{$dateStr} 11:00", $tz), 'employee_id' => null]];
            }
        };
        return new BookingService($repository, [], $avoidSmallGaps);
    }

    public function testSmallGapsCanBeAvoided() {
        $all = array_column($this->gapService(false)->getAvailableSlots('2099-01-05', 'srv'), 'start_time');
        $this->assertCount(13 + 4, $all);
        // Gaps of 0 or at least 30 minutes (the shortest treatment with cleanup); 11:00-12:00 cannot avoid
        // a short gap, so only its edges are kept (the last cleanup may run past closing)
        $compact = array_column($this->gapService(true)->getAvailableSlots('2099-01-05', 'srv'), 'start_time');
        $this->assertSame(['08:00', '08:30', '09:00', '11:00', '11:15'], $compact);
        $this->assertSame(['employee_id' => null, 'end_time' => '08:45'], $this->gapService(true)->assign('2099-01-05', 'srv', '08:00'));
        $this->assertNull($this->gapService(true)->assign('2099-01-05', 'srv', '08:05'));
    }

    public function testWithoutStaffTheVenueIsBooked() {
        $repository = new class extends BookingRepository {
            public function getServiceById(string $serviceId): ?array {
                return ['name' => 'Test', 'duration' => 60, 'is_active' => true];
            }
            public function isHoliday(string $dateStr, string $countryCode = 'se'): bool {
                return false;
            }
            public function getOperatingHours(string $dayOfWeek): ?array {
                return ['is_open' => true, 'open_time' => '08:00', 'close_time' => '10:00'];
            }
            public function getBlockingIntervals(string $dateStr): array {
                return [];
            }
        };
        $service = new BookingService($repository, ['emp_x' => ['isBookable' => false]], false);
        $this->assertFalse($service->hasStaff());
        $this->assertSame(['employee_id' => null, 'end_time' => '09:00'], $service->assign('2099-01-05', 'srv', '08:00'));
        $this->assertNull($service->getAvailableSlots('2099-01-05', 'srv', 'se', 'emp_x'));
    }

    /**
     * Monday 08:00-12:00 with Eva (all treatments) and Frida (only 'a'), both following the opening
     * hours. Treatments: a 60 min, b 30 min + 10 cleanup, x 30 min exclusive; sets ab and broken.
     * $bookings: [start, end, employee, exclusive].
     */
    private function venue(array $bookings): BookingService {
        $repository = new class($bookings) extends BookingRepository {
            public function __construct(private array $list) {
                parent::__construct();
            }
            public function getServices(): ?array {
                return [
                    'a' => ['name' => 'A', 'duration' => 60],
                    'b' => ['name' => 'B', 'duration' => 30, 'cleanup' => 10],
                    'x' => ['name' => 'X', 'duration' => 30, 'exclusive' => true],
                    'ab' => ['name' => 'A+B', 'items' => ['a', 'b'], 'price_incl_vat' => 900],
                    'broken' => ['name' => 'Gone', 'items' => ['a', 'deleted']],
                ];
            }
            public function getServiceById(string $serviceId): ?array {
                return $this->getServices()[$serviceId] ?? null;
            }
            public function isHoliday(string $dateStr, string $countryCode = 'se'): bool {
                return false;
            }
            public function getOperatingHours(string $dayOfWeek): ?array {
                return ['is_open' => true, 'open_time' => '08:00', 'close_time' => '12:00'];
            }
            public function getBlockingIntervals(string $dateStr): array {
                $tz = ResourceProvider::getTimezone();
                $at = fn ($time) => DateTime::createFromFormat('Y-m-d H:i', "{$dateStr} {$time}", $tz);
                return array_map(fn ($b) => ['start' => $at($b[0]), 'end' => $at($b[1]), 'employee_id' => $b[2], 'exclusive' => $b[3]], $this->list);
            }
        };
        return new BookingService($repository, [
            'emp_e' => ['givenName' => 'Eva'],
            'emp_f' => ['givenName' => 'Frida', 'services' => ['a']],
        ], false);
    }

    public function testExclusiveBookingBlocksEveryone() {
        $starts = array_column($this->venue([['08:00', '09:00', 'emp_e', true]])->getAvailableSlots('2099-01-05', 'a'), 'start_time');
        $this->assertSame('09:00', $starts[0]);
        $starts = array_column($this->venue([['08:00', '09:00', 'emp_e', false]])->getAvailableSlots('2099-01-05', 'a'), 'start_time');
        $this->assertSame('08:00', $starts[0]);
    }

    public function testExclusiveTreatmentNeedsTheVenueToItself() {
        // Eva is booked 10-11 for a normal treatment; Frida does not do x, and x cannot run beside the booking
        $starts = array_column($this->venue([['10:00', '11:00', 'emp_e', false]])->getAvailableSlots('2099-01-05', 'x'), 'start_time');
        $last = array_search('09:30', $starts, true);
        $this->assertNotFalse($last);
        // Nothing between 09:30 (ends as the booking starts) and 11:00 (when it is over)
        $this->assertSame('11:00', $starts[$last + 1]);
    }

    public function testSetRunsBackToBackWithOneEmployee() {
        $service = $this->venue([]);
        $this->assertSame(['emp_e'], array_keys($service->staffFor('ab')));
        $week = $service->getWeekPlan('2099-01-05', 'ab');
        $this->assertSame([90, 10], [$week['duration'], $week['cleanup']]);
        $this->assertSame(['employee_id' => 'emp_e', 'end_time' => '09:30'], $service->assign('2099-01-05', 'ab', '08:00'));
        $this->assertNull($service->assign('2099-01-05', 'ab', '08:00', 'emp_f'));
        $this->assertNull($service->getWeekPlan('2099-01-05', 'broken'));
    }
}
