<?php
/**
 * tests/StaffTest.php
 * Unit tests for Staff: bookable employees, treatment lists and weekly hours.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/Staff.php';

class StaffTest extends TestCase {
    public function testBookableNeedsActiveAndBookable() {
        $this->assertTrue(Staff::isBookable([]));
        $this->assertFalse(Staff::isBookable(['isBookable' => false]));
        $this->assertFalse(Staff::isBookable(['inactive' => true]));
    }

    public function testEmptyTreatmentListOffersEverything() {
        $this->assertTrue(Staff::offers([], ['srv']));
        $this->assertTrue(Staff::offers(['services' => ['a', 'srv']], ['other', 'srv']));
        $this->assertFalse(Staff::offers(['services' => ['a']], ['srv']));
    }

    public function testHoursWithoutScheduleFollowOpeningHours() {
        $this->assertNull(Staff::hours([], 'monday'));
        $this->assertSame([], Staff::hours(['schedule' => []], 'monday'));
        $this->assertSame([8, 9, 13], Staff::hours(['schedule' => ['monday' => ['13:00', '08:00', '09:00', '9:00', 'x']]], 'monday'));
    }

    public function testDateExceptionWinsOverWeeklyHours() {
        $employee = ['schedule' => ['monday' => ['08:00']], 'exceptions' => ['2099-01-05' => ['hours' => [], 'note' => 'Sick'], '2099-01-06' => ['hours' => ['12:00']]]];
        $this->assertSame([], Staff::hours($employee, 'monday', '2099-01-05'));
        $this->assertSame([8], Staff::hours($employee, 'monday', '2099-01-12'));
        $this->assertSame([8], Staff::hours($employee, 'monday'));
        // Also for an employee who otherwise follows the opening hours
        $this->assertSame([12], Staff::hours(['exceptions' => $employee['exceptions']], 'tuesday', '2099-01-06'));
        $this->assertNull(Staff::hours(['exceptions' => $employee['exceptions']], 'tuesday', '2099-01-13'));
    }

    public function testSetExceptionsCoversARangeAndDropsPastDates() {
        $exceptions = Staff::setExceptions(['2000-01-01' => ['hours' => [], 'note' => '']], '2099-07-01', '2099-07-03', [], 'Vacation', '2026-10-22');
        $this->assertSame(['2099-07-01', '2099-07-02', '2099-07-03'], array_keys($exceptions));
        $this->assertSame(['hours' => [], 'note' => 'Vacation'], $exceptions['2099-07-02']);
        $this->assertSame(['2099-07-01', '2099-07-03'], array_keys(Staff::setExceptions($exceptions, '2099-07-02', '2099-07-02', null, '', '2026-10-22')));
        $this->assertNull(Staff::setExceptions([], '2099-07-03', '2099-07-01', [], '', '2026-10-22'));
        $this->assertNull(Staff::setExceptions([], '2099-02-30', '2099-03-01', [], '', '2026-10-22'));
        $this->assertNull(Staff::setExceptions([], '2099-01-01', '2100-06-01', [], '', '2026-10-22'));
    }

    public function testExceptionRangesJoinConsecutiveEqualDays() {
        $employee = ['exceptions' => [
            '2099-07-02' => ['hours' => [], 'note' => 'Vacation'],
            '2099-07-01' => ['hours' => [], 'note' => 'Vacation'],
            '2099-07-03' => ['hours' => ['10:00', '11:00'], 'note' => ''],
            '2099-07-05' => ['hours' => ['10:00', '11:00'], 'note' => ''],
            '2000-01-01' => ['hours' => [], 'note' => 'old'],
        ]];
        $this->assertSame([
            ['from' => '2099-07-01', 'to' => '2099-07-02', 'hours' => [], 'note' => 'Vacation'],
            ['from' => '2099-07-03', 'to' => '2099-07-03', 'hours' => [10, 11], 'note' => ''],
            ['from' => '2099-07-05', 'to' => '2099-07-05', 'hours' => [10, 11], 'note' => ''],
        ], Staff::exceptionRanges($employee, '2026-10-22'));
        $this->assertSame(['09:00', '10:00'], Staff::hourRange(9, 11));
        $this->assertSame([], Staff::hourRange(11, 9));
    }

    public function testWindowsJoinHoursAndStayInsideOpeningHours() {
        // Open 08:30-16:00; works 08, 09, 10 and 15, 16
        $this->assertSame([[510, 660], [900, 960]], Staff::windows([8, 9, 10, 15, 16], 510, 960));
        $this->assertSame([[510, 960]], Staff::windows(null, 510, 960));
        $this->assertSame([], Staff::windows([20], 510, 960));
    }

    public function testScheduleFromPostKeepsOnlyRealHours() {
        $schedule = Staff::scheduleFromPost(['monday' => ['08:00' => '1', '25:00' => '1', '09:00' => '1'], 'funday' => ['08:00' => '1']]);
        $this->assertSame(['08:00', '09:00'], $schedule['monday']);
        $this->assertSame([], $schedule['sunday']);
        $this->assertArrayNotHasKey('funday', $schedule);
    }

    public function testNameJoinsGivenAndFamilyName() {
        $this->assertSame('Anna Berg', Staff::name(['givenName' => 'Anna', 'familyName' => 'Berg']));
        $this->assertSame('Berg', Staff::name(['familyName' => 'Berg']));
    }
}
