<?php
/**
 * tests/SetupCheckTest.php
 * Dashboard setup checklist.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/SetupCheck.php';

class SetupCheckTest extends TestCase {
    private static function keys(array $problems): array {
        return array_column($problems, 'key');
    }

    public function testFreshInstallListsEverything() {
        $this->assertSame(
            ['noServices', 'noOpenHours', 'businessName', 'emailOff', 'publicUrl', 'https'],
            self::keys(SetupCheck::problems([], [], [], [], false))
        );
    }

    public function testReadySetupHasNoProblems() {
        $problems = SetupCheck::problems(
            ['business_name' => 'Salong Anna', 'public_url' => 'https://example.se/'],
            ['smtp' => ['enabled' => true, 'from_email' => 'anna@salong.se', 'notification_email' => 'anna@salong.se']],
            ['foot' => ['name' => 'Foot care', 'is_active' => true]],
            ['monday' => ['is_open' => true, 'open_time' => '09:00', 'close_time' => '17:00']],
            true
        );
        $this->assertSame([], $problems);
    }

    public function testEmailDetailsAreCheckedOnlyWhenEmailIsOn() {
        $base = [['business_name' => 'x', 'public_url' => 'https://x.se/'], ['foot' => ['is_active' => true]], ['monday' => ['is_open' => true]]];
        $on = SetupCheck::problems($base[0], ['smtp' => ['enabled' => true, 'from_email' => 'noreply@example.com']], $base[1], $base[2], true);
        $this->assertSame(['emailSender', 'emailAlerts'], self::keys($on));

        $off = SetupCheck::problems($base[0], ['smtp' => ['enabled' => false]], $base[1], $base[2], true);
        $this->assertSame(['emailOff'], self::keys($off));
    }

    public function testInactiveServicesAndClosedDaysDoNotCount() {
        $problems = SetupCheck::problems([], [], ['a' => ['is_active' => false]], ['monday' => ['is_open' => false]], true);
        $this->assertSame(['noServices', 'noOpenHours'], array_slice(self::keys($problems), 0, 2));
    }
}
