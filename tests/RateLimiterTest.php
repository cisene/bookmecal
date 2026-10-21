<?php
/**
 * tests/RateLimiterTest.php
 * Sliding-window limits and client keys.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/RateLimiter.php';

class RateLimiterTest extends TestCase {
    private string $dir;

    protected function setUp(): void {
        $this->dir = sys_get_temp_dir() . '/bookmecal-ratelimit-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
    }

    public function testBlocksAfterLimitWithinWindow() {
        $limiter = new RateLimiter($this->dir);
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($limiter->hit('book', '192.0.2.1', 3, 60, 1000 + $i));
        }
        $this->assertFalse($limiter->hit('book', '192.0.2.1', 3, 60, 1010));
        // Other clients and other buckets are counted separately
        $this->assertTrue($limiter->hit('book', '192.0.2.2', 3, 60, 1010));
        $this->assertTrue($limiter->hit('read', '192.0.2.1', 3, 60, 1010));
    }

    public function testAllowsAgainWhenWindowHasPassed() {
        $limiter = new RateLimiter($this->dir);
        $this->assertTrue($limiter->hit('book', 'a', 1, 60, 1000));
        $this->assertFalse($limiter->hit('book', 'a', 1, 60, 1059));
        $this->assertTrue($limiter->hit('book', 'a', 1, 60, 1061));
    }

    public function testFilesDoNotContainTheClientAddress() {
        (new RateLimiter($this->dir))->hit('book', '192.0.2.1', 3, 60);
        foreach (glob($this->dir . '/*') as $file) {
            $this->assertStringNotContainsString('192.0.2.1', basename($file) . file_get_contents($file));
        }
    }

    public function testClientKeyGroupsIpv6ByPrefix() {
        $this->assertSame('192.0.2.1', RateLimiter::clientKey('192.0.2.1'));
        $this->assertSame(RateLimiter::clientKey('2001:db8:1:2::1'), RateLimiter::clientKey('2001:db8:1:2:ffff::9'));
        $this->assertNotSame(RateLimiter::clientKey('2001:db8:1:2::1'), RateLimiter::clientKey('2001:db8:1:3::1'));
        $this->assertSame('unknown', RateLimiter::clientKey('not an ip'));
    }
}
