<?php
/**
 * tests/CalendarFeedTest.php
 * iCalendar feed: token handling, which bookings are included, escaping and line folding.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/CalendarFeed.php';

class CalendarFeedTest extends TestCase {
    private string $secrets;
    private DateTimeZone $tz;

    protected function setUp(): void {
        $this->secrets = sys_get_temp_dir() . '/bookmecal-secrets-' . bin2hex(random_bytes(4)) . '.json';
        $this->tz = new DateTimeZone('Europe/Stockholm');
    }

    protected function tearDown(): void {
        @unlink($this->secrets);
    }

    private static function booking(string $id, string $date, string $time, string $end, string $status, array $extra = []): array {
        return $extra + [
            'id' => $id, 'service_id' => 'foot', 'date' => $date, 'time' => $time, 'end_time' => $end,
            'customer_name' => 'Anna Svensson', 'customer_email' => 'anna@example.com', 'status' => $status,
        ];
    }

    private function render(array $bookings, string $now = '2026-10-22 12:00'): string {
        return CalendarFeed::render($bookings, ['foot' => 'Medical Foot Care'], 'Salong', $this->tz, new DateTimeImmutable($now, $this->tz));
    }

    public function testFeedIsOffUntilATokenIsCreated() {
        $feed = new CalendarFeed($this->secrets);
        $this->assertNull($feed->token());
        $this->assertFalse($feed->accepts(''));

        $token = $feed->renewToken();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertTrue($feed->accepts($token));
        $this->assertFalse($feed->accepts(substr($token, 0, 63)));
    }

    public function testRenewInvalidatesOldTokenAndKeepsOtherSecrets() {
        file_put_contents($this->secrets, json_encode(['form_secret' => str_repeat('a', 64)]));
        $feed = new CalendarFeed($this->secrets);
        $old = $feed->renewToken();
        $new = $feed->renewToken();

        $this->assertNotSame($old, $new);
        $this->assertFalse($feed->accepts($old));
        $this->assertTrue($feed->accepts($new));
        $this->assertSame(str_repeat('a', 64), json_decode(file_get_contents($this->secrets), true)['form_secret']);

        $this->assertTrue($feed->disable());
        $this->assertFalse($feed->accepts($new));
        $this->assertArrayHasKey('form_secret', json_decode(file_get_contents($this->secrets), true));
    }

    public function testIncludesBlockingBookingsWithUtcTimesAndStatus() {
        $ics = $this->render([
            self::booking('book_a1', '2026-10-23', '10:00', '10:45', 'approved'),
            self::booking('book_b2', '2026-10-24', '09:00', '09:30', 'pending'),
            self::booking('book_c3', '2026-10-25', '09:00', '09:30', 'rejected'),
            self::booking('book_d4', '2026-10-25', '11:00', '11:30', 'expired'),
        ]);

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\nVERSION:2.0\r\n", $ics);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);
        $this->assertSame(2, substr_count($ics, 'BEGIN:VEVENT'));
        // 10:00 CEST (UTC+2) is 08:00 UTC
        $this->assertStringContainsString("UID:book_a1@bookmecal\r\nDTSTAMP:20261022T100000Z\r\nDTSTART:20261023T080000Z\r\nDTEND:20261023T084500Z\r\n", $ics);
        $this->assertStringContainsString("SUMMARY:Medical Foot Care – Anna Svensson\r\n", $ics);
        $this->assertStringContainsString("SUMMARY:? Medical Foot Care – Anna Svensson\r\n", $ics);
        $this->assertStringContainsString('STATUS:TENTATIVE', $ics);
        $this->assertStringNotContainsString('book_c3', $ics);
        $this->assertStringNotContainsString('book_d4', $ics);
    }

    public function testLeavesOutOldAndMalformedBookings() {
        $ics = $this->render([
            self::booking('book_01', '2026-09-01', '10:00', '10:45', 'approved'),
            self::booking('book_02', '2026-09-30', '10:00', '10:45', 'approved'),
            self::booking('book_03', '2026-13-01', '10:00', '10:45', 'approved'),
            self::booking('../evil', '2026-10-23', '10:00', '10:45', 'approved'),
        ]);
        $this->assertStringNotContainsString('book_01', $ics);
        $this->assertStringContainsString('UID:book_02@bookmecal', $ics);
        $this->assertStringNotContainsString('book_03', $ics);
        $this->assertStringNotContainsString('evil', $ics);
    }

    public function testMissingEndTimeDefaultsToOneHour() {
        $ics = $this->render([self::booking('book_e5', '2026-12-01', '10:00', '', 'approved')]);
        // Winter time: UTC+1
        $this->assertStringContainsString("DTSTART:20261201T090000Z\r\nDTEND:20261201T100000Z", $ics);
    }

    public function testCustomerTextCannotAddProperties() {
        $ics = $this->render([self::booking('book_f6', '2026-10-23', '10:00', '10:45', 'approved', [
            'customer_name' => "Eve\r\nATTENDEE:mailto:x@example.com; a,b\\",
        ])]);
        $this->assertStringNotContainsString("\r\nATTENDEE", $ics);
        $this->assertStringContainsString('Eve\nATTENDEE:mailto:x@example.com\; a\,b\\\\', str_replace("\r\n ", '', $ics));
    }

    public function testFoldsLongLinesWithoutSplittingCharacters() {
        $line = 'SUMMARY:' . str_repeat('åäö', 40);
        $folded = CalendarFeed::fold($line);
        foreach (explode("\r\n", $folded) as $part) {
            $this->assertLessThanOrEqual(75, strlen($part));
            $this->assertTrue(mb_check_encoding(ltrim($part, ' '), 'UTF-8'));
        }
        $this->assertSame($line, str_replace("\r\n ", '', $folded));
        $this->assertSame('SHORT:x', CalendarFeed::fold('SHORT:x'));
    }

    public function testSingleEventForTheCustomerHasNoContactDetails() {
        $ics = CalendarFeed::single(self::booking('book_a1', '2026-10-23', '10:00', '10:45', 'approved'), 'Medical Foot Care – Salong', $this->tz, new DateTimeImmutable('2026-10-22 12:00', $this->tz));
        $this->assertSame(1, substr_count($ics, 'BEGIN:VEVENT'));
        $this->assertStringContainsString("DTSTART:20261023T080000Z\r\nDTEND:20261023T084500Z\r\nSUMMARY:Medical Foot Care – Salong\r\nSTATUS:CONFIRMED", $ics);
        $this->assertStringNotContainsString('anna@example.com', $ics);
        $this->assertStringNotContainsString('REFRESH-INTERVAL', $ics);
        $this->assertNull(CalendarFeed::single(self::booking('book_a1', 'x', '10:00', '10:45', 'approved'), 'x', $this->tz));
    }
}
