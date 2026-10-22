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
