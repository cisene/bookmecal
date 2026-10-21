<?php
/**
 * tests/SecurityLogTest.php
 * Security events as JSON lines.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/SecurityLog.php';

class SecurityLogTest extends TestCase {
    private string $file;

    protected function setUp(): void {
        $this->file = sys_get_temp_dir() . '/bookmecal-security-' . bin2hex(random_bytes(4)) . '.log';
        SecurityLog::setFile($this->file);
    }

    protected function tearDown(): void {
        SecurityLog::setFile(null);
        @unlink($this->file);
    }

    public function testRecordsOneJsonLinePerEventNewestFirst() {
        $_SERVER['REMOTE_ADDR'] = '192.0.2.7';
        SecurityLog::record('login_failed', ['username' => 'anna']);
        SecurityLog::record('rate_limited', ['bucket' => 'book']);
        unset($_SERVER['REMOTE_ADDR']);

        $this->assertCount(2, file($this->file));
        $events = SecurityLog::recent();
        $this->assertSame('rate_limited', $events[0]['event']);
        $this->assertSame('book', $events[0]['bucket']);
        $this->assertSame('login_failed', $events[1]['event']);
        $this->assertSame('192.0.2.7', $events[1]['ip']);
    }

    public function testContextCannotBreakTheLineFormat() {
        SecurityLog::record('login_failed', ['username' => "x\n{\"event\":\"fake\"}", 'nested' => ['a']]);
        $lines = file($this->file);
        $this->assertCount(1, $lines);
        $event = json_decode($lines[0], true);
        $this->assertSame("x\n{\"event\":\"fake\"}", $event['username']);
        $this->assertNull($event['nested']);
    }

    public function testRecentLimitsTheNumberOfEvents() {
        for ($i = 0; $i < 5; $i++) {
            SecurityLog::record('event' . $i);
        }
        $this->assertSame(['event4', 'event3'], array_column(SecurityLog::recent(2), 'event'));
    }
}
